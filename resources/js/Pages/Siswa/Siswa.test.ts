import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import Form from '@/Pages/Siswa/Form.vue';
import Index from '@/Pages/Siswa/Index.vue';
import Show from '@/Pages/Siswa/Show.vue';
import type { StudentDetail } from '@/types/student';
import { config, flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { put, permissions } = vi.hoisted(() => ({
    put: vi.fn(),
    permissions: [] as string[],
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        router: { delete: vi.fn(), get: vi.fn() },
        usePage: () => ({ props: { auth: { permissions } }, url: '/siswa' }),
        useForm: (data: Record<string, unknown>) =>
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
    default: { template: '<div data-testid="upload" />' },
}));
vi.mock('@/Components/shared/DocumentPreviewDialog.vue', () => ({
    default: { template: '<div />' },
}));

const siswa: StudentDetail = {
    id: 7,
    name: 'Budi',
    nisn: '0012345678',
    nik: '3201********1234',
    age: 16,
    ktp_langsung: false,
    school: 'SMAN 1',
    docs: { kk: true, akta_lahir: true },
    eligible: true,
    reasons: [],
    birth_date: '2009-05-01',
    gender: 'L',
};
const showProps = {
    student: siswa,
    documents: [],
    eligibility: { eligible: true, reasons: [] },
    locked: false,
    registrations: [],
};

beforeEach(() => {
    const route = (name: string, id?: number) => `/${name}/${id ?? ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    put.mockReset();
    permissions.length = 0;
});

describe('Siswa/Show', () => {
    it('tanpa izin dokumen.lihat tidak merender bagian dokumen', () => {
        permissions.push('siswa.lihat');
        const w = mount(Show, { props: showProps });

        expect(w.find('[data-testid="upload"]').exists()).toBe(false);
        expect(w.text()).not.toContain('Dokumen');
    });

    it('tombol Koreksi data tampil untuk pemegang siswa.ubah walau terkunci, dengan banner sesuai peran', () => {
        permissions.push('siswa.lihat', 'siswa.ubah');
        const admin = mount(Show, { props: { ...showProps, locked: true } });

        expect(admin.text()).toContain('Koreksi data');
        expect(admin.text()).toContain('Anda tetap dapat mengoreksi');

        permissions.length = 0;
        permissions.push('siswa.lihat');
        const operator = mount(Show, { props: { ...showProps, locked: true } });

        expect(operator.text()).not.toContain('Koreksi data');
        expect(operator.text()).toContain('tidak dapat diubah');
    });
});

describe('Siswa/Show — pendaftaran sesi', () => {
    it('kartu pendaftaran menampilkan sesi (hari, jam, lokasi) dan status, tertaut ke sesi bagi pemegang jadwal.lihat', () => {
        permissions.push('siswa.lihat', 'jadwal.lihat');
        const w = mount(Show, {
            props: {
                ...showProps,
                registrations: [
                    {
                        id: 3,
                        status: 'terdaftar',
                        schedule: {
                            id: 5,
                            date: '2026-10-20',
                            start_time: '08:00',
                            end_time: '12:00',
                            location: 'Aula SMPN 1',
                        },
                    },
                ],
            },
        });

        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.text()).toContain('08.00–12.00');
        expect(w.text()).toContain('Aula SMPN 1');
        expect(w.text()).toContain('Terdaftar');
        expect(w.find('a[href="/jadwal.show/5"]').exists()).toBe(true);
    });

    it('tanpa izin jadwal.lihat sesi tampil sebagai teks biasa', () => {
        permissions.push('siswa.lihat');
        const w = mount(Show, {
            props: {
                ...showProps,
                registrations: [
                    {
                        id: 3,
                        status: 'ditolak',
                        schedule: {
                            id: 5,
                            date: '2026-10-20',
                            start_time: '08:00',
                            end_time: '12:00',
                            location: 'Aula',
                        },
                    },
                ],
            },
        });

        expect(w.text()).toContain('Selasa, 20 Okt 2026');
        expect(w.find('a[href="/jadwal.show/5"]').exists()).toBe(false);
    });
});

describe('Siswa/Form', () => {
    const formProps = {
        student: {
            id: 7,
            nik: '3201123412341234',
            nisn: '0012345678',
            name: 'Budi',
            birth_date: '2009-05-01',
            gender: 'L' as const,
            school_id: 1,
        },
        schools: null,
    };

    it('catatan kosong memblokir pengiriman dan menampilkan pesan', async () => {
        const w = mount(Form, { props: formProps });

        await w.find('form').trigger('submit');

        expect(w.text()).toContain(
            'Catatan koreksi wajib diisi (minimal 5 karakter).',
        );
        expect(put).not.toHaveBeenCalled();
    });

    it('catatan terisi dikirim ke siswa.update', async () => {
        const w = mount(Form, { props: formProps });

        await w.find('textarea').setValue('NIK salah ketik.');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][0]).toBe('/siswa.update/7');
    });
});

describe('Siswa/Index kosong', () => {
    const paginator = {
        data: [],
        current_page: 1,
        last_page: 1,
        from: null,
        to: null,
        total: 0,
        per_page: 20,
        links: [],
    };

    it('menampilkan aksi Lihat link pendaftaran hanya bagi pemegang sekolah.tautan', () => {
        permissions.push('siswa.lihat', 'sekolah.tautan');
        const dengan = mount(Index, {
            props: { students: paginator, filters: {}, schools: null },
        });
        expect(dengan.text()).toContain('Lihat link pendaftaran');

        permissions.length = 0;
        permissions.push('siswa.lihat');
        const tanpa = mount(Index, {
            props: { students: paginator, filters: {}, schools: null },
        });
        expect(tanpa.text()).not.toContain('Lihat link pendaftaran');
    });
});

describe('Siswa — penanda KTP-el langsung', () => {
    const halaman = (rows: (typeof siswa)[]) => ({
        data: rows,
        current_page: 1,
        last_page: 1,
        from: 1,
        to: rows.length,
        total: rows.length,
        per_page: 20,
        links: [],
    });

    it('daftar menampilkan lencana hanya pada siswa yang ktp_langsung', () => {
        permissions.push('siswa.lihat');
        const w = mount(Index, {
            props: {
                students: halaman([
                    { ...siswa, id: 1, ktp_langsung: true },
                    { ...siswa, id: 2, ktp_langsung: false },
                ]),
                filters: {},
                schools: null,
            },
        });

        expect(w.findAllComponents(KtpLangsungBadge)).toHaveLength(1);
    });

    it('detail menampilkan lencana beserta kalimat penjelas hanya bila ktp_langsung', () => {
        permissions.push('siswa.lihat');
        const ya = mount(Show, {
            props: { ...showProps, student: { ...siswa, ktp_langsung: true } },
        });
        const tidak = mount(Show, { props: showProps });

        expect(ya.findComponent(KtpLangsungBadge).exists()).toBe(true);
        expect(ya.text()).toContain('KTP-el dapat langsung diterbitkan');
        expect(tidak.findComponent(KtpLangsungBadge).exists()).toBe(false);
        expect(tidak.text()).not.toContain('KTP-el dapat langsung diterbitkan');
    });
});
