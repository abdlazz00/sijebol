import type { NotificationRow } from '@/types/notification';
import { router } from '@inertiajs/vue3';

/** Aksi notifikasi yang dipakai bersama oleh lonceng dan halaman daftar. */
export function useNotificationActions() {
    const keep = { preserveScroll: true, preserveState: true } as const;

    function markRead(id: string) {
        router.post(route('notifikasi.read', id), {}, keep);
    }

    // Tandai dibaca dulu, baru pindah: dua visit sekaligus akan saling membatalkan.
    function open(notification: NotificationRow) {
        if (notification.read_at !== null)
            return router.visit(notification.url);

        router.post(
            route('notifikasi.read', notification.id),
            {},
            {
                ...keep,
                onFinish: () => router.visit(notification.url),
            },
        );
    }

    function markAllRead() {
        router.post(route('notifikasi.read-all'), {}, keep);
    }

    return { markRead, open, markAllRead };
}
