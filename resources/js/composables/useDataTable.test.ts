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

describe('alamat daftar di halaman', () => {
    // Alamat tertulis ('/jadwal') tidak ikut berubah saat rute diganti nama dan membuat filter berakhir di 404;
    // halaman harus memakai nama rute: useDataTable(route('schedules.index'), ...).
    it('tidak ada halaman yang memberi useDataTable alamat tertulis', () => {
        const pages = import.meta.glob('/resources/js/Pages/**/*.vue', {
            query: '?raw',
            import: 'default',
            eager: true,
        }) as Record<string, string>;

        const literal = Object.entries(pages)
            .filter(([, source]) => /useDataTable\(\s*['"`]\//.test(source))
            .map(([file]) => file.replace('/resources/js/Pages/', ''));

        expect(literal).toEqual([]);
    });
});

describe('alamat lama di sumber frontend', () => {
    // Rute sudah berbahasa Inggris (/schedules, /students, ...). Alamat tertulis berawalan nama lama
    // selalu berakhir di 404; pakai nama rute: route('schedules.show', id).
    it('tidak ada alamat tertulis berawalan nama rute lama', () => {
        const sources = import.meta.glob(
            ['/resources/js/**/*.vue', '/resources/js/**/*.ts', '!/resources/js/**/*.test.ts'],
            { query: '?raw', import: 'default', eager: true },
        ) as Record<string, string>;
        const lama =
            /['"`]\/(jadwal|siswa|sekolah|pengguna|verifikasi|laporan|notifikasi|saya|beranda|profil|log|rekaman|tiket|peran)(?=[/'"`?$])/;

        const ditemukan = Object.entries(sources).flatMap(([file, source]) =>
            source
                .split(/\r?\n/)
                .flatMap((line, i) => (lama.test(line) ? [`${file.replace('/resources/js/', '')}:${i + 1}`] : [])),
        );

        expect(ditemukan).toEqual([]);
    });
});
