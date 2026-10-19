import StatusTimeline from '@/Components/shared/StatusTimeline.vue';
import Index from '@/Pages/Verifikasi/Index.vue';
import Show from '@/Pages/Verifikasi/Show.vue';
import type { EntryRow, QueueRow, SessionBrief } from '@/types/registration';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post, permissions } = vi.hoisted(() => ({
    post: vi.fn(),
    permissions: [] as string[],
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        router: { get: vi.fn(), post: vi.fn() },
        usePage: () => ({
            props: { auth: { permissions } },
            url: '/verifikasi',
        }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post,
                clearErrors: vi.fn(),
            }),
    };
});
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/lib/focusFirstInvalid', () => ({ focusFirstInvalid: vi.fn() }));
vi.mock('@/Components/shared/DocumentPreviewDialog.vue', () => ({
    default: { template: '<div />' },
}));

const sesi: SessionBrief = {
    id: 3,
    date: '2026-10-20',
    start_time: '08:00',
    end_time: '12:00',
    location: 'Aula SMPN 1',
};
const baris: QueueRow = {
    id: 11,
    student: { id: 7, name: 'Budi Santoso', nisn: '0012345678' },
    school: 'SMPN 1',
    session: sesi,
    since: '2026-10-01T10:00:00+07:00',
};
const paginator = (data: QueueRow[]) => ({
    data,
    links: [],
    current_page: 1,
    last_page: 1,
    from: 1,
    to: data.length,
    total: data.length,
    per_page: 20,
    path: '/verifikasi',
    first_page_url: '',
    last_page_url: '',
    next_page_url: null,
    prev_page_url: null,
});

beforeEach(() => {
    const route = (name: string, id?: number) =>
        `/${name}${id !== undefined ? `/${id}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    post.mockReset();
    permissions.length = 0;
});

describe('Verifikasi/Index', () => {
    it('baris antrean menampilkan sesi (hari, jam, lokasi) menggantikan judul pendaftaran', () => {
        const w = mount(Index, {
            props: {
                queue: paginator([baris]),
                filters: {},
                schools: null,
                sessions: [],
            },
        });

        expect(w.text()).toContain('Sesi');
        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('08.00–12.00');
        expect(w.text()).toContain('Aula SMPN 1');
        expect(w.text()).toContain('Budi Santoso');
    });

    it('filter Sesi tampil bila ada sesi yang punya antrean', () => {
        const dengan = mount(Index, {
            props: {
                queue: paginator([baris]),
                filters: {},
                schools: null,
                sessions: [{ ...sesi, school: 'SMPN 1' }],
            },
        });
        const tanpa = mount(Index, {
            props: {
                queue: paginator([]),
                filters: {},
                schools: null,
                sessions: [],
            },
        });

        expect(dengan.text()).toContain('Sesi rekaman');
        expect(tanpa.text()).not.toContain('Semua sesi');
    });
});

describe('Verifikasi/Show', () => {
    const entry: EntryRow & { session: SessionBrief } = {
        id: 11,
        student: baris.student,
        status: 'menunggu_verifikasi',
        note: null,
        decided_at: null,
        history: [
            {
                from: null,
                to: 'menunggu_verifikasi',
                by: 'Budi Santoso',
                note: null,
                at: '2026-10-01T10:00:00+07:00',
            },
        ],
        session: sesi,
    };
    const props = {
        entry,
        student: {
            id: 7,
            name: 'Budi Santoso',
            nisn: '0012345678',
            nik: '3201********1234',
            birth_date: '2009-05-01',
            age: 17,
            gender: 'L' as const,
            school: 'SMPN 1',
        },
        documents: [],
        eligibility: { eligible: true, reasons: [] },
    };

    it('menampilkan sesi pendaftaran dan riwayat awal tanpa status asal', () => {
        const w = mount(Show, { props });

        expect(w.text()).toContain('Sesi');
        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('Aula SMPN 1');
        expect(w.text()).toContain('Mendaftar');
        expect(w.text()).not.toContain('undefined');
    });

    it('keputusan hanya untuk pemegang izin dan status menunggu verifikasi, dengan keterangan baru', () => {
        const tanpa = mount(Show, { props });
        expect(tanpa.text()).not.toContain('Simpan keputusan');

        permissions.push('verifikasi.putuskan');
        const dengan = mount(Show, { props });
        expect(dengan.text()).toContain('Simpan keputusan');
        expect(dengan.text()).toContain('Siswa terdaftar di sesi ini.');
        expect(dengan.text()).toContain('Kursi dilepas');

        const sudah = mount(Show, {
            props: { ...props, entry: { ...entry, status: 'terdaftar' } },
        });
        expect(sudah.text()).not.toContain('Simpan keputusan');
    });
});

describe('StatusTimeline', () => {
    it('riwayat tanpa status asal tampil sebagai "Mendaftar"', () => {
        const w = mount(StatusTimeline, {
            props: {
                items: [
                    {
                        from: null,
                        to: 'menunggu_verifikasi',
                        by: 'Budi',
                        note: null,
                        at: '2026-10-01T10:00:00+07:00',
                    },
                    {
                        from: 'menunggu_verifikasi',
                        to: 'terdaftar',
                        by: 'Petugas',
                        note: 'Lengkap',
                        at: '2026-10-02T10:00:00+07:00',
                    },
                ],
            },
        });

        expect(w.text()).toContain('Mendaftar');
        expect(w.text()).toMatch(/Menunggu verifikasi →\s+Terdaftar/);
    });
});
