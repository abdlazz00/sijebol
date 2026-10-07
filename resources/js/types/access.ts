export interface MenuItem {
    label: string;
    href: string;
    icon: string;
}

export interface MenuGroup {
    group: string;
    items: MenuItem[];
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export type UserStatusKey = 'aktif' | 'menunggu' | 'kedaluwarsa' | 'nonaktif';

export interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string | null;
    school: string | null;
    status: UserStatusKey;
    last_login_at: string | null;
    can_manage: boolean;
}

export interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
    permissions_count: number;
    is_school_scoped: boolean;
    protected: boolean;
}

export interface Option {
    id: number;
    name: string;
}
