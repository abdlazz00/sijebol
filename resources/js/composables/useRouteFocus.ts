import { router } from '@inertiajs/vue3';
import { nextTick, readonly, ref } from 'vue';

/** Bagian router Inertia yang dipakai; memudahkan pengujian tanpa memasang aplikasi. */
export interface RouterLike {
    on(
        event: string,
        callback: (event: { detail: Record<string, unknown> }) => unknown,
    ): () => void;
}

/** Bagian document yang dipakai. */
interface DocLike {
    title: string;
    getElementById(id: string): { focus(options?: FocusOptions): void } | null;
}

/**
 * Saat pindah ke halaman (komponen) lain, fokus dipindah ke konten utama dan
 * judul halaman diumumkan. Muat awal, partial reload, dan filter pada halaman
 * yang sama tidak menyentuh fokus.
 */
export function createRouteFocus(doc: DocLike = document) {
    const announcement = ref('');
    let last: string | null = null;

    function attach(
        target: RouterLike = router as unknown as RouterLike,
    ): () => void {
        return target.on('navigate', (e) => {
            const page = e.detail.page as { component: string };
            const changed = last !== null && last !== page.component;
            last = page.component;
            if (!changed) return;

            // Head Inertia memperbarui judul dengan debounce 1 ms; beri jeda agar judul sudah baru.
            void nextTick(() =>
                setTimeout(() => {
                    doc.getElementById('konten')?.focus({
                        preventScroll: true,
                    });
                    announcement.value = `Halaman: ${doc.title}`;
                }, 50),
            );
        });
    }

    return { announcement: readonly(announcement), attach };
}

const shared = createRouteFocus();

export const attachRouteFocus = shared.attach;

export function useRouteFocus() {
    return { announcement: shared.announcement };
}
