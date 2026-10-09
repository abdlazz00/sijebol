import type { ImportStatus } from '@/types/student';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { useImportStatus } from './useImportStatus';

const result = (over: Partial<ImportStatus> = {}): ImportStatus => ({
    id: 5,
    status: 'antri',
    imported: 0,
    failed: [],
    error: null,
    ...over,
});

function setup(fetcher: (id: number) => Promise<ImportStatus>) {
    const scope = effectScope();
    const api = scope.run(() => useImportStatus(fetcher))!;

    return { api, scope };
}

describe('useImportStatus', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('polling tiap 2 detik sampai selesai lalu berhenti', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockResolvedValueOnce(result({ status: 'berjalan' }))
            .mockResolvedValue(result({ status: 'selesai', imported: 3 }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(0);
        expect(api.status.value?.status).toBe('berjalan');

        await vi.advanceTimersByTimeAsync(2000);
        expect(api.status.value).toMatchObject({
            status: 'selesai',
            imported: 3,
        });

        const calls = fetcher.mock.calls.length;
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetcher.mock.calls.length).toBe(calls);
        scope.stop();
    });

    it('berhenti saat gagal', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockResolvedValue(result({ status: 'gagal', error: 'x' }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(0);
        const calls = fetcher.mock.calls.length;
        await vi.advanceTimersByTimeAsync(6000);

        expect(api.status.value?.status).toBe('gagal');
        expect(fetcher.mock.calls.length).toBe(calls);
        scope.stop();
    });

    it('menandai stalled bila masih antri lebih dari 20 detik', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockResolvedValue(result({ status: 'antri' }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(18000);
        expect(api.stalled.value).toBe(false);

        await vi.advanceTimersByTimeAsync(4000);
        expect(api.stalled.value).toBe(true);
        scope.stop();
    });

    it('show menampilkan hasil yang sudah selesai tanpa polling', async () => {
        const fetcher = vi.fn<(id: number) => Promise<ImportStatus>>();
        const { api, scope } = setup(fetcher);

        api.show(result({ status: 'selesai', imported: 2 }));
        await vi.advanceTimersByTimeAsync(10000);

        expect(api.status.value?.imported).toBe(2);
        expect(fetcher).not.toHaveBeenCalled();
        scope.stop();
    });

    it('kegagalan jaringan sesaat tidak menghentikan polling', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue(result({ status: 'selesai' }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(0);
        await vi.advanceTimersByTimeAsync(2000);

        expect(api.status.value?.status).toBe('selesai');
        scope.stop();
    });
});
