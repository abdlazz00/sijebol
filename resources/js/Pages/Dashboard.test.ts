import Dashboard from '@/Pages/Dashboard.vue';
import type {
    CalendarEventItem,
    DashboardCharts,
    KpiCardItem,
    QuickActionItem,
    UpcomingScheduleRow,
} from '@/types/dashboard';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

// Mock ApexChart
vi.mock('@/Components/shared/ApexChart.vue', () => ({
    default: {
        name: 'ApexChart',
        props: ['type', 'height', 'options', 'series'],
        template: '<div data-testid="mock-apex-chart" :data-type="type"></div>',
    },
}));

// Mock AppLayout
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: {
        name: 'AppLayout',
        props: ['title'],
        template: '<div data-testid="app-layout"><slot /></div>',
    },
}));

// Mock Inertia
const mockUser = {
    id: 1,
    name: 'Sari Wulandari',
    role: 'admin_kecamatan',
    school_id: null,
    school: null,
};

const mockNotifications = {
    latest: [
        {
            id: 1,
            title: 'Pendaftaran Baru',
            body: 'SMAN 16 mengajukan 35 siswa',
            created_at: '2026-10-13T08:00:00Z',
            read_at: null,
        },
    ],
};

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({
        props: {
            auth: { user: mockUser },
            notifications: mockNotifications,
        },
    }),
}));

describe('Dashboard Page Integration', () => {
    const kpiSample: KpiCardItem[] = [
        {
            key: 'total',
            label: 'Total Siswa Terdaftar',
            value: 1250,
            unit: 'siswa',
            variant: 'blue',
            icon: 'users',
        },
        {
            key: 'scheduled',
            label: 'Sudah Dijadwalkan',
            value: 800,
            percentage: 64,
            variant: 'green',
            icon: 'calendar-check',
        },
    ];

    const quickActionsSample: QuickActionItem[] = [
        {
            label: 'Tambah Pendaftaran',
            href: '/pendaftaran/buat',
            icon: 'plus',
            variant: 'blue',
        },
        {
            label: 'Buat Jadwal',
            href: '/jadwal/buat',
            icon: 'calendar-plus',
            variant: 'green',
        },
    ];

    const chartsSample: DashboardCharts = {
        school_recap: {
            categories: ['SMAN 16', 'SMAN 28'],
            series: [{ name: 'Pendaftar', data: [120, 95] }],
        },
        recording_status: {
            labels: ['Sudah Rekam', 'Dijadwalkan', 'Belum Dijadwalkan'],
            series: [50, 30, 20],
            total: 100,
        },
        monthly_trend: {
            categories: ['Jan', 'Feb', 'Mar'],
            series: [
                { name: 'Terdaftar', data: [30, 45, 60] },
                { name: 'Sudah Rekam', data: [10, 25, 40] },
            ],
        },
    };

    const schedulesSample: UpcomingScheduleRow[] = [
        {
            id: 1,
            date: '2026-10-14',
            school_name: 'SMAN 16 Batam',
            participants: 45,
            location: 'Aula SMAN 16',
            status: 'Hari Ini',
        },
    ];

    const calendarSample: CalendarEventItem[] = [
        {
            id: 1,
            date: '2026-10-14',
            school_name: 'SMAN 16 Batam',
            location: 'Aula SMAN 16',
            participants: 45,
            status: 'Hari Ini',
        },
    ];

    it('merender seluruh seksi dashboard secara lengkap dan harmonis', () => {
        const wrapper = mount(Dashboard, {
            props: {
                kpi: kpiSample,
                quick_actions: quickActionsSample,
                charts: chartsSample,
                schedules_table: schedulesSample,
                calendar_events: calendarSample,
            },
        });

        // Header salam
        expect(wrapper.text()).toContain('Selamat datang, Sari');
        expect(wrapper.text()).toContain('Kecamatan Sungai Beduk');

        // KPI cards
        expect(wrapper.text()).toContain('Total Siswa Terdaftar');
        expect(wrapper.text()).toContain('1.250');

        // Quick actions
        expect(wrapper.text()).toContain('Aksi Cepat');
        expect(wrapper.text()).toContain('Tambah Pendaftaran');

        // Charts
        expect(wrapper.text()).toContain('Rekap Pendaftar per Sekolah');
        expect(wrapper.text()).toContain('Status Perekaman KTP-el');

        // Table
        expect(wrapper.text()).toContain('Jadwal Perekaman Terdekat');
        expect(wrapper.text()).toContain('SMAN 16 Batam');

        // Notifications & Calendar
        expect(wrapper.text()).toContain('Notifikasi Terbaru');
        expect(wrapper.text()).toContain('Pendaftaran Baru');
        expect(wrapper.text()).toContain('Kalender Kegiatan');
    });

    it('merender fallback ke kartu summary lama jika kpi belum disediakan', () => {
        const wrapper = mount(Dashboard, {
            props: {
                summary: [
                    {
                        label: 'Menunggu verifikasi',
                        value: 5,
                        caption: 'Entri baru',
                        icon: 'clipboard-list',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Menunggu verifikasi');
    });
});
