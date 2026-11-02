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

export type StudentRecordGroupKey = 'langsung' | 'menunggu' | 'belum';

export interface StudentRecordGroup {
    key: StudentRecordGroupKey;
    label: string;
    count: number;
}

/** Baris daftar rekaman & KTP-el; tanpa NIK (NIK hanya ada di Excel). */
export type StudentRecordRow = {
    id: number;
    name: string;
    nisn: string;
    school: string;
    group: StudentRecordGroupKey;
    session_date: string | null;
    eligible_from: string;
    reason: string | null;
};

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
