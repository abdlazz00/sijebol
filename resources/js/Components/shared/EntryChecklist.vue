<script setup lang="ts">
import SearchInput from '@/Components/shared/SearchInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import type { ChecklistItem } from '@/types/schedule';
import { computed, ref } from 'vue';

const selected = defineModel<number[]>({ required: true });
const props = withDefaults(
    defineProps<{
        items: ChecklistItem[];
        emptyText?: string;
        disabled?: boolean;
    }>(),
    { emptyText: 'Tidak ada siswa.', disabled: false },
);

const search = ref('');
const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.items.filter(
              (i) => i.name.toLowerCase().includes(q) || i.nisn.includes(q),
          )
        : props.items;
});

function toggle(id: number, on: boolean) {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((x) => x !== id);
}

function selectAll() {
    selected.value = props.items.map((i) => i.id);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-if="items.length"
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
        >
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
                :disabled="disabled"
                @click="selectAll"
            >
                Pilih semua
            </Button>
        </div>

        <p
            v-if="items.length"
            role="status"
            class="text-muted-foreground text-sm"
        >
            {{ selected.length }} dipilih
        </p>

        <ul v-if="visible.length" class="divide-y rounded-lg border">
            <li
                v-for="item in visible"
                :key="item.id"
                class="flex items-start gap-3 px-4 py-3"
            >
                <Checkbox
                    :id="`entri-${item.id}`"
                    class="mt-0.5"
                    :model-value="selected.includes(item.id)"
                    :disabled="disabled"
                    @update:model-value="toggle(item.id, $event === true)"
                />
                <label
                    :for="`entri-${item.id}`"
                    class="min-w-0 flex-1 text-sm leading-5"
                >
                    <span class="block font-medium break-words">{{
                        item.name
                    }}</span>
                    <span class="text-muted-foreground block text-xs leading-4">
                        NISN {{ item.nisn }}
                    </span>
                </label>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            {{
                items.length
                    ? 'Tidak ada siswa yang sesuai pencarian.'
                    : emptyText
            }}
        </p>
    </div>
</template>
