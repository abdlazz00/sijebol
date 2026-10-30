import type { MenuGroup } from './access';
import type { NotificationSummary } from './notification';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role?: string | null;
    school_id?: number | null;
    school?: { id: number; name: string } | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        permissions: string[];
    };
    flash: {
        success?: string | null;
        error?: string | null;
        import?: { id: number } | null;
    };
    menu: MenuGroup[];
    notifications: NotificationSummary | null;
};
