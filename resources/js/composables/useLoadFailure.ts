import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';

type VisitLike = {
    url: URL;
    method: string;
    data: unknown;
    preserveState?: unknown;
    preserveScroll?: unknown;
    replace?: boolean;
    only?: string[];
    prefetch?: boolean;
};

type EventLike = {
    detail: Record<string, unknown>;
    preventDefault: () => void;
};

/** Bagian router Inertia yang dipakai; memudahkan pengujian tanpa memasang aplikasi. */
export interface RouterLike {
    on(event: string, callback: (event: EventLike) => unknown): () => void;
    visit(url: string | URL, options?: Record<string, unknown>): void;
}

// Status dibagi seluruh halaman: banner di AppLayout membaca instansi yang sama.
const failed = ref(false);
let lastVisit: VisitLike | null = null;

/**
 * Memantau kegagalan jaringan (`exception`) dan respons non-Inertia (`invalid`).
 * Dipasang sekali di app.ts; mengembalikan fungsi pelepas.
 */
export function attachLoadFailure(
    target: RouterLike = router as unknown as RouterLike,
): () => void {
    const offs = [
        target.on('before', (e) => {
            const visit = e.detail.visit as VisitLike;
            if (!visit.prefetch) lastVisit = visit;
        }),
        target.on('start', () => {
            failed.value = false;
        }),
        target.on('success', () => {
            failed.value = false;
        }),
        target.on('exception', () => {
            failed.value = true;
        }),
        target.on('invalid', (e) => {
            // Mencegah modal HTML mentah bawaan Inertia.
            e.preventDefault();
            failed.value = true;
        }),
    ];

    return () => offs.forEach((off) => off());
}

/** Mengulang kunjungan terakhir (bukan memuat ulang halaman saat ini, yang bisa berbeda). */
export function retryLoad(
    target: RouterLike = router as unknown as RouterLike,
): void {
    const visit = lastVisit;
    failed.value = false;
    if (!visit) return;

    target.visit(visit.url, {
        method: visit.method,
        data: visit.data,
        preserveState: visit.preserveState,
        preserveScroll: visit.preserveScroll,
        replace: visit.replace,
        only: visit.only,
    });
}

export function dismissLoadFailure(): void {
    failed.value = false;
}

export function useLoadFailure() {
    return { failed: readonly(failed) };
}
