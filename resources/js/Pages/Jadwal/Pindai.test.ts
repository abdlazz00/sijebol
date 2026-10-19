import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import Pindai from '@/Pages/Jadwal/Pindai.vue';
import type { ScheduleDetail } from '@/types/schedule';
import { config, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { scanners, hasCamera, postJson, permissions, kamera } = vi.hoisted(
    () => ({
        scanners: [] as {
            video: HTMLVideoElement;
            onDecode: (r: { data: string }) => void;
            options: Record<string, unknown>;
            start: ReturnType<typeof vi.fn>;
            stop: ReturnType<typeof vi.fn>;
            destroy: ReturnType<typeof vi.fn>;
        }[],
        hasCamera: vi.fn(),
        postJson: vi.fn(),
        permissions: [] as string[],
        kamera: { startError: null as Error | null },
    }),
);

vi.mock('qr-scanner', () => ({
    default: class {
        static hasCamera = hasCamera;
        start = vi.fn(() =>
            kamera.startError
                ? Promise.reject(kamera.startError)
                : Promise.resolve(),
        );
        stop = vi.fn();
        destroy = vi.fn();
        constructor(
            video: HTMLVideoElement,
            onDecode: (r: { data: string }) => void,
            options: Record<string, unknown>,
        ) {
            scanners.push({
                video,
                onDecode,
                options,
                start: this.start,
                stop: this.stop,
                destroy: this.destroy,
            });
        }
    },
}));
vi.mock('@/lib/postJson', () => ({ postJson }));
vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { auth: { permissions } } }),
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

const sesi: ScheduleDetail = {
    id: 5,
    date: '2026-10-20',
    start_time: '08:00',
    end_time: '12:00',
    location: 'Aula SMPN 1',
    school: 'SMPN 1',
    quota: 30,
    taken: 10,
    cancelled: false,
    is_past: false,
    last_registration_day: '2026-10-18',
};
const jendelaTerbuka = {
    from: '2020-01-01T00:00:00+07:00',
    to: '2099-01-01T00:00:00+07:00',
};
const ok = (nama: string, ktp = false, counts = { waiting: 2, done: 4 }) => ({
    status: 200,
    data: {
        status: 'ok',
        message: 'Tercatat sudah rekaman.',
        student: { name: nama, nisn: '0012345678', ktp_langsung: ktp },
        scanned_at: '2026-10-20T08:30:00+07:00',
        counts,
    },
});

function pasang(over: Record<string, unknown> = {}) {
    return mount(Pindai, {
        props: {
            schedule: sesi,
            counts: { waiting: 3, done: 3 },
            window: jendelaTerbuka,
            ...over,
        },
        attachTo: document.body,
    });
}

async function mulai(w: ReturnType<typeof pasang>) {
    await w
        .findAll('button')
        .find((b) => b.text().includes('Mulai memindai'))!
        .trigger('click');
    await flushPromises();

    return scanners[scanners.length - 1];
}

const decode = async (kode: string) => {
    scanners[scanners.length - 1].onDecode({ data: kode });
    await flushPromises();
};

beforeEach(() => {
    const route = (name: string, id?: number) =>
        `/${name}${id !== undefined ? `/${id}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    vi.stubGlobal('isSecureContext', true);
    scanners.length = 0;
    kamera.startError = null;
    permissions.length = 0;
    permissions.push('rekaman.pindai', 'rekaman.lihat');
    hasCamera.mockReset().mockResolvedValue(true);
    postJson.mockReset();
});

afterEach(() => {
    document.body.replaceChildren();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Jadwal/Pindai — kamera', () => {
    it('kamera tidak dinyalakan sebelum petugas menekan Mulai memindai', () => {
        const w = pasang();

        expect(scanners).toHaveLength(0);
        expect(w.text()).toContain('Mulai memindai');
        expect(w.text()).toContain('Sudah 3');
        expect(w.text()).toContain('Belum 3');
    });

    it('Mulai memindai membuat pemindai kamera belakang dan menyalakannya', async () => {
        const w = pasang();
        const s = await mulai(w);

        expect(scanners).toHaveLength(1);
        expect(s.video).toBeInstanceOf(HTMLVideoElement);
        expect(s.options).toMatchObject({
            preferredCamera: 'environment',
            returnDetailedScanResult: true,
        });
        expect(s.start).toHaveBeenCalledOnce();
        expect(w.text()).toContain('Hentikan');
    });

    it('izin kamera ditolak: penjelasan tertulis dan jalan keluar ke penandaan manual', async () => {
        kamera.startError = Object.assign(new Error('ditolak'), {
            name: 'NotAllowedError',
        });
        const w = pasang();

        await mulai(w);

        expect(w.text()).toContain('Izin kamera ditolak');
        expect(w.text()).toContain('Mulai memindai');
        expect(
            w
                .findAll('a')
                .some((a) => a.attributes('href') === '/jadwal.rekaman.show/5'),
        ).toBe(true);
    });

    it('kamera tidak ditemukan: penjelasan dan tautan penandaan manual', async () => {
        hasCamera.mockResolvedValue(false);
        const w = pasang();
        await w
            .findAll('button')
            .find((b) => b.text().includes('Mulai memindai'))!
            .trigger('click');
        await flushPromises();

        expect(scanners).toHaveLength(0);
        expect(w.text()).toContain('Kamera tidak ditemukan');
        expect(
            w
                .findAll('a')
                .some((a) => a.attributes('href') === '/jadwal.rekaman.show/5'),
        ).toBe(true);
    });

    it('konteks tidak aman: menjelaskan bahwa kamera butuh HTTPS', async () => {
        vi.stubGlobal('isSecureContext', false);
        const w = pasang();
        await w
            .findAll('button')
            .find((b) => b.text().includes('Mulai memindai'))!
            .trigger('click');
        await flushPromises();

        expect(scanners).toHaveLength(0);
        expect(w.text()).toContain('butuh HTTPS');
        expect(
            w
                .findAll('a')
                .some((a) => a.attributes('href') === '/jadwal.rekaman.show/5'),
        ).toBe(true);
    });

    it('saat dilepas, kamera dihentikan dan pemindai dihancurkan', async () => {
        const w = pasang();
        const s = await mulai(w);

        w.unmount();

        expect(s.stop).toHaveBeenCalled();
        expect(s.destroy).toHaveBeenCalled();
    });
});

describe('Jadwal/Pindai — hasil pindai', () => {
    it('sukses: nama, NISN, pesan, lencana KTP-el, dan hitungan diperbarui', async () => {
        postJson.mockResolvedValue(ok('Budi Santoso', true));
        const w = pasang();
        await mulai(w);

        await decode('KODE-1');

        expect(postJson).toHaveBeenCalledWith('/jadwal.pindai.store/5', {
            code: 'KODE-1',
        });
        expect(w.text()).toContain('Budi Santoso');
        expect(w.text()).toContain('0012345678');
        expect(w.text()).toContain('Tercatat sudah rekaman');
        expect(w.findComponent(KtpLangsungBadge).exists()).toBe(true);
        expect(w.text()).toContain('Sudah 4');
        expect(w.text()).toContain('Belum 2');
    });

    it('penolakan 422: pesan server tampil sebagai teks beserta kata Ditolak', async () => {
        postJson.mockResolvedValue({
            status: 422,
            data: {
                status: 'rejected',
                message: 'Tiket sudah dipindai pukul 08.30.',
            },
        });
        const w = pasang();
        await mulai(w);

        await decode('KODE-2');

        expect(w.text()).toContain('Ditolak');
        expect(w.text()).toContain('Tiket sudah dipindai pukul 08.30.');
        expect(w.text()).toContain('Sudah 3');
    });

    it('429 dan galat server menampilkan pesan yang bisa ditindaklanjuti', async () => {
        const w = pasang();
        await mulai(w);

        postJson.mockResolvedValueOnce({ status: 429, data: null });
        await decode('A');
        expect(w.text()).toContain('Terlalu banyak pindaian; tunggu sebentar.');

        postJson.mockResolvedValueOnce({ status: 419, data: null });
        await decode('B');
        expect(w.text()).toContain('Muat ulang halaman');

        postJson.mockResolvedValueOnce({ status: 500, data: null });
        await decode('C');
        expect(w.text()).toContain('belum tercatat');
    });

    it('galat jaringan: pesan tampil, pemindaian lanjut, dan kode yang sama bisa langsung diulang', async () => {
        const w = pasang();
        const s = await mulai(w);
        postJson.mockRejectedValueOnce(new TypeError('Failed to fetch'));

        await decode('KODE-3');
        expect(w.text()).toContain('Tidak ada sambungan');
        expect(s.stop).not.toHaveBeenCalled();

        postJson.mockResolvedValueOnce(ok('Ani'));
        await decode('KODE-3');
        expect(postJson).toHaveBeenCalledTimes(2);
        expect(w.text()).toContain('Ani');
    });

    it('kode sama dalam 5 detik tidak dikirim dua kali, setelahnya boleh', async () => {
        const sekarang = vi.spyOn(Date, 'now').mockReturnValue(1_000_000);
        postJson.mockResolvedValue(ok('Budi'));
        const w = pasang();
        await mulai(w);

        await decode('KODE-4');
        sekarang.mockReturnValue(1_003_000);
        await decode('KODE-4');
        expect(postJson).toHaveBeenCalledTimes(1);

        sekarang.mockReturnValue(1_006_000);
        await decode('KODE-4');
        expect(postJson).toHaveBeenCalledTimes(2);
        expect(w.exists()).toBe(true);
    });

    it('selama satu permintaan berjalan, pindaian berikutnya diabaikan', async () => {
        let selesai: (v: unknown) => void = () => {};
        postJson.mockReturnValueOnce(new Promise((r) => (selesai = r)));
        const w = pasang();
        await mulai(w);

        scanners[0].onDecode({ data: 'KODE-5' });
        scanners[0].onDecode({ data: 'KODE-6' });
        selesai(ok('Budi'));
        await flushPromises();

        expect(postJson).toHaveBeenCalledTimes(1);
        expect(w.text()).toContain('Budi');
    });

    it('menyimpan paling banyak 5 hasil terakhir', async () => {
        postJson.mockImplementation(() => Promise.resolve(ok('Siswa')));
        const w = pasang();
        await mulai(w);

        for (const k of ['a', 'b', 'c', 'd', 'e', 'f']) await decode(k);

        expect(w.findAll('[data-testid="riwayat"] li')).toHaveLength(5);
    });

    it('panel hasil berada di region aria-live dan tombol memenuhi target sentuh 44px', async () => {
        const w = pasang();

        const live = w.find('[aria-live="polite"]');
        expect(live.exists()).toBe(true);
        const tombol = w
            .findAll('button')
            .find((b) => b.text().includes('Mulai memindai'))!;
        expect(tombol.classes().join(' ')).toMatch(/min-h-11|h-11|h-12/);

        postJson.mockResolvedValue(ok('Budi'));
        await mulai(w);
        await decode('KODE-7');
        expect(live.text()).toContain('Budi');
    });
});

describe('Jadwal/Pindai — keadaan sesi', () => {
    it('sesi dibatalkan: peringatan tanpa tombol kamera', () => {
        const w = pasang({ schedule: { ...sesi, cancelled: true } });

        expect(w.text()).toContain('Sesi ini dibatalkan');
        expect(w.text()).not.toContain('Mulai memindai');
    });

    it('di luar jendela: peringatan jam dibuka, tombol tetap ada', () => {
        const w = pasang({
            window: {
                from: '2099-10-20T07:00:00+07:00',
                to: '2099-10-20T13:00:00+07:00',
            },
        });

        expect(w.text()).toContain('Pemindaian dibuka');
        expect(w.text()).toContain('07.00–13.00');
        expect(w.text()).toContain('Mulai memindai');
    });

    it('di dalam jendela tidak ada peringatan jam', () => {
        const w = pasang();

        expect(w.text()).not.toContain('Pemindaian dibuka');
    });
});
