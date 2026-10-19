export type RegistrationStatus =
    | 'menunggu_verifikasi'
    | 'perlu_perbaikan'
    | 'terdaftar'
    | 'sudah_rekaman'
    | 'tidak_hadir'
    | 'belum_rekaman'
    | 'ditolak'
    | 'dibatalkan'
    | 'kedaluwarsa';

export type HistoryRow = {
    from: RegistrationStatus | null;
    to: RegistrationStatus;
    by: string;
    note: string | null;
    at: string;
};

export type SessionBrief = {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    location: string;
};

export type EntryRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    status: RegistrationStatus;
    note: string | null;
    decided_at: string | null;
    history: HistoryRow[];
};

export type QueueRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    school: string | null;
    session: SessionBrief;
    since: string;
};

/** Pendaftaran milik akun siswa (halaman Sesi rekaman). */
export type SelfEntry = {
    id: number;
    status: RegistrationStatus;
    note: string | null;
    can_cancel: boolean;
    can_resubmit: boolean;
    schedule: SessionBrief;
};

/** Sesi yang dapat dipilih siswa; kelayakan dihitung pada tanggal sesi itu. */
export type OpenSchedule = SessionBrief & {
    quota: number;
    taken: number;
    full: boolean;
    eligible: boolean;
    reasons: string[];
};

/** Opsi filter sesi di antrean verifikasi. */
export type QueueSession = SessionBrief & { school: string | null };
