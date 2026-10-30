export interface StudentInfo {
    id: number | null;
    name: string;
    nik: string | null;
    nisn: string | null;
    school_name: string;
    has_profile: boolean;
}

export interface StepItem {
    id: number;
    title: string;
    status: 'completed' | 'current' | 'upcoming' | 'error';
    description: string;
}

export interface SmartHeroData {
    state: 'NO_PROFILE' | 'MISSING_DOCS' | 'NO_REGISTRATION' | 'MENUNGGU_VERIFIKASI' | 'PERBAIKAN' | 'TERDAFTAR' | 'SUDAH_REKAMAN' | 'DITOLAK';
    variant: 'amber' | 'blue' | 'indigo' | 'rose' | 'emerald' | 'zinc';
    title: string;
    subtitle: string;
    badge: string;
    cta_label: string;
    cta_url: string;
    deadline_at: string | null;
    notes: string | null;
}

export interface ActiveEntryData {
    id: number;
    status: string;
    status_label: string;
    ticket_code: string | null;
    schedule_date: string;
    time_window: string;
    location: string;
    direct_ktp: boolean;
    days_remaining: number;
}

export interface SchoolScheduleItem {
    id: number;
    date: string;
    quota: number;
    taken: number;
    remaining_quota: number;
    location: string;
    is_open: boolean;
}

export interface StudentDashboardProps {
    student: StudentInfo;
    stepper: {
        current_step: number;
        steps: StepItem[];
    };
    smart_hero: SmartHeroData;
    active_entry: ActiveEntryData | null;
    school_schedules: SchoolScheduleItem[];
    guidelines: string[];
}
