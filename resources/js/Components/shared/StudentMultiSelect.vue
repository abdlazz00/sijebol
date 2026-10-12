<script setup lang="ts">
import SearchInput from '@/Components/shared/SearchInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import type { Paginator } from '@/types/access';
import type { CandidateRow } from '@/types/registration';
import { Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';

const selected = defineModel<number[]>({ required: true });
const props = defineProps<{
    candidates: Paginator<CandidateRow>;
    /** Siswa terpilih sejak awal (mode ubah), termasuk yang tidak ada di halaman ini. */
    selectedRows?: CandidateRow[];
    errors?: Record<number, string>;
    reloadUrl: string;
    reloadQuery?: Record<string, unknown>;
}>();

const search = ref('');

// Baris yang pernah terlihat: pilihan dari halaman lain tetap dapat ditampilkan dan dihapus.
const known = reactive(new Map<number, CandidateRow>());
const remember = (rows: CandidateRow[]) =>
    rows.forEach((r) => known.set(r.id, r));
remember(props.selectedRows ?? []);
watch(() => props.candidates.data, remember, { immediate: true });

const chosen = computed(() =>
    selected.value
        .map((id) => known.get(id))
        .filter((r): r is CandidateRow => r !== undefined),
);

function reload(page = 1) {
    const q = search.value.trim();
    router.get(
        props.reloadUrl,
        {
            ...props.reloadQuery,
            ...(q ? { q } : {}),
            ...(page > 1 ? { page } : {}),
        },
        {
            only: ['candidates'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const onSearch = useDebounceFn(() => reload(1), 300);
watch(search, () => onSearch());

// Siswa yang terpilih tetapi sudah tidak eligible tetap bisa dibatalkan.
const isDisabled = (c: CandidateRow) =>
    !c.eligible && !selected.value.includes(c.id);

function toggle(c: CandidateRow, on: boolean) {
    selected.value = on
        ? [...selected.value, c.id]
        : selected.value.filter((id) => id !== c.id);
}

function selectAllEligible() {
    const ids = new Set(selected.value);
    props.candidates.data
        .filter((c) => c.eligible)
        .forEach((c) => ids.add(c.id));
    selected.value = [...ids];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <SearchInput
                    v-model="search"
                    placeholder="Cari nama atau NISN"
                    aria-label="Cari siswa"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                class="h-10 px-4"
                @click="selectAllEligible"
            >
                Pilih semua yang memenuhi syarat di halaman ini
            </Button>
        </div>

        <p role="status" class="text-muted-foreground text-sm">
            {{ selected.length }} dipilih
        </p>

        <div v-if="chosen.length" class="flex flex-col gap-2">
            <p class="text-sm font-medium">Terpilih ({{ chosen.length }})</p>
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="c in chosen"
                    :key="c.id"
                    class="bg-primary-soft text-primary flex items-center gap-1 rounded-full py-1 pr-1 pl-3 text-xs"
                >
                    {{ c.name }}
                    <button
                        type="button"
                        class="hover:bg-primary/10 flex size-6 items-center justify-center rounded-full"
                        :aria-label="`Hapus ${c.name} dari pilihan`"
                        @click="toggle(c, false)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </li>
            </ul>
        </div>

        <ul v-if="candidates.data.length" class="divide-y rounded-lg border">
            <li
                v-for="c in candidates.data"
                :key="c.id"
                class="flex items-start gap-3 px-4 py-3"
                :class="{ 'bg-secondary/50': isDisabled(c) }"
            >
                <Checkbox
                    :id="`siswa-${c.id}`"
                    class="mt-0.5"
                    :model-value="selected.includes(c.id)"
                    :disabled="isDisabled(c)"
                    @update:model-value="toggle(c, $event === true)"
                />
                <div class="min-w-0 flex-1 text-sm leading-5">
                    <label
                        :for="`siswa-${c.id}`"
                        class="block font-medium break-words"
                    >
                        {{ c.name }}
                        <span class="text-muted-foreground font-normal">
                            · NISN {{ c.nisn }} · {{ c.age }} th
                        </span>
                    </label>
                    <p
                        v-if="!c.eligible"
                        class="text-muted-foreground mt-1 text-xs leading-4"
                    >
                        Belum memenuhi: {{ c.reasons.join(' · ') }}
                    </p>
                    <Link
                        v-if="c.missing_docs"
                        :href="route('siswa.show', c.id)"
                        class="text-primary mt-1 inline-block text-xs underline"
                    >
                        Lengkapi dokumen
                    </Link>
                    <p v-if="errors?.[c.id]" class="text-danger mt-1 text-xs">
                        {{ errors[c.id] }}
                    </p>
                </div>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            {{
                search.trim()
                    ? 'Tidak ada siswa yang sesuai pencarian.'
                    : 'Belum ada siswa di sekolah ini.'
            }}
        </p>

        <div
            v-if="candidates.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-muted-foreground text-xs leading-4">
                Halaman {{ candidates.current_page }} dari
                {{ candidates.last_page }} · {{ candidates.total }} siswa
            </p>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 px-3"
                    :disabled="candidates.current_page <= 1"
                    @click="reload(candidates.current_page - 1)"
                >
                    Sebelumnya
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 px-3"
                    :disabled="candidates.current_page >= candidates.last_page"
                    @click="reload(candidates.current_page + 1)"
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>
</template>
