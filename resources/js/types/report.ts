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

export interface SessionRecapRow {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    school: string;
    location: string;
    quota: number;
    taken: number;
    present: number;
    not_recorded: number;
    absent: number;
    attendance: number | null;
}

export interface SessionRecap {
    rows: SessionRecapRow[];
    totals: Omit<
        SessionRecapRow,
        'id' | 'date' | 'start_time' | 'end_time' | 'school' | 'location'
    >;
}
