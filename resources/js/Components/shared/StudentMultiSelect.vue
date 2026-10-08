<script setup lang="ts">
import SearchInput from '@/Components/shared/SearchInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import type { CandidateRow } from '@/types/registration';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const selected = defineModel<number[]>({ required: true });
const props = defineProps<{
    candidates: CandidateRow[];
    errors?: Record<number, string>;
}>();

const search = ref('');
const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.candidates.filter(
              (c) => c.name.toLowerCase().includes(q) || c.nisn.includes(q),
          )
        : props.candidates;
});

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
    props.candidates.filter((c) => c.eligible).forEach((c) => ids.add(c.id));
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
                Pilih semua yang eligible
            </Button>
        </div>

        <p role="status" class="text-muted-foreground text-sm">
            {{ selected.length }} dipilih
        </p>

        <ul v-if="visible.length" class="divide-y rounded-lg border">
            <li
                v-for="c in visible"
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
                    <ul
                        v-if="!c.eligible"
                        class="text-muted-foreground mt-1 list-disc pl-4 text-xs leading-4"
                    >
                        <li v-for="reason in c.reasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
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
                candidates.length
                    ? 'Tidak ada siswa yang sesuai pencarian.'
                    : 'Belum ada siswa di sekolah ini.'
            }}
        </p>
    </div>
</template>
