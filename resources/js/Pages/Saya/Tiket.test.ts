import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import Tiket from '@/Pages/Saya/Tiket.vue';
import type { Ticket } from '@/types/registration';
import { config, flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { toDataURL, renderTicket, downloadDataUrl } = vi.hoisted(() => ({
    toDataURL: vi.fn(),
    renderTicket: vi.fn(),
    downloadDataUrl: vi.fn(),
}));

vi.mock('qrcode', () => ({ default: { toDataURL } }));
vi.mock('@/lib/ticketImage', () => ({ renderTicket, downloadDataUrl }));
vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { auth: { permissions: [] } } }),
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

const KODE = 'KODE' + 'A'.repeat(28);
const tiket = (over: Partial<Ticket> = {}): Ticket => ({
    entry_id: 9,
    status: 'terdaftar',
    code: KODE,
    student_name: 'Budi Santoso',
    ktp_langsung: false,
    school: 'SMPN 1 Sungai Beduk',
    schedule: {
        id: 1,
        date: '2026-10-20',
        start_time: '08:00',
        end_time: '12:00',
        location: 'Aula SMPN 1',
    },
    ...over,
});

beforeEach(() => {
    const route = (name: string, id?: number) =>
        `/${name}${id !== undefined ? `/${id}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    toDataURL.mockReset().mockResolvedValue('data:image/png;base64,QQQ');
    renderTicket.mockReset().mockResolvedValue('data:image/png;base64,TTT');
    downloadDataUrl.mockReset();
});

describe('Saya/Tiket', () => {
    it('menampilkan QR berlatar putih dengan alt bermakna, detail sesi, dan petunjuk kecerahan', async () => {
        const w = mount(Tiket, { props: { ticket: tiket() } });
        await flushPromises();

        const img = w.find('img');
        expect(img.attributes('src')).toBe('data:image/png;base64,QQQ');
        expect(img.attributes('alt')).toBe(
            'Kode QR tiket rekaman Budi Santoso',
        );
        expect(img.element.parentElement!.className).toContain('bg-white');
        expect(w.text()).toContain('Budi Santoso');
        expect(w.text()).toContain('SMPN 1 Sungai Beduk');
        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('08.00–12.00');
        expect(w.text()).toContain('Aula SMPN 1');
        expect(w.text()).toContain('Tunjukkan QR ini kepada petugas saat sesi');
        expect(w.text()).toContain('Naikkan kecerahan layar saat dipindai.');
    });

    it('QR dibuat dari kode dan kode tidak dirender sebagai teks', async () => {
        const w = mount(Tiket, { props: { ticket: tiket() } });
        await flushPromises();

        expect(toDataURL).toHaveBeenCalledWith(
            KODE,
            expect.objectContaining({ margin: 2 }),
        );
        expect(w.html()).not.toContain(KODE);
    });

    it('Simpan sebagai gambar menyusun kartu tiket lalu mengunduhnya dengan nama berkas tanggal', async () => {
        const w = mount(Tiket, { props: { ticket: tiket() } });
        await flushPromises();

        await w
            .findAll('button')
            .find((b) => b.text().includes('Simpan sebagai gambar'))!
            .trigger('click');
        await flushPromises();

        expect(renderTicket).toHaveBeenCalledWith(
            'data:image/png;base64,QQQ',
            expect.objectContaining({
                studentName: 'Budi Santoso',
                school: 'SMPN 1 Sungai Beduk',
                day: 'Selasa, 20 Okt 2026',
                time: '08.00–12.00',
                location: 'Aula SMPN 1',
            }),
        );
        expect(downloadDataUrl).toHaveBeenCalledWith(
            'data:image/png;base64,TTT',
            'tiket-rekaman-2026-10-20.png',
        );
    });

    it('menyediakan tautan kembali ke halaman sesi', () => {
        const w = mount(Tiket, { props: { ticket: tiket() } });

        expect(w.find('a[href="/me.sessions.index"]').text()).toContain(
            'Kembali ke sesi saya',
        );
    });

    it('Sudah rekaman: tanpa QR dan tanpa tombol simpan', async () => {
        const w = mount(Tiket, {
            props: { ticket: tiket({ status: 'sudah_rekaman', code: null }) },
        });
        await flushPromises();

        expect(w.find('img').exists()).toBe(false);
        expect(toDataURL).not.toHaveBeenCalled();
        expect(w.text()).toContain('Perekaman Anda sudah selesai.');
        expect(
            w.findAll('button').some((b) => b.text().includes('Simpan')),
        ).toBe(false);
    });

    it('gagal membuat QR menampilkan pesan, bukan halaman kosong', async () => {
        toDataURL.mockRejectedValue(new Error('gagal'));
        const w = mount(Tiket, { props: { ticket: tiket() } });
        await flushPromises();

        expect(w.find('[role="alert"]').text()).toContain(
            'QR gagal dibuat. Muat ulang halaman.',
        );
        expect(w.find('img').exists()).toBe(false);
        expect(w.text()).toContain('Budi Santoso');
    });

    it('gagal menyusun gambar menampilkan pesan dan tidak mengunduh', async () => {
        renderTicket.mockRejectedValue(new Error('gagal'));
        const w = mount(Tiket, { props: { ticket: tiket() } });
        await flushPromises();

        await w
            .findAll('button')
            .find((b) => b.text().includes('Simpan sebagai gambar'))!
            .trigger('click');
        await flushPromises();

        expect(downloadDataUrl).not.toHaveBeenCalled();
        expect(w.text()).toContain('Gambar tiket gagal dibuat');
    });
});

describe('Saya/Tiket — KTP-el langsung', () => {
    it('menampilkan lencana dan penjelasan hanya bila ktp_langsung', () => {
        const ya = mount(Tiket, {
            props: { ticket: tiket({ ktp_langsung: true }) },
        });
        const tidak = mount(Tiket, { props: { ticket: tiket() } });

        expect(ya.findComponent(KtpLangsungBadge).exists()).toBe(true);
        expect(ya.text()).toContain('KTP-el dapat langsung diterbitkan');
        expect(tidak.findComponent(KtpLangsungBadge).exists()).toBe(false);
    });
});
