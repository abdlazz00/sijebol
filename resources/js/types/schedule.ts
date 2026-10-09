import type { RegistrationStatus } from './registration';

export type ScheduleRow = {
    id: number;
    date: string;
    location: string;
    school: string | null;
    is_past: boolean;
    participants_count: number;
    counts: Partial<Record<RegistrationStatus, number>>;
};

export type ScheduleDetail = {
    id: number;
    date: string;
    location: string;
    school: string | null;
    is_past: boolean;
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
    hint?: string | null;
};

export type ScheduleTarget = {
    id: number;
    date: string;
    location: string;
};
