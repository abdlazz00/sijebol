export type NotificationRow = {
    id: string;
    title: string;
    body: string;
    url: string;
    read_at: string | null;
    created_at: string;
};

export type NotificationSummary = {
    unread_count: number;
    latest: NotificationRow[];
};

export type ActivityChange = { field: string; old: unknown; new: unknown };

export type ActivityRow = {
    id: number;
    created_at: string;
    causer: { id: number; name: string } | null;
    action: string;
    subject: { type: string; label: string };
    changes: ActivityChange[];
};
