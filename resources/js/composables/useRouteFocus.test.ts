import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createRouteFocus } from './useRouteFocus';

type Handler = (event: { detail: Record<string, unknown> }) => unknown;

function fakeRouter() {
    const handlers: Handler[] = [];

    return {
        on(_event: string, callback: Handler) {
            handlers.push(callback);

            return () => handlers.splice(handlers.indexOf(callback), 1);
        },
        navigate(component: string) {
            handlers.forEach((h) => h({ detail: { page: { component } } }));
        },
        count: () => handlers.length,
    };
}

function fakeDoc(title = 'Jadwal - SIJEBOL', hasMain = true) {
    const focus = vi.fn();

    return {
        title,
        focus,
        getElementById: vi.fn((id: string) =>
            hasMain && id === 'konten' ? { focus } : null,
        ),
    };
}

describe('useRouteFocus', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('muat awal tidak memindahkan fokus dan tidak mengumumkan apa pun', async () => {
        const doc = fakeDoc();
        const { attach, announcement } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('Jadwal/Index');
        await vi.runAllTimersAsync();

        expect(doc.focus).not.toHaveBeenCalled();
        expect(announcement.value).toBe('');
    });

    it('pindah ke komponen lain memfokuskan konten tanpa gulir dan mengumumkan judul', async () => {
        const doc = fakeDoc('Siswa - SIJEBOL');
        const { attach, announcement } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('Jadwal/Index');
        router.navigate('Siswa/Index');
        await vi.runAllTimersAsync();

        expect(doc.focus).toHaveBeenCalledWith({ preventScroll: true });
        expect(announcement.value).toBe('Halaman: Siswa - SIJEBOL');
    });

    it('komponen yang sama (filter, partial reload) tidak memindahkan fokus', async () => {
        const doc = fakeDoc();
        const { attach } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('Jadwal/Index');
        router.navigate('Jadwal/Index');
        router.navigate('Jadwal/Index');
        await vi.runAllTimersAsync();

        expect(doc.focus).not.toHaveBeenCalled();
    });

    it('tanpa elemen konten tidak melempar galat', async () => {
        const doc = fakeDoc('X', false);
        const { attach } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('A');
        router.navigate('B');

        await expect(vi.runAllTimersAsync()).resolves.not.toThrow();
    });

    it('pelepas menghentikan pemantauan', () => {
        const { attach } = createRouteFocus(fakeDoc());
        const router = fakeRouter();

        const detach = attach(router);
        expect(router.count()).toBe(1);
        detach();
        expect(router.count()).toBe(0);
    });
});
