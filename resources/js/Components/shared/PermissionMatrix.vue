<script setup lang="ts">
import { Checkbox } from '@/Components/ui/checkbox';
import { humanize } from '@/lib/format';
import {
    MATRIX_COLUMNS,
    moduleState,
    otherActions,
    setPermission,
    toggleModule,
    type CatalogAction,
    type CatalogModule,
} from '@/lib/permissionMatrix';

defineProps<{ catalog: CatalogModule[]; disabled?: boolean }>();

// Izin yang tidak bisa diubah tetap ada di nilai agar ikut terkirim; server menjaga keasliannya.
const selected = defineModel<string[]>({ required: true });

const box =
    'border-slate-500 disabled:opacity-100 not-data-checked:disabled:bg-neutral-soft data-checked:disabled:opacity-60';

const find = (module: CatalogModule, action: string) =>
    module.actions.find((a) => a.action === action);

const label = (module: CatalogModule, action: CatalogAction) =>
    `${humanize(action.action)} ${humanize(module.module)}`;

function state(module: CatalogModule) {
    const s = moduleState(selected.value, module);

    return s === 'all' ? true : s === 'some' ? 'indeterminate' : false;
}

const hasEditable = (module: CatalogModule) =>
    module.actions.some((a) => a.editable);

function toggle(value: string, on: boolean | 'indeterminate') {
    selected.value = setPermission(selected.value, value, on === true);
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1128px] text-sm">
            <thead class="bg-secondary text-muted-foreground text-xs">
                <tr class="h-12 border-y">
                    <th
                        scope="col"
                        class="bg-secondary sticky left-0 w-[156px] px-4 text-left font-medium"
                    >
                        Modul
                    </th>
                    <th
                        scope="col"
                        class="w-[124px] px-3 text-left font-medium"
                    >
                        Pilih semua
                    </th>
                    <th
                        v-for="column in MATRIX_COLUMNS"
                        :key="column"
                        scope="col"
                        class="w-[120px] px-4 text-center font-medium"
                    >
                        {{ humanize(column) }}
                    </th>
                    <th scope="col" class="px-4 text-left font-medium">
                        Lainnya
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="module in catalog"
                    :key="module.module"
                    class="min-h-16 border-b align-middle"
                >
                    <th
                        scope="row"
                        class="sticky left-0 bg-white px-4 py-3 text-left font-medium"
                    >
                        {{ humanize(module.module) }}
                    </th>
                    <td class="px-3 py-3">
                        <label class="flex min-h-10 items-center gap-2">
                            <Checkbox
                                :class="box"
                                :model-value="state(module)"
                                :disabled="disabled || !hasEditable(module)"
                                :aria-label="`Pilih semua ${humanize(module.module)}`"
                                @update:model-value="
                                    selected = toggleModule(
                                        selected,
                                        module,
                                        state(module) !== true,
                                    )
                                "
                            />
                            Pilih semua
                        </label>
                    </td>
                    <td
                        v-for="column in MATRIX_COLUMNS"
                        :key="column"
                        class="px-4 py-3 text-center"
                    >
                        <template v-if="find(module, column)">
                            <label
                                class="inline-flex min-h-10 min-w-10 items-center justify-center"
                            >
                                <Checkbox
                                    :class="box"
                                    :model-value="
                                        selected.includes(
                                            find(module, column)!.value,
                                        )
                                    "
                                    :disabled="
                                        disabled ||
                                        !find(module, column)!.editable
                                    "
                                    :aria-label="
                                        label(module, find(module, column)!)
                                    "
                                    @update:model-value="
                                        toggle(
                                            find(module, column)!.value,
                                            $event,
                                        )
                                    "
                                />
                            </label>
                        </template>
                        <span
                            v-else
                            class="text-muted-foreground"
                            aria-label="Tidak tersedia"
                            >—</span
                        >
                    </td>
                    <td class="px-4 py-3">
                        <div
                            v-if="otherActions(module).length"
                            class="flex flex-col"
                        >
                            <label
                                v-for="action in otherActions(module)"
                                :key="action.value"
                                class="flex min-h-10 items-center gap-2"
                                :class="
                                    action.editable
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                <Checkbox
                                    :class="box"
                                    :model-value="
                                        selected.includes(action.value)
                                    "
                                    :disabled="disabled || !action.editable"
                                    :aria-label="label(module, action)"
                                    @update:model-value="
                                        toggle(action.value, $event)
                                    "
                                />
                                {{ humanize(action.action) }}
                            </label>
                        </div>
                        <span
                            v-else
                            class="text-muted-foreground"
                            aria-label="Tidak tersedia"
                            >—</span
                        >
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
