import Sesi from '@/Pages/Saya/Sesi.vue';
import type { OpenSchedule, SelfEntry } from '@/types/registration';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { routerPost, pageProps } = vi.hoisted(() => ({
    routerPost: vi.fn(),
    pageProps: {
        flash: {} as { error?: string | null },
        auth: { permissions: [] as string[] },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: routerPost },
    usePage: () => ({ props: pageProps }),
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/Components/shared/ConfirmDialog.vue', () => ({
    default: {
        props: ['open', 'title', 'description'],
        emits: ['update:open', 'confirm'],
        template:
            '<div v-if="open" data-testid="confirm"><p>{{ title }}</p><p>{{ description }}</p><button data-testid="ok" @click="$emit(\'confirm\')">ok</button></div>',
    },
}));

const sesi = (over: Partial<OpenSchedule> = {}): OpenSchedule => ({
    id: 1,
    date: '2026-10-20',
    start_time: '08:00',
    end_time: '12:00',
    location: 'Aula SMPN 1',
    quota: 30,
    taken: 12,
    full: false,
    eligible: true,
    reasons: [],
    ...over,
});
const entry = (over: Partial<SelfEntry> = {}): SelfEntry => ({
    id: 9,
    status: 'menunggu_verifikasi',
    note: null,
    can_cancel: true,
    can_resubmit: false,
    schedule: {
        id: 1,
        date: '2026-10-20',
        start_time: '08:00',
        end_time: '12:00',
        location: 'Aula SMPN 1',
    },
    ...over,
});
const base = {
    ready: true,
    close_days: 2,
    entry: null as SelfEntry | null,
    schedules: [] as OpenSchedule[],
};

beforeEach(() => {
    const route = (name: string, id?: number) =>
        `/${name}${id !== undefined ? `/${id}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    routerPost.mockReset();
    pageProps.flash = {};
});

describe('Saya/Sesi — daftar sesi', () => {
    it('data belum siap: banner dan tautan ke Data saya, tanpa daftar sesi', () => {
        const w = mount(Sesi, {
            props: { ...base, ready: false, schedules: [sesi()] },
        });

        expect(w.text()).toContain('Lengkapi data diri dan dokumen dulu');
        expect(w.find('a[href="/saya.show"]').exists()).toBe(true);
        expect(w.text()).not.toContain('Daftar');
    });

    it('menampilkan sesi dengan hari, jam, lokasi, dan sisa kursi; sesi penuh nonaktif bertuliskan Penuh', () => {
        const w = mount(Sesi, {
            props: {
                ...base,
                schedules: [sesi(), sesi({ id: 2, taken: 30, full: true })],
            },
        });

        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('08.00–12.00');
        expect(w.text()).toContain('Aula SMPN 1');
        expect(w.text()).toContain('Sisa 18 dari 30 kursi');
        expect(w.text()).toContain('Penuh');
        expect(w.text()).toContain('Pendaftaran ditutup 2 hari sebelum sesi.');

        const tombol = w
            .findAll('button')
            .filter((b) => b.text().startsWith('Daftar'));
        expect(tombol[0].attributes('disabled')).toBeUndefined();
        expect(tombol[1].attributes('disabled')).toBeDefined();
    });

    it('mendaftar: dialog menyebut sesi, penguncian data, dan cara membatalkan; konfirmasi memanggil rute daftar', async () => {
        const w = mount(Sesi, {
            props: { ...base, schedules: [sesi({ id: 4 })] },
        });
        await w
            .findAll('button')
            .find((b) => b.text().startsWith('Daftar'))!
            .trigger('click');

        const dialog = w.find('[data-testid="confirm"]');
        expect(dialog.text()).toContain('Selasa, 20 Okt 2026');
        expect(dialog.text()).toContain('terkunci selama diperiksa');
        expect(dialog.text()).toContain(
            'batalkan pendaftaran untuk mengubah data',
        );

        await dialog.find('[data-testid="ok"]').trigger('click');
        expect(routerPost).toHaveBeenCalledWith(
            '/saya.sesi.daftar/4',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('alasan yang sama di semua sesi diangkat sekali ke banner; alasan khas tetap per sesi', () => {
        const dokumen = 'Akta kelahiran belum diunggah.';
        const umur = 'Umur minimal 16 tahun (umur 15).';
        const w = mount(Sesi, {
            props: {
                ...base,
                schedules: [
                    sesi({ id: 1, eligible: false, reasons: [dokumen, umur] }),
                    sesi({ id: 2, eligible: false, reasons: [dokumen] }),
                ],
            },
        });

        expect(w.text().split(dokumen)).toHaveLength(2);
        expect(w.text().split(umur)).toHaveLength(2);
        expect(w.text()).toContain('Lengkapi data saya');
        expect(
            w
                .findAll('button')
                .filter((b) => b.text().startsWith('Daftar'))
                .every((b) => b.attributes('disabled') !== undefined),
        ).toBe(true);
    });

    it('lebih dari 4 sesi dikelompokkan menurut bulan', () => {
        const w = mount(Sesi, {
            props: {
                ...base,
                schedules: [
                    sesi({ id: 1, date: '2026-10-20' }),
                    sesi({ id: 2, date: '2026-10-22' }),
                    sesi({ id: 3, date: '2026-10-27' }),
                    sesi({ id: 4, date: '2026-10-29' }),
                    sesi({ id: 5, date: '2026-11-03' }),
                ],
            },
        });

        expect(w.text()).toContain('Oktober 2026');
        expect(w.text()).toContain('November 2026');
    });

    it('sedikit sesi tidak diberi judul bulan', () => {
        const w = mount(Sesi, {
            props: { ...base, schedules: [sesi(), sesi({ id: 2 })] },
        });

        expect(w.text()).not.toContain('Oktober 2026');
    });

    it('tanpa sesi tampil empty state yang menjelaskan', () => {
        const w = mount(Sesi, { props: base });

        expect(w.text()).toContain(
            'Belum ada sesi yang dibuka untuk sekolah Anda',
        );
    });

    it('galat dari server tampil sebagai banner yang menetap', () => {
        pageProps.flash = { error: 'Kuota sesi ini sudah penuh.' };
        const w = mount(Sesi, { props: { ...base, schedules: [sesi()] } });

        expect(w.find('[role="alert"]').text()).toContain(
            'Kuota sesi ini sudah penuh.',
        );
    });
});

describe('Saya/Sesi — pendaftaran saya', () => {
    it('menunggu verifikasi: judul kartu = hari, tanggal, dan jam; tahap, langkah berikutnya, dan daftar sesi tersembunyi', () => {
        const w = mount(Sesi, { props: { ...base, entry: entry() } });

        expect(w.find('h2').text()).toContain('Selasa, 20 Okt 2026');
        expect(w.find('h2').text()).toContain('08.00–12.00');
        expect(w.text()).toContain('Menunggu verifikasi');
        expect(w.text()).toContain('Langkah berikutnya');
        expect(w.text()).toContain('data dan dokumen Anda terkunci sementara');
        expect(w.find('[aria-current="step"]').text()).toContain(
            'Menunggu verifikasi',
        );
        expect(w.text()).not.toContain('Pendaftaran ditutup');
    });

    it('terdaftar: tahap Terdaftar aktif dan tidak menjanjikan tiket', () => {
        const w = mount(Sesi, {
            props: { ...base, entry: entry({ status: 'terdaftar' }) },
        });

        expect(w.find('[aria-current="step"]').text()).toContain('Terdaftar');
        expect(w.text()).toContain('Anda terdaftar. Datang sesuai jadwal.');
        expect(w.text().toLowerCase()).not.toContain('tiket');
    });

    it('perlu perbaikan: catatan petugas, perbaiki data, dan ajukan ulang lewat konfirmasi', async () => {
        const w = mount(Sesi, {
            props: {
                ...base,
                entry: entry({
                    status: 'perlu_perbaikan',
                    note: 'Foto KK buram',
                    can_resubmit: true,
                }),
            },
        });

        expect(w.text()).toContain('Foto KK buram');
        expect(w.find('a[href="/saya.show"]').text()).toContain(
            'Perbaiki data',
        );

        await w
            .findAll('button')
            .find((b) => b.text().includes('Ajukan ulang'))!
            .trigger('click');
        await w.find('[data-testid="ok"]').trigger('click');
        expect(routerPost).toHaveBeenCalledWith(
            '/saya.pendaftaran.ajukan-ulang/9',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('membatalkan pendaftaran lewat konfirmasi', async () => {
        const w = mount(Sesi, { props: { ...base, entry: entry() } });

        await w
            .findAll('button')
            .find((b) => b.text().includes('Batalkan pendaftaran'))!
            .trigger('click');
        await w.find('[data-testid="ok"]').trigger('click');
        expect(routerPost).toHaveBeenCalledWith(
            '/saya.pendaftaran.batal/9',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('tidak dapat dibatalkan lagi: tombol hilang dan alasannya tertulis', () => {
        const w = mount(Sesi, {
            props: { ...base, entry: entry({ can_cancel: false }) },
        });

        expect(
            w
                .findAll('button')
                .some((b) => b.text().includes('Batalkan pendaftaran')),
        ).toBe(false);
        expect(w.text()).toContain(
            'Pembatalan hanya sampai sehari sebelum sesi.',
        );
    });

    it('ditolak: catatan tampil dan sesi lain dapat dipilih lagi', () => {
        const w = mount(Sesi, {
            props: {
                ...base,
                entry: entry({
                    status: 'ditolak',
                    note: 'Dokumen tidak sesuai',
                    can_cancel: false,
                }),
                schedules: [sesi({ id: 7 })],
            },
        });

        expect(w.text()).toContain('Dokumen tidak sesuai');
        expect(w.text()).toContain('Pendaftaran ditolak');
        expect(
            w.findAll('button').some((b) => b.text().startsWith('Daftar')),
        ).toBe(true);
    });
});
