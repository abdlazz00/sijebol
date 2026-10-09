export interface RecapColumn {
    key: string;
    label: string;
}

export interface RecapRow {
    school: string;
    counts: Record<string, number>;
    total: number;
}

export interface Recap {
    columns: RecapColumn[];
    rows: RecapRow[];
    totals: { counts: Record<string, number>; total: number };
}

export interface ReportFilters {
    dari: string | null;
    sampai: string | null;
}
