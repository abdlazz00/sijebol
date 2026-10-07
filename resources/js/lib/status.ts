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
