import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ router: { get: vi.fn() } }));

import { router } from '@inertiajs/vue3';
import { useDataTable } from './useDataTable';

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
            { search: 'budi' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('reset mengembalikan nilai awal lalu memuat ulang', () => {
        const { filters, reset } = useDataTable('/pengguna', { search: '' });
        filters.search = 'x';
        reset();
        expect(filters.search).toBe('');
        expect(router.get).toHaveBeenCalledWith(
            '/pengguna',
            {},
            expect.any(Object),
        );
    });
});
