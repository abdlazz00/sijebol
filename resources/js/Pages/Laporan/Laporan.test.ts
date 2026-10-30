import Index from '@/Pages/Laporan/Index.vue';
import type { Recap, ReportFilters, SessionRecap } from '@/types/report';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
    usePage: () => ({ props: { errors: {}, auth: { permissions: [] } } }),
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

const recap: Recap = {
    columns: [{ key: 'terdaftar', label: 'Terdaftar' }],
    rows: [{ school: 'SMPN A', counts: { terdaftar: 2 }, total: 2 }],
    totals: { counts: { terdaftar: 2 }, total: 2 },
};
const baris = (over: Partial<SessionRecap['rows'][number]> = {}) => ({
    id: 1,
    date: '2026-10-20',
    start_time: '08:00',
    end_time: '12:00',
    school: 'SMPN A',
    location: 'Aula SMPN A',
    quota: 30,
    taken: 5,
    present: 3,
    not_recorded: 1,
    absent: 1,
    attendance: 80 as number | null,
    ...over,
});
const sessionRecap = (rows = [baris()]): SessionRecap => ({
    rows,
    totals: {
        quota: 30,
        taken: 5,
        present: 3,
        not_recorded: 1,
        absent: 1,
        attendance: 80,
    },
});
const filters: ReportFilters = { dari: '2026-10-01', sampai: '2026-10-31' };

beforeEach(() => {
    const route = (name: string, params?: Record<string, string>) =>
        `/${name}${params ? `?${new URLSearchParams(params)}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

describe('Laporan/Index — kehadiran per sesi', () => {
    it('menampilkan tabel kedua dengan keterangan tanggal sesi dan baris total', () => {
        const w = mount(Index, {
            props: { recap, session_recap: sessionRecap(), filters },
        });

        expect(w.text()).toContain('Kehadiran per sesi');
        expect(w.text()).toContain('menurut tanggal sesi');
        expect(w.text()).toContain('menurut tanggal pendaftaran');
        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('08.00–12.00');
        expect(w.text()).toContain('Aula SMPN A');
        expect(w.text()).toContain('80%');
        const total = w
            .findAll('tr')
            .find(
                (tr) =>
                    tr.text().startsWith('Total') && tr.text().includes('80%'),
            );
        expect(total).toBeDefined();
    });

    it('kehadiran kosong ditampilkan sebagai tanda strip, bukan 0%', () => {
        const w = mount(Index, {
            props: {
                recap,
                session_recap: sessionRecap([baris({ attendance: null })]),
                filters,
            },
        });

        expect(w.text()).toContain('—');
        expect(w.text()).not.toMatch(/(?<!\d)0%/);
    });

    it('ekspor kedua mengikuti filter yang diterapkan', () => {
        const w = mount(Index, {
            props: { recap, session_recap: sessionRecap(), filters },
        });

        const hrefs = w.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).toContain(
            '/reports.export-sessions?dari=2026-10-01&sampai=2026-10-31',
        );
        expect(hrefs).toContain(
            '/reports.export?dari=2026-10-01&sampai=2026-10-31',
        );
    });

    it('keadaan kosong bila tidak ada sesi pada rentang', () => {
        const w = mount(Index, {
            props: {
                recap,
                session_recap: {
                    rows: [],
                    totals: {
                        quota: 0,
                        taken: 0,
                        present: 0,
                        not_recorded: 0,
                        absent: 0,
                        attendance: null,
                    },
                },
                filters,
            },
        });

        expect(w.text()).toContain('Tidak ada sesi pada rentang ini');
    });
});
