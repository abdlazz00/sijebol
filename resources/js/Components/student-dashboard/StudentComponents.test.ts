import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import StudentStepper from './StudentStepper.vue';
import StudentSmartHero from './StudentSmartHero.vue';
import StudentActiveTicketCard from './StudentActiveTicketCard.vue';
import StudentUpcomingSchedules from './StudentUpcomingSchedules.vue';
import StudentGuidelines from './StudentGuidelines.vue';
import type { SmartHeroData, ActiveEntryData, SchoolScheduleItem, StepItem } from '@/types/student-dashboard';

beforeEach(() => {
    const route = (name: string, id?: number) => `/${name}${id ? `/${id}` : ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

describe('StudentStepper.vue', () => {
    it('merender seluruh 4 langkah alur pendaftaran siswa dengan status yang tepat', () => {
        const steps: StepItem[] = [
            { id: 1, title: 'Akun Terdaftar', status: 'completed', description: 'Akun aktif' },
            { id: 2, title: 'Data Diri & Dokumen', status: 'completed', description: 'Biodata lengkap' },
            { id: 3, title: 'Pendaftaran Sesi', status: 'current', description: 'Menunggu verifikasi' },
            { id: 4, title: 'Perekaman KTP-el', status: 'upcoming', description: 'Hari-H' },
        ];

        const wrapper = mount(StudentStepper, {
            props: {
                currentStep: 3,
                steps,
            },
        });

        expect(wrapper.text()).toContain('Akun Terdaftar');
        expect(wrapper.text()).toContain('Data Diri & Dokumen');
        expect(wrapper.text()).toContain('Pendaftaran Sesi');
        expect(wrapper.text()).toContain('Perekaman KTP-el');

        const activeStep = wrapper.find('[aria-current="step"]');
        expect(activeStep.exists()).toBe(true);
        expect(activeStep.text()).toContain('Pendaftaran Sesi');
    });
});

describe('StudentSmartHero.vue', () => {
    it('merender judul, badge, catatan perbaikan, dan tombol CTA aksi primer dengan benar', () => {
        const hero: SmartHeroData = {
            state: 'PERBAIKAN',
            variant: 'rose',
            title: 'Berkas Perlu Diperbaiki',
            subtitle: 'Tolong perbaiki foto KK Anda.',
            badge: 'Perlu Perbaikan',
            cta_label: 'Perbaiki Berkas',
            cta_url: '/me',
            deadline_at: '2026-10-30T10:00:00Z',
            notes: 'Foto buram dan tulisan tidak terbaca.',
        };

        const wrapper = mount(StudentSmartHero, {
            props: { hero },
            global: {
                stubs: { Link: { template: '<a :href="$attrs.href"><slot /></a>' } },
            },
        });

        expect(wrapper.text()).toContain('Berkas Perlu Diperbaiki');
        expect(wrapper.text()).toContain('Perlu Perbaikan');
        expect(wrapper.text()).toContain('Foto buram dan tulisan tidak terbaca.');
        expect(wrapper.find('a').attributes('href')).toBe('/me');
        expect(wrapper.find('a').text()).toContain('Perbaiki Berkas');
    });
});

describe('StudentActiveTicketCard.vue', () => {
    it('merender rincian tiket, tanggal sesi, jam, lokasi, dan penanda KTP langsung', () => {
        const activeEntry: ActiveEntryData = {
            id: 10,
            status: 'terdaftar',
            status_label: 'Terdaftar',
            ticket_code: 'TKT-2026-0099',
            schedule_date: '2026-11-05',
            time_window: '08:30 - 11:30 WIB',
            location: 'Aula MAN 2 Batam',
            direct_ktp: true,
            days_remaining: 9,
        };

        const wrapper = mount(StudentActiveTicketCard, {
            props: { entry: activeEntry },
            global: {
                stubs: {
                    Link: { template: '<a :href="$attrs.href"><slot /></a>' },
                    KtpLangsungBadge: { template: '<span data-testid="ktp-badge">KTP Langsung</span>' },
                },
            },
        });

        expect(wrapper.text()).toContain('TKT-2026-0099');
        expect(wrapper.text()).toContain('Aula MAN 2 Batam');
        expect(wrapper.text()).toContain('08:30 - 11:30 WIB');
        expect(wrapper.text()).toContain('9 hari lagi');
        expect(wrapper.find('[data-testid="ktp-badge"]').exists()).toBe(true);
    });
});

describe('StudentUpcomingSchedules.vue', () => {
    it('merender daftar sesi terbuka sekolah dengan sisa kuota', () => {
        const schedules: SchoolScheduleItem[] = [
            {
                id: 1,
                date: '2026-11-05',
                quota: 50,
                taken: 30,
                remaining_quota: 20,
                location: 'Ruang Multimedia',
                is_open: true,
            },
        ];

        const wrapper = mount(StudentUpcomingSchedules, {
            props: { schedules },
            global: {
                stubs: { Link: { template: '<a :href="$attrs.href"><slot /></a>' } },
            },
        });

        expect(wrapper.text()).toContain('Ruang Multimedia');
        expect(wrapper.text()).toContain('20 kursi tersisa');
    });
});

describe('StudentGuidelines.vue', () => {
    it('merender seluruh butir panduan persiapan hari-H', () => {
        const guidelines = [
            'Bawa dokumen fisik asli KK dan Akta.',
            'Kenakan seragam sekolah rapi berkerah.',
        ];

        const wrapper = mount(StudentGuidelines, {
            props: { guidelines },
        });

        expect(wrapper.text()).toContain('Bawa dokumen fisik asli KK dan Akta.');
        expect(wrapper.text()).toContain('Kenakan seragam sekolah rapi berkerah.');
    });
});
