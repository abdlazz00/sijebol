import type { Recap } from '@/types/report';

export interface TableColumn {
    key: string;
    label: string;
    class?: string;
}

export type RecapTableRow = Record<string, string | number | boolean>;

/** Meratakan rekap server menjadi kolom dan baris `DataTable`; baris total hanya ada bila ada sekolah. */
export function recapTable(recap: Recap): {
    columns: TableColumn[];
    rows: RecapTableRow[];
} {
    const numeric = 'w-[96px] text-right';
    const columns: TableColumn[] = [
        { key: 'school', label: 'Sekolah', class: 'min-w-[220px]' },
        ...recap.columns.map((c) => ({
            key: c.key,
            label: c.label,
            class: numeric,
        })),
        { key: 'total', label: 'Total', class: numeric },
    ];
    const flat = (
        school: string,
        counts: Record<string, number>,
        total: number,
        isTotal: boolean,
    ): RecapTableRow => ({ school, ...counts, total, isTotal });

    const rows = recap.rows.map((r) =>
        flat(r.school, r.counts, r.total, false),
    );
    if (rows.length) {
        rows.push(flat('Total', recap.totals.counts, recap.totals.total, true));
    }

    return { columns, rows };
}
