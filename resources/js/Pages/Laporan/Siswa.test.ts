import Siswa from '@/Pages/Laporan/Siswa.vue';
import type { Paginator } from '@/types/access';
import type { StudentRecordGroup, StudentRecordRow } from '@/types/report';
import { config, flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { get },
    usePage: () => ({ props: { errors: {} } }),
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

const baris = (o: Partial<StudentRecordRow>): StudentRecordRow => ({
    id: 1,
    name: 'Aziz',
    nisn: '0081234567',
    school: 'SMAN 1',
    group: 'langsung',
    session_date: '2026-10-20',
    eligible_from: '2026-10-20',
    reason: null,
    ...o,
});
const rows = [
    baris({}),
    baris({
        id: 2,
        name: 'Budi',
        group: 'menunggu',
        eligible_from: '2026-10-21',
    }),
    baris({
        id: 3,
        name: 'Citra',
        group: 'belum',
        session_date: null,
        reason: 'Belum mendaftar sesi',
    }),
];
const groups: StudentRecordGroup[] = [
    { key: 'langsung', label: 'Langsung terbit', count: 1 },
    { key: 'menunggu', label: 'Menunggu genap 17', count: 1 },
    { key: 'belum', label: 'Belum rekaman', count: 1 },
];
const paginator = (data: StudentRecordRow[]): Paginator<StudentRecordRow> => ({
    data,
    current_page: 1,
    last_page: 1,
    from: data.length ? 1 : null,
    to: data.length || null,
    total: data.length,
    per_page: 25,
    links: [],
});
const props = (over: Record<string, unknown> = {}) => ({
    records: paginator(rows),
    groups,
    filters: {},
    schools: [{ id: 1, name: 'SMAN 1' }],
    schedules: [{ id: 5, label: '2026-10-20 · SMAN 1' }],
    ...over,
});

beforeEach(() => {
    const route = (name: string, params?: unknown) =>
        params ? `/${name}?${JSON.stringify(params)}` : `/${name}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    get.mockReset();
});

describe('Laporan/Siswa', () => {
    it('menampilkan tiga kelompok dengan jumlahnya', () => {
        const w = mount(Siswa, { props: props() });
        const tombol = w.findAll('button[aria-pressed]');

        expect(tombol).toHaveLength(3);
        expect(tombol[0].text()).toContain('Langsung terbit');
        expect(tombol[1].text()).toContain('Menunggu genap 17');
        expect(tombol[2].text()).toContain('Belum rekaman');
        expect(tombol[2].text()).toContain('1');
    });

    it('mengklik kelompok menyaring, mengklik lagi melepasnya', async () => {
        const w = mount(Siswa, { props: props() });

        await w.findAll('button[aria-pressed]')[2].trigger('click');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith(
            '/reports.students',
            { filter: { group: 'belum' } },
            expect.anything(),
        );

        await w.findAll('button[aria-pressed]')[2].trigger('click');
        await flushPromises();
        expect(get).toHaveBeenLastCalledWith(
            '/reports.students',
            {},
            expect.anything(),
        );
    });

    it('kelompok yang sedang disaring ditandai aria-pressed', () => {
        const w = mount(Siswa, {
            props: props({ filters: { group: 'menunggu' } }),
        });

        expect(
            w
                .findAll('button[aria-pressed]')
                .map((b) => b.attributes('aria-pressed')),
        ).toEqual(['false', 'true', 'false']);
    });

    it('baris: nama, NISN, sekolah, tanggal rekaman, KTP-el, dan alasan; tanpa NIK', () => {
        const teks = mount(Siswa, { props: props() }).text();

        expect(teks).toContain('Aziz');
        expect(teks).toContain('NISN 0081234567');
        expect(teks).toContain('SMAN 1');
        expect(teks).toContain('KTP-el langsung');
        expect(teks).toContain('Terbit mulai');
        expect(teks).toContain('Belum mendaftar sesi');
        expect(teks).not.toMatch(/\d{4}\*{8}\d{4}/);
    });

    it('kolom Sekolah dan filter sekolah hanya ada bila schools tidak null', () => {
        const staf = mount(Siswa, { props: props() });
        const operator = mount(Siswa, { props: props({ schools: null }) });

        expect(staf.findAll('th').map((th) => th.text())).toContain('Sekolah');
        expect(operator.findAll('th').map((th) => th.text())).not.toContain(
            'Sekolah',
        );
    });

    it('tautan Unduh Excel membawa filter yang berlaku', () => {
        const w = mount(Siswa, {
            props: props({ filters: { group: 'belum' } }),
        });
        const href = w.find('a[href*="reports.students.export"]');

        expect(href.exists()).toBe(true);
        expect(href.attributes('href')).toContain('belum');
    });

    it('menjelaskan bahwa terbit berarti memenuhi syarat umur', () => {
        expect(mount(Siswa, { props: props() }).text()).toContain('pencetakan');
    });

    it('keadaan kosong menampilkan pesan dan Reset filter bila ada filter', () => {
        const w = mount(Siswa, {
            props: props({
                records: paginator([]),
                filters: { search: 'zzz' },
            }),
        });

        expect(w.text()).toContain('Tidak ada hasil');
        expect(w.text()).toContain('Reset filter');
    });
});
