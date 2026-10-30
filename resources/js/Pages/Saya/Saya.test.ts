import Saya from '@/Pages/Saya/Index.vue';
import type { SelfStudent } from '@/types/student';
import { config, flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { put, permissions } = vi.hoisted(() => ({
    put: vi.fn(),
    permissions: [
        'profil.lihat',
        'profil.ubah',
        'dokumen.lihat',
        'dokumen.unggah',
        'dokumen.hapus',
        'pendaftaran.lihat',
    ],
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => ({ props: { auth: { permissions } } }),
        useForm: (data: Record<string, string>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                put,
                clearErrors: vi.fn(),
            }),
    };
});
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/lib/focusFirstInvalid', () => ({ focusFirstInvalid: vi.fn() }));
vi.mock('@/Components/shared/FileUpload.vue', () => ({
    default: {
        props: { label: String, canUpload: Boolean, hideDownload: Boolean },
        template:
            '<div data-testid="upload" :data-can-upload="String(canUpload)" :data-hide-download="String(hideDownload)">{{ label }}</div>',
    },
}));
vi.mock('@/Components/shared/DocumentPreviewDialog.vue', () => ({
    default: { template: '<div />' },
}));

const siswa: SelfStudent = {
    id: 7,
    nik: '3201123412341234',
    nisn: '0012345678',
    name: 'Budi Santoso',
    birth_date: '2009-05-01',
    gender: 'L',
};
const base = {
    student: null as SelfStudent | null,
    school: 'SMAN 1 Sungai Beduk',
    account_name: 'Budi',
    documents: [],
    eligibility: { eligible: false, reasons: ['Data diri belum disimpan.'] },
    locked: false,
};

beforeEach(() => {
    const route = (name: string) => `/${name}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    put.mockReset();
});

describe('Saya', () => {
    it('data belum disimpan: dokumen nonaktif dengan alasan yang terlihat, nama terisi dari akun', () => {
        const w = mount(Saya, { props: base });

        expect(w.text()).toContain(
            'Simpan data diri di Langkah 1 agar bisa mengunggah dokumen.',
        );
        for (const u of w.findAll('[data-testid="upload"]')) {
            expect(u.attributes('data-can-upload')).toBe('false');
            expect(u.attributes('data-hide-download')).toBe('true');
        }
        expect(
            (w.find('input[autocomplete="name"]').element as HTMLInputElement)
                .value,
        ).toBe('Budi');
    });

    it('menempel NIK dengan spasi menghasilkan angka saja', async () => {
        const w = mount(Saya, { props: base });
        const nik = w.find('input[maxlength="16"]');

        await nik.setValue('3201 1234 1234 1234');
        await flushPromises();

        expect((nik.element as HTMLInputElement).value).toBe(
            '3201123412341234',
        );
    });

    it('NIK tidak lengkap menampilkan pesan dan tidak mengirim', async () => {
        const w = mount(Saya, { props: base });

        await w.find('input[maxlength="16"]').setValue('123');
        await w.find('form').trigger('submit');

        expect(w.text()).toContain('NIK harus 16 digit angka.');
        expect(put).not.toHaveBeenCalled();
    });

    it('terkunci: data baca-saja, tidak ada tombol simpan, banner tampil', () => {
        const w = mount(Saya, {
            props: {
                ...base,
                student: siswa,
                locked: true,
                eligibility: { eligible: true, reasons: [] },
            },
        });

        expect(w.text()).toContain('sedang diperiksa petugas');
        expect(w.text()).toContain('3201123412341234');
        expect(w.find('form').exists()).toBe(false);
        expect(w.text()).not.toContain('Simpan data');
        for (const u of w.findAll('[data-testid="upload"]')) {
            expect(u.attributes('data-can-upload')).toBe('false');
        }
    });

    it('lengkap menampilkan kartu sukses, belum lengkap menampilkan alasan', () => {
        const lengkap = mount(Saya, {
            props: {
                ...base,
                student: siswa,
                eligibility: { eligible: true, reasons: [] },
            },
        });
        expect(lengkap.text()).toContain('sudah lengkap');

        const kurang = mount(Saya, {
            props: {
                ...base,
                student: siswa,
                eligibility: {
                    eligible: false,
                    reasons: ['Akta kelahiran belum diunggah.'],
                },
            },
        });
        expect(kurang.text()).toContain('Akta kelahiran belum diunggah.');
        const uploads = kurang.findAll('[data-testid="upload"]');
        expect(uploads[0].attributes('data-can-upload')).toBe('true');
    });

    it('kartu Sesi rekaman hanya muncul setelah data dan dokumen lengkap', () => {
        const lengkap = mount(Saya, {
            props: {
                ...base,
                student: siswa,
                eligibility: { eligible: true, reasons: [] },
            },
        });
        expect(lengkap.find('a[href="/me.sessions.index"]').text()).toContain(
            'Pilih sesi rekaman',
        );

        const belum = mount(Saya, {
            props: {
                ...base,
                student: siswa,
                eligibility: {
                    eligible: false,
                    reasons: ['Akta kelahiran belum diunggah.'],
                },
            },
        });
        expect(belum.find('a[href="/me.sessions.index"]').exists()).toBe(false);
        expect(
            mount(Saya, { props: base }).find('a[href="/me.sessions.index"]').exists(),
        ).toBe(false);
    });

    it('saat data terkunci tautan sesi menjadi Lihat pendaftaran saya', () => {
        const w = mount(Saya, {
            props: {
                ...base,
                student: siswa,
                locked: true,
                eligibility: { eligible: true, reasons: [] },
            },
        });

        expect(w.find('a[href="/me.sessions.index"]').text()).toContain(
            'Lihat pendaftaran saya',
        );
    });
});
