export type BadgeVariant =
    'success' | 'warning' | 'danger' | 'neutral' | 'info';

export interface StatusMeta {
    label: string;
    variant: BadgeVariant;
}

const USER_STATUS: Record<string, StatusMeta> = {
    aktif: { label: 'Aktif', variant: 'success' },
    menunggu: { label: 'Menunggu aktivasi', variant: 'warning' },
    kedaluwarsa: { label: 'Kedaluwarsa', variant: 'danger' },
    nonaktif: { label: 'Nonaktif', variant: 'neutral' },
};

export function userStatus(status: string): StatusMeta {
    return USER_STATUS[status] ?? { label: status, variant: 'neutral' };
}

const REGISTRATION_STATUS: Record<string, StatusMeta> = {
    draf: { label: 'Draf', variant: 'neutral' },
    diajukan: { label: 'Diajukan', variant: 'info' },
    perlu_perbaikan: { label: 'Perlu perbaikan', variant: 'warning' },
    terverifikasi: { label: 'Terverifikasi', variant: 'success' },
    terjadwal: { label: 'Terjadwal', variant: 'info' },
    sudah_rekaman: { label: 'Sudah rekaman', variant: 'success' },
    tidak_hadir: { label: 'Tidak hadir', variant: 'danger' },
    belum_rekaman: { label: 'Belum rekaman', variant: 'warning' },
    ditolak: { label: 'Ditolak', variant: 'danger' },
};

export function registrationStatus(status: string): StatusMeta {
    return REGISTRATION_STATUS[status] ?? { label: status, variant: 'neutral' };
}
