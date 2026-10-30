import DashboardCalendarDialog from '@/Components/dashboard/DashboardCalendarDialog.vue';
import DashboardCharts from '@/Components/dashboard/DashboardCharts.vue';
import DashboardKpiCards from '@/Components/dashboard/DashboardKpiCards.vue';
import DashboardMiniCalendar from '@/Components/dashboard/DashboardMiniCalendar.vue';
import DashboardQuickActions from '@/Components/dashboard/DashboardQuickActions.vue';
import DashboardUpcomingTable from '@/Components/dashboard/DashboardUpcomingTable.vue';
import type {
    CalendarEventItem,
    DashboardCharts as DashboardChartsType,
    KpiCardItem,
    QuickActionItem,
    UpcomingScheduleRow,
} from '@/types/dashboard';
import { config, flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { permissions } = vi.hoisted(() => ({ permissions: [] as string[] }));

vi.mock('@/Components/shared/ApexChart.vue', () => ({
    default: {
        name: 'ApexChart',
        props: ['type', 'height', 'options', 'series'],
        template: '<div data-testid="mock-apex-chart" :data-type="type"></div>',
    },
}));

// Mock Link from @inertiajs/vue3
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        name: 'Link',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => ({ props: { auth: { user: null, permissions } } }),
}));

beforeEach(() => {
    permissions.length = 0;
});

describe('DashboardKpiCards', () => {
    it('merender 5 kartu KPI dengan label, format angka, dan badge persentase', () => {
        const items: KpiCardItem[] = [
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
            {
                key: 'recorded',
                label: 'Sudah Rekam KTP-el',
                value: 600,
                percentage: 48,
                variant: 'amber',
                icon: 'id-card',
            },
            {
                key: 'in_process',
                label: 'Siap Dijadwalkan',
                value: 300,
                percentage: 24,
                variant: 'rose',
                icon: 'clock',
            },
            {
                key: 'unscheduled',
                label: 'Belum Dijadwalkan',
                value: 150,
                percentage: 12,
                variant: 'slate',
                icon: 'file-text',
            },
        ];

        const wrapper = mount(DashboardKpiCards, { props: { items } });

        expect(wrapper.text()).toContain('Total Siswa Terdaftar');
        expect(wrapper.text()).toContain('1.250');
        expect(wrapper.text()).toContain('siswa');
        expect(wrapper.text()).toContain('64%');
        expect(wrapper.text()).toContain('48%');
        expect(wrapper.findAll('.group')).toHaveLength(5);
    });
});

describe('DashboardQuickActions', () => {
    it('merender tombol aksi cepat dengan tautan dan label yang sesuai', () => {
        const items: QuickActionItem[] = [
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

        const wrapper = mount(DashboardQuickActions, { props: { items } });

        expect(wrapper.text()).toContain('Aksi Cepat');
        expect(wrapper.text()).toContain('Tambah Pendaftaran');
        expect(wrapper.text()).toContain('Buat Jadwal');
        const links = wrapper.findAll('a');
        expect(links[0].attributes('href')).toBe('/pendaftaran/buat');
        expect(links[1].attributes('href')).toBe('/jadwal/buat');
    });

    it('aksi Pindai Tiket memakai ikon scan-line yang terpetakan', () => {
        const wrapper = mount(DashboardQuickActions, {
            props: {
                items: [
                    {
                        label: 'Pindai Tiket',
                        href: '/jadwal',
                        icon: 'scan-line',
                        variant: 'amber',
                    },
                ],
            },
        });

        expect(wrapper.find('a svg').attributes('class')).toMatch(/scan-line/);
    });

    it('tidak merender apapun jika items kosong', () => {
        const wrapper = mount(DashboardQuickActions, { props: { items: [] } });
        expect(wrapper.find('[role="region"]').exists()).toBe(false);
    });
});

describe('DashboardCharts', () => {
    it('merender 3 kontainer visualisasi grafik analitik', () => {
        const charts: DashboardChartsType = {
            school_recap: {
                categories: ['SMAN 16', 'SMAN 28', 'SMKN 5'],
                series: [{ name: 'Pendaftar', data: [120, 95, 80] }],
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

        const wrapper = mount(DashboardCharts, {
            props: { charts, isOperator: false },
        });

        expect(wrapper.text()).toContain('Rekap Pendaftar per Sekolah');
        expect(wrapper.text()).toContain('Status Perekaman KTP-el');
        expect(wrapper.text()).toContain('Tren Perekaman Tahunan');
    });

    it('menampilkan judul kesiapan berkas jika pengguna adalah operator sekolah', () => {
        const charts: DashboardChartsType = {
            school_recap: {
                categories: ['KK Lengkap', 'Akta Lengkap', 'Keduanya'],
                series: [{ name: 'Siswa', data: [50, 40, 35] }],
            },
            recording_status: {
                labels: ['Sudah Rekam', 'Dijadwalkan', 'Belum Dijadwalkan'],
                series: [20, 15, 10],
                total: 45,
            },
            monthly_trend: {
                categories: ['Jan', 'Feb'],
                series: [{ name: 'Terdaftar', data: [20, 25] }],
            },
        };

        const wrapper = mount(DashboardCharts, {
            props: { charts, isOperator: true },
        });

        expect(wrapper.text()).toContain('Kesiapan Berkas Siswa');
    });
});

describe('DashboardUpcomingTable', () => {
    it('merender daftar jadwal terdekat dengan badge status', () => {
        const schedules: UpcomingScheduleRow[] = [
            {
                id: 1,
                date: '2026-10-14',
                school_name: 'SMAN 16 Batam',
                participants: 45,
                quota: 50,
                waiting: 3,
                present: 12,
                location: 'Aula SMAN 16',
                status: 'Hari Ini',
            },
            {
                id: 2,
                date: '2026-10-18',
                school_name: 'SMKN 5 Batam',
                participants: 60,
                quota: 80,
                waiting: 0,
                present: 0,
                location: 'Ruang Serbaguna',
                status: 'Dijadwalkan',
            },
        ];

        const wrapper = mount(DashboardUpcomingTable, { props: { schedules } });

        expect(wrapper.text()).toContain('Jadwal Perekaman Terdekat');
        expect(wrapper.text()).toContain('SMAN 16 Batam');
        expect(wrapper.text()).toContain('45/50 kursi');
        expect(wrapper.text()).toContain('3 menunggu verifikasi');
        expect(wrapper.text()).toContain('12 sudah rekaman');
        expect(wrapper.text()).toContain('60/80 kursi');
        expect(wrapper.text()).toContain('Hari Ini');
        expect(wrapper.text()).toContain('SMKN 5 Batam');
        expect(wrapper.text()).toContain('Dijadwalkan');
    });

    it('tautan Pindai hanya pada baris Hari Ini bagi pemegang rekaman.pindai', () => {
        const baris = (id: number, status: string): UpcomingScheduleRow => ({
            id,
            date: '2026-10-14',
            school_name: 'SMAN 16',
            participants: 1,
            quota: 30,
            waiting: 0,
            present: 0,
            location: 'Aula',
            status,
        });
        const schedules = [baris(1, 'Hari Ini'), baris(2, 'Dijadwalkan')];
        const route = (name: string, id?: number) => `/${name}/${id}`;
        vi.stubGlobal('route', route);
        config.global.mocks = { route };

        const tanpa = mount(DashboardUpcomingTable, { props: { schedules } });
        expect(tanpa.text()).not.toContain('Pindai');

        permissions.push('rekaman.pindai');
        const dengan = mount(DashboardUpcomingTable, { props: { schedules } });
        const tautan = dengan
            .findAll('a')
            .filter((a) => a.text().includes('Pindai'));
        expect(tautan).toHaveLength(1);
        expect(tautan[0].attributes('href')).toBe('/schedules.scan.show/1');
        vi.unstubAllGlobals();
    });

    it('merender empty state jika tidak ada jadwal', () => {
        const wrapper = mount(DashboardUpcomingTable, {
            props: { schedules: [] },
        });

        expect(wrapper.text()).toContain(
            'Belum ada jadwal perekaman mendatang',
        );
    });
});

describe('DashboardMiniCalendar & Dialog', () => {
    it('merender kalender mini dengan navigasi bulan dan indikator kegiatan', () => {
        const todayStr = '2026-10-15';
        const events: CalendarEventItem[] = [
            {
                id: 10,
                date: todayStr,
                school_name: 'SMAN 16 Batam',
                location: 'Aula',
                participants: 40,
                status: 'Hari Ini',
            },
        ];

        const wrapper = mount(DashboardMiniCalendar, { props: { events } });

        expect(wrapper.text()).toContain('Kalender Kegiatan');
        expect(
            wrapper.find('button[aria-label="Bulan sebelumnya"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('button[aria-label="Bulan berikutnya"]').exists(),
        ).toBe(true);
    });

    it('merender dialog rincian agenda ketika dibuka', async () => {
        const events: CalendarEventItem[] = [
            {
                id: 10,
                date: '2026-10-15',
                school_name: 'SMAN 16 Batam',
                location: 'Aula Sekolah',
                participants: 50,
                status: 'Dijadwalkan',
            },
        ];

        mount(DashboardCalendarDialog, {
            props: {
                open: true,
                date: '2026-10-15',
                events,
            },
            attachTo: document.body,
        });

        await flushPromises();

        expect(document.body.textContent).toContain('Agenda Kegiatan');
        expect(document.body.textContent).toContain('SMAN 16 Batam');
        expect(document.body.textContent).toContain('Aula Sekolah');
        expect(document.body.textContent).toContain('50 peserta');
        expect(document.body.textContent).toContain('Dijadwalkan');
    });
});
