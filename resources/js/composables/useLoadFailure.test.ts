import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    attachLoadFailure,
    dismissLoadFailure,
    retryLoad,
    useLoadFailure,
} from './useLoadFailure';

type Handler = (event: {
    detail: Record<string, unknown>;
    preventDefault: () => void;
}) => unknown;

function fakeRouter() {
    const handlers: Record<string, Handler[]> = {};

    return {
        visit: vi.fn(),
        on(event: string, callback: Handler) {
            (handlers[event] ??= []).push(callback);

            return () => {
                handlers[event] = (handlers[event] ?? []).filter(
                    (h) => h !== callback,
                );
            };
        },
        emit(event: string, detail: Record<string, unknown> = {}) {
            const e = { detail, preventDefault: vi.fn() };
            (handlers[event] ?? []).forEach((h) => h(e));

            return e;
        },
        count(event: string) {
            return (handlers[event] ?? []).length;
        },
    };
}

const visit = (url: string, extra: Record<string, unknown> = {}) => ({
    url: new URL(url, 'http://localhost'),
    method: 'get',
    data: {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: [],
    prefetch: false,
    ...extra,
});

describe('useLoadFailure', () => {
    beforeEach(() => dismissLoadFailure());

    it('kegagalan jaringan menyetel gagal; kunjungan baru dan sukses mengosongkan', () => {
        const router = fakeRouter();
        attachLoadFailure(router);
        const { failed } = useLoadFailure();

        router.emit('exception');
        expect(failed.value).toBe(true);

        router.emit('start');
        expect(failed.value).toBe(false);

        router.emit('exception');
        router.emit('success');
        expect(failed.value).toBe(false);
    });

    it('respons non-Inertia menyetel gagal dan mencegah modal bawaan', () => {
        const router = fakeRouter();
        attachLoadFailure(router);

        const event = router.emit('invalid');

        expect(useLoadFailure().failed.value).toBe(true);
        expect(event.preventDefault).toHaveBeenCalledOnce();
    });

    it('coba lagi mengulang kunjungan terakhir, bukan halaman saat ini, dan mengabaikan prefetch', () => {
        const router = fakeRouter();
        attachLoadFailure(router);

        router.emit('before', { visit: visit('/jadwal?filter[period]=lewat') });
        router.emit('before', {
            visit: visit('/jadwal?halaman-lain', { prefetch: true }),
        });
        router.emit('exception');
        retryLoad(router);

        expect(router.visit).toHaveBeenCalledOnce();
        const [url, options] = router.visit.mock.calls[0];
        expect(String(url)).toBe(
            'http://localhost/jadwal?filter[period]=lewat',
        );
        expect(options).toMatchObject({
            method: 'get',
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
        expect(useLoadFailure().failed.value).toBe(false);
    });

    it('coba lagi tanpa kunjungan tercatat tidak melempar galat', () => {
        const router = fakeRouter();

        expect(() => retryLoad(router)).not.toThrow();
    });

    it('tutup mengosongkan dan pembatal melepas pendengar', () => {
        const router = fakeRouter();
        const detach = attachLoadFailure(router);

        router.emit('exception');
        dismissLoadFailure();
        expect(useLoadFailure().failed.value).toBe(false);

        detach();
        expect(router.count('exception')).toBe(0);
        router.emit('exception');
        expect(useLoadFailure().failed.value).toBe(false);
    });
});
