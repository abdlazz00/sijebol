import type { RegistrationStatus } from './registration';

export type ScheduleRow = {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    location: string;
    school: string | null;
    is_past: boolean;
    cancelled: boolean;
    quota: number;
    taken: number;
    participants_count: number;
    counts: Partial<Record<RegistrationStatus, number>>;
};

export type ScheduleDetail = {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    location: string;
    school: string | null;
    quota: number;
    taken: number;
    cancelled: boolean;
    is_past: boolean;
    last_registration_day: string;
    created_by?: string | null;
};

export type ParticipantRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    status: RegistrationStatus;
};

export type ChecklistItem = {
    id: number;
    name: string;
    nisn: string;
};
