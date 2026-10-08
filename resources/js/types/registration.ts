export type RegistrationStatus =
    | 'draf'
    | 'diajukan'
    | 'perlu_perbaikan'
    | 'terverifikasi'
    | 'terjadwal'
    | 'sudah_rekaman'
    | 'tidak_hadir'
    | 'belum_rekaman'
    | 'ditolak';

export type HistoryRow = {
    from: RegistrationStatus;
    to: RegistrationStatus;
    by: string;
    note: string | null;
    at: string;
};

export type EntryRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    status: RegistrationStatus;
    note: string | null;
    decided_at: string | null;
    history: HistoryRow[];
};

export type RegistrationRow = {
    id: number;
    title: string;
    school: string | null;
    created_at: string;
    submitted_at: string | null;
    students_count: number;
    counts: Partial<Record<RegistrationStatus, number>>;
};

export type RegistrationDetail = RegistrationRow & {
    expected_count: number | null;
    created_by: string | null;
};

export type CandidateRow = {
    id: number;
    name: string;
    nisn: string;
    age: number;
    eligible: boolean;
    reasons: string[];
    missing_docs: boolean;
    selected: boolean;
};

export type QueueRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    school: string | null;
    registration: string;
    since: string;
};
