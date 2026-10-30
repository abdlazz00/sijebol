import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from './Dashboard.vue';
import type { StudentDashboardProps } from '@/types/student-dashboard';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { auth: { user: { name: 'Budi' } } } }),
}));

vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

beforeEach(() => {
    const route = (name: string, id?: number) => `/${name}${id ? `/${id}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

const mockProps: StudentDashboardProps = {
    student: {
        id: 1,
        name: 'Budi Santoso',
        nik: '2171012345670001',
        nisn: '0061234567',
        school_name: 'MAN 2 Batam',
        has_profile: true,
    },
    stepper: {
        current_step: 3,
        steps: [
            { id: 1, title: 'Akun Terdaftar', status: 'completed', description: 'Akun aktif' },
            { id: 2, title: 'Data Diri & Dokumen', status: 'completed', description: 'Lengkap' },
            { id: 3, title: 'Pendaftaran Sesi', status: 'current', description: 'Menunggu verifikasi' },
            { id: 4, title: 'Perekaman KTP-el', status: 'upcoming', description: 'Hari-H' },
        ],
    },
    smart_hero: {
        state: 'MENUNGGU_VERIFIKASI',
        variant: 'indigo',
        title: 'Berkas Sedang Diverifikasi',
        subtitle: 'Petugas sedang memeriksa berkas Anda.',
        badge: 'Dalam Antrean',
        cta_label: 'Lihat Sesi Rekaman',
        cta_url: '/me/sessions',
        deadline_at: null,
        notes: null,
    },
    active_entry: {
        id: 10,
        status: 'menunggu_verifikasi',
        status_label: 'Menunggu Verifikasi',
        ticket_code: null,
        schedule_date: '2026-11-05',
        time_window: '08:30 - 11:30 WIB',
        location: 'Aula MAN 2 Batam',
        direct_ktp: true,
        days_remaining: 9,
    },
    school_schedules: [],
    guidelines: [
        'Bawa dokumen fisik asli KK dan Akta.',
        'Kenakan seragam sekolah rapi berkerah.',
    ],
};

describe('Pages/Student/Dashboard.vue', () => {
    it('merender judul sambutan nama siswa, asal sekolah, stepper, smart hero, dan panduan secara harmonis', () => {
        const wrapper = mount(Dashboard, {
            props: mockProps,
            global: {
                stubs: {
                    KtpLangsungBadge: { template: '<span data-testid="ktp-badge">KTP Langsung</span>' },
                },
            },
        });

        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('MAN 2 Batam');
        expect(wrapper.text()).toContain('Berkas Sedang Diverifikasi');
        expect(wrapper.text()).toContain('Akun Terdaftar');
        expect(wrapper.text()).toContain('Bawa dokumen fisik asli KK dan Akta.');
    });
});
