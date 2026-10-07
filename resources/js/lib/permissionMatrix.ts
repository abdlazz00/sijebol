export interface CatalogAction {
    value: string;
    action: string;
    editable: boolean;
}

export interface CatalogModule {
    module: string;
    actions: CatalogAction[];
}

export const MATRIX_COLUMNS = ['lihat', 'buat', 'ubah', 'hapus'] as const;

export function otherActions(module: CatalogModule): CatalogAction[] {
    return module.actions.filter(
        (a) => !(MATRIX_COLUMNS as readonly string[]).includes(a.action),
    );
}

/** Status "Pilih semua" dihitung dari izin yang dapat diedit saja. */
export function moduleState(
    selected: string[],
    module: CatalogModule,
): 'all' | 'some' | 'none' {
    const editable = module.actions.filter((a) => a.editable);
    const chosen = editable.filter((a) => selected.includes(a.value)).length;

    if (chosen === 0) return 'none';

    return chosen === editable.length ? 'all' : 'some';
}

/** Mengubah semua izin editable modul; izin non-editable dan modul lain tidak disentuh. */
export function toggleModule(
    selected: string[],
    module: CatalogModule,
    on: boolean,
): string[] {
    const editable = module.actions
        .filter((a) => a.editable)
        .map((a) => a.value);
    const kept = selected.filter((value) => !editable.includes(value));

    return on ? [...kept, ...editable] : kept;
}

export function setPermission(
    selected: string[],
    value: string,
    on: boolean,
): string[] {
    const without = selected.filter((v) => v !== value);

    return on ? [...without, value] : without;
}
