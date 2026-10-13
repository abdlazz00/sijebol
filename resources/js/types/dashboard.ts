/** Props Beranda; disuplai `DashboardService` dan `DashboardController`. */

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

export interface KpiCardItem {
    key: string;
    label: string;
    value: number;
    percentage?: number | null;
    unit?: string;
    variant: 'blue' | 'green' | 'amber' | 'rose' | 'slate';
    icon: 'users' | 'calendar-check' | 'id-card' | 'clock' | 'file-text' | 'shield-check' | 'alert-circle';
}

export interface QuickActionItem {
    label: string;
    href: string;
    icon: 'plus' | 'calendar-plus' | 'id-card' | 'download' | 'user-plus' | 'clipboard-check';
    variant: 'blue' | 'green' | 'amber' | 'rose';
}

export interface BarChartData {
    categories: string[];
    series: { name: string; data: number[] }[];
}

export interface DonutChartData {
    labels: string[];
    series: number[];
    total: number;
}

export interface LineChartData {
    categories: string[];
    series: { name: string; data: number[] }[];
}

export interface DashboardCharts {
    school_recap: BarChartData;
    recording_status: DonutChartData;
    monthly_trend: LineChartData;
}

export interface UpcomingScheduleRow {
    id: number;
    date: string;
    school?: string;
    school_name?: string;
    participants: number;
    location: string;
    status: 'Hari Ini' | 'Terlaksana' | 'Dijadwalkan' | string;
}

export interface CalendarEventItem {
    id: number;
    date: string;
    school?: string;
    school_name?: string;
    location: string;
    participants: number;
    status: 'Hari Ini' | 'Terlaksana' | 'Dijadwalkan' | string;
}
