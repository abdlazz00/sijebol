/** Props opsional Beranda; belum disuplai backend (menunggu modul pendaftaran, jadwal, notifikasi). */
export interface SummaryCard {
    label: string;
    value: number;
    caption: string;
    icon: 'clipboard-list' | 'clipboard-check' | 'calendar-days';
}

export interface ActionItem {
    title: string;
    description: string;
    label: string;
    href: string;
    icon: 'clipboard-list' | 'calendar-plus';
}

export interface ScheduleItem {
    date: string;
    school: string;
    location: string;
    participants: number;
}

export interface NotificationItem {
    title: string;
    description: string;
    at: string;
    read?: boolean;
}
