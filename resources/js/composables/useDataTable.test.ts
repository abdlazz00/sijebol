import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ router: { get: vi.fn() } }));

import { router } from '@inertiajs/vue3';
import { useDataTable } from './useDataTable';

const baseOptions = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
};

describe('useDataTable', () => {
    beforeEach(() => vi.clearAllMocks());

    it('mengirim filter tanpa nilai kosong', () => {
        const { filters, apply } = useDataTable('/pengguna', {
            search: '',
            role: null,
        });
        filters.search = 'budi';
        apply();
        expect(router.get).toHaveBeenCalledWith(
            '/pengguna',
            { filter: { search: 'budi' } },
            expect.objectContaining(baseOptions),
        );
    });

    it('reset mengosongkan semua filter lalu memuat ulang', () => {
        const { filters, reset } = useDataTable('/pengguna', {
            search: 'awal',
        });
        filters.search = 'x';
        reset();
        expect(filters.search).toBe('');
        expect(router.get).toHaveBeenCalledWith(
            '/pengguna',
            {},
            expect.objectContaining(baseOptions),
        );
    });

    it('loading menyala saat permintaan berjalan dan padam saat selesai', () => {
        const { apply, loading } = useDataTable('/pengguna');
        apply();
        const options = vi.mocked(router.get).mock.calls[0][2] as {
            onStart: () => void;
            onFinish: () => void;
        };

        expect(loading.value).toBe(false);
        options.onStart();
        expect(loading.value).toBe(true);
        options.onFinish();
        expect(loading.value).toBe(false);
    });
});
