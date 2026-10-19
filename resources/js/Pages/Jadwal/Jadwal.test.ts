import Form from '@/Pages/Jadwal/Form.vue';
import Index from '@/Pages/Jadwal/Index.vue';
import Rekaman from '@/Pages/Jadwal/Rekaman.vue';
import Show from '@/Pages/Jadwal/Show.vue';
import type {
    ParticipantRow,
    ScheduleDetail,
    ScheduleRow,
} from '@/types/schedule';
import { config, flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post, put, routerPost, routerDelete, permissions } = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    routerPost: vi.fn(),
    routerDelete: vi.fn(),
    permissions: [] as string[],
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        router: { get: vi.fn(), post: routerPost, delete: routerDelete },
        usePage: () => ({ props: { auth: { permissions } }, url: '/jadwal' }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post,
                put,
                clearErrors: vi.fn(),
            }),
    };
});
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/lib/focusFirstInvalid', () => ({ focusFirstInvalid: vi.fn() }));
vi.mock('@/Components/shared/ConfirmDialog.vue', () => ({
    default: {
        props: ['open', 'title', 'description', 'confirmDisabled'],
        emits: ['update:open', 'confirm'],
        template:
            '<div v-if="open" data-testid="confirm"><p>{{ title }}</p><p>{{ description }}</p><slot /><button data-testid="ok" :disabled="confirmDisabled" @click="$emit(\'confirm\')">ok</button></div>',
    },
}));
vi.mock('@/Components/shared/RowActions.vue', () => ({
    default: {
        props: ['label'],
        template: '<div data-testid="more"><slot /></div>',
    },
}));
vi.mock('@/Components/ui/dropdown-menu', () => ({
    DropdownMenuItem: {
        emits: ['select'],
        template:
            '<button type="button" @click="$emit(\'select\')"><slot /></button>',
    },
}));

const detail: ScheduleDetail = {
    id: 5,
    date: '2026-10-20',
    start_time: '08:00',
    end_time: '12:00',
    location: 'Aula SMPN 1',
    school: 'SMPN 1 Sungai Beduk',
    quota: 30,
    taken: 12,
    cancelled: false,
    is_past: false,
    last_registration_day: '2026-10-18',
    created_by: 'Admin',
};
const peserta = (
    id: number,
    status: ParticipantRow['status'],
): ParticipantRow => ({
    id,
    student: { id: id + 100, name: `Siswa ${id}`, nisn: `00000000${id}` },
    status,
});

beforeEach(() => {
    const route = (name: string, id?: number | number[]) =>
        `/${name}${id !== undefined ? `/${[id].flat().join('/')}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    post.mockReset();
    put.mockReset();
    routerPost.mockReset();
    routerDelete.mockReset();
    permissions.length = 0;
});

describe('Jadwal/Index', () => {
    const row: ScheduleRow = {
        id: 5,
        date: '2026-10-20',
        start_time: '08:00',
        end_time: '12:00',
        location: 'Aula SMPN 1',
        school: 'SMPN 1',
        is_past: false,
        cancelled: false,
        quota: 30,
        taken: 12,
        participants_count: 14,
        counts: { terdaftar: 12, ditolak: 2 },
    };
    const paginator = (data: ScheduleRow[]) => ({
        data,
        links: [],
        current_page: 1,
        last_page: 1,
        from: 1,
        to: data.length,
        total: data.length,
        per_page: 20,
        path: '/jadwal',
        first_page_url: '',
        last_page_url: '',
        next_page_url: null,
        prev_page_url: null,
    });

    it('menampilkan jam, kuota "N dari M kursi", dan ringkasan status berteks', () => {
        const w = mount(Index, {
            props: { schedules: paginator([row]), filters: {}, schools: null },
        });

        expect(w.text()).toContain('08.00–12.00');
        expect(w.text()).toContain('12 dari 30 kursi');
        expect(w.text()).toContain('Terdaftar 12');
        expect(w.text()).toContain('Ditolak 2');
    });

    it('sesi dibatalkan dan sesi lewat diberi tanda teks', () => {
        const w = mount(Index, {
            props: {
                schedules: paginator([
                    { ...row, cancelled: true },
                    { ...row, id: 6, is_past: true },
                ]),
                filters: {},
                schools: null,
            },
        });

        expect(w.text()).toContain('Dibatalkan');
        expect(w.text()).toContain('Sudah lewat');
    });

    it('memakai istilah "Sesi rekaman" dan tombol Buat sesi bagi pemegang izin', () => {
        permissions.push('jadwal.buat');
        const w = mount(Index, {
            props: { schedules: paginator([]), filters: {}, schools: null },
        });

        expect(w.text()).toContain('Sesi rekaman');
        expect(w.text()).toContain('Buat sesi');
        expect(w.text()).toContain('Belum ada sesi');
    });
});

describe('Jadwal/Form', () => {
    it('membuat sesi: jam selesai harus setelah jam mulai dan kuota 1–1000', async () => {
        const w = mount(Form, { props: { schedule: null, schools: null } });
        const [mulai, selesai] = w.findAll('input[type="time"]');
        await w.find('input[type="date"]').setValue('2099-01-01');
        await w.find('input[placeholder^="Contoh"]').setValue('Aula');
        await mulai.setValue('10:00');
        await selesai.setValue('09:00');
        await w.find('input[inputmode="numeric"]').setValue('0');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(w.text()).toContain('Jam selesai harus setelah jam mulai.');
        expect(w.text()).toContain('Kuota harus berupa angka 1–1000.');
        expect(post).not.toHaveBeenCalled();
    });

    it('data valid dikirim lewat POST', async () => {
        const w = mount(Form, { props: { schedule: null, schools: null } });
        const [mulai, selesai] = w.findAll('input[type="time"]');
        await w.find('input[type="date"]').setValue('2099-01-01');
        await w.find('input[placeholder^="Contoh"]').setValue('Aula');
        await mulai.setValue('08:00');
        await selesai.setValue('11:00');
        await w.find('input[inputmode="numeric"]').setValue('25');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(post).toHaveBeenCalledOnce();
    });

    it('ubah sesi berpendaftar: tanggal tampil sebagai teks baca-saja dengan alasan', () => {
        const w = mount(Form, {
            props: {
                schedule: {
                    id: 5,
                    date: '2026-10-20',
                    start_time: '08:00',
                    end_time: '12:00',
                    location: 'Aula',
                    quota: 30,
                    has_participants: true,
                },
                schools: null,
            },
        });

        expect(w.find('input[type="date"]').exists()).toBe(false);
        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('tidak dapat diubah');
    });
});

describe('Jadwal/Show', () => {
    const base = { schedule: detail, participants: [] as ParticipantRow[] };

    it('menampilkan peserta semua status dengan badge berteks dan rincian kursi', () => {
        const w = mount(Show, {
            props: {
                ...base,
                participants: [
                    peserta(1, 'terdaftar'),
                    peserta(2, 'menunggu_verifikasi'),
                    peserta(3, 'ditolak'),
                ],
            },
        });

        expect(w.text()).toContain('Terdaftar');
        expect(w.text()).toContain('Menunggu verifikasi');
        expect(w.text()).toContain('Ditolak');
        expect(w.text()).toContain('12 dari 30 kursi');
        expect(w.text()).toContain('1 terdaftar');
        expect(w.text()).toContain('1 menunggu verifikasi');
        expect(w.text()).not.toContain('Tambah siswa');
        expect(w.text()).not.toContain('Pindahkan');
        expect(w.text()).not.toContain('Keluarkan');
    });

    it('tautan Pindai tiket hanya untuk pemegang rekaman.pindai dan sesi yang tidak dibatalkan', () => {
        const tanpa = mount(Show, { props: base });
        expect(tanpa.text()).not.toContain('Pindai tiket');

        permissions.push('rekaman.pindai');
        const dengan = mount(Show, { props: base });
        expect(dengan.text()).toContain('Pindai tiket');
        expect(
            dengan
                .findAll('a')
                .some((a) => a.attributes('href') === '/jadwal.pindai.show/5'),
        ).toBe(true);
        expect(
            mount(Show, {
                props: { ...base, schedule: { ...detail, cancelled: true } },
            }).text(),
        ).not.toContain('Pindai tiket');
    });

    it('tombol Batalkan sesi hanya untuk pemegang izin ubah dan sesi yang masih berlaku', () => {
        const tanpa = mount(Show, { props: base });
        expect(tanpa.text()).not.toContain('Batalkan sesi');

        permissions.push('jadwal.ubah');
        expect(mount(Show, { props: base }).text()).toContain('Batalkan sesi');
        expect(
            mount(Show, {
                props: { ...base, schedule: { ...detail, cancelled: true } },
            }).text(),
        ).not.toContain('Batalkan sesi');
        expect(
            mount(Show, {
                props: { ...base, schedule: { ...detail, is_past: true } },
            }).text(),
        ).not.toContain('Batalkan sesi');
    });

    it('dialog pembatalan menyebut jumlah pendaftar terdampak dan mewajibkan catatan', async () => {
        permissions.push('jadwal.ubah');
        const w = mount(Show, {
            props: {
                ...base,
                participants: [
                    peserta(1, 'terdaftar'),
                    peserta(2, 'perlu_perbaikan'),
                    peserta(3, 'ditolak'),
                    peserta(4, 'sudah_rekaman'),
                ],
            },
        });
        await w
            .findAll('button')
            .find((b) => b.text().includes('Batalkan sesi'))!
            .trigger('click');

        const dialog = w.find('[data-testid="confirm"]');
        expect(dialog.text()).toContain('2 pendaftaran akan dibatalkan');
        expect(
            dialog.find('[data-testid="ok"]').attributes('disabled'),
        ).toBeDefined();

        await dialog.find('textarea').setValue('abc');
        expect(
            dialog.find('[data-testid="ok"]').attributes('disabled'),
        ).toBeDefined();

        await dialog.find('textarea').setValue('Gedung dipakai ujian.');
        await dialog.find('[data-testid="ok"]').trigger('click');

        expect(routerPost).toHaveBeenCalledWith(
            '/jadwal.batalkan/5',
            { note: 'Gedung dipakai ujian.' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('Hapus hanya untuk sesi tanpa peserta dan berada di menu aksi, terpisah dari Batalkan', async () => {
        permissions.push('jadwal.ubah');
        const kosong = mount(Show, { props: base });
        const menu = kosong.find('[data-testid="more"]');
        expect(menu.text()).toContain('Hapus sesi');
        expect(
            kosong
                .findAll('button')
                .filter((b) => b.text().includes('Hapus sesi')),
        ).toHaveLength(1);
        await menu.find('button').trigger('click');
        await kosong.find('[data-testid="ok"]').trigger('click');
        expect(routerDelete).toHaveBeenCalledWith('/jadwal.destroy/5');

        const berisi = mount(Show, {
            props: { ...base, participants: [peserta(1, 'ditolak')] },
        });
        expect(berisi.text()).not.toContain('Hapus sesi');
    });
});

describe('Jadwal/Show — pembatalan pendaftaran oleh admin', () => {
    const dua = [
        { ...peserta(1, 'terdaftar'), can_cancel: true },
        { ...peserta(2, 'ditolak'), can_cancel: false },
    ];

    it('Batalkan pendaftaran ada di menu aksi baris, hanya untuk can_cancel dan pemegang verifikasi.putuskan', () => {
        const tanpaIzin = mount(Show, {
            props: { schedule: detail, participants: dua },
        });
        expect(tanpaIzin.text()).not.toContain('Batalkan pendaftaran');

        permissions.push('verifikasi.putuskan');
        const w = mount(Show, {
            props: { schedule: detail, participants: dua },
        });
        const item = w
            .findAll('button')
            .filter((b) => b.text().includes('Batalkan pendaftaran'));

        expect(item).toHaveLength(1);
        expect(w.find('[data-testid="more"]').exists()).toBe(true);
    });

    it('dialog menyebut siswa dan kursi dilepas, mewajibkan catatan, lalu mengirim ke rute pembatalan peserta', async () => {
        permissions.push('verifikasi.putuskan');
        const w = mount(Show, {
            props: { schedule: detail, participants: dua },
        });
        await w
            .findAll('button')
            .find((b) => b.text().includes('Batalkan pendaftaran'))!
            .trigger('click');

        const dialog = w.find('[data-testid="confirm"]');
        expect(dialog.text()).toContain('Siswa 1');
        expect(dialog.text()).toContain('kursi');
        expect(
            dialog.find('[data-testid="ok"]').attributes('disabled'),
        ).toBeDefined();

        await dialog.find('textarea').setValue('abc');
        expect(
            dialog.find('[data-testid="ok"]').attributes('disabled'),
        ).toBeDefined();

        await dialog
            .find('textarea')
            .setValue('Data ganda dengan pendaftaran lain.');
        await dialog.find('[data-testid="ok"]').trigger('click');

        expect(routerPost).toHaveBeenCalledWith(
            '/jadwal.peserta.batalkan/5/1',
            { note: 'Data ganda dengan pendaftaran lain.' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

describe('Jadwal/Rekaman', () => {
    it('menautkan ke halaman pindai bagi pemegang rekaman.pindai', () => {
        const props = {
            schedule: { ...detail, date: '2026-10-19' },
            entries: [],
            marked: [],
            can_mark: true,
        };
        permissions.push('rekaman.lihat');
        expect(mount(Rekaman, { props }).text()).not.toContain('Pindai tiket');

        permissions.push('rekaman.pindai');
        expect(mount(Rekaman, { props }).text()).toContain('Pindai tiket');
    });

    it('daftar ceklis memakai istilah Terdaftar', () => {
        permissions.push('rekaman.lihat', 'rekaman.tandai');
        const w = mount(Rekaman, {
            props: {
                schedule: { ...detail, date: '2026-10-19' },
                entries: [{ id: 1, name: 'Ani', nisn: '0000000001' }],
                marked: [],
                can_mark: true,
            },
        });

        expect(w.text()).toContain('Siswa terdaftar');
        expect(w.text()).not.toContain('terjadwal');
        expect(w.text()).toContain('Ani');
    });
});
