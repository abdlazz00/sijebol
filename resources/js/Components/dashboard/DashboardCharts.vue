<script setup lang="ts">
import ApexChart from '@/Components/shared/ApexChart.vue';
import type { DashboardCharts } from '@/types/dashboard';
import { BarChart3, PieChart, TrendingUp } from '@lucide/vue';
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';

const props = defineProps<{
    charts: DashboardCharts;
    isOperator?: boolean;
}>();

// 1. Bar Chart Options
const barOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        fontFamily: 'inherit',
        animations: { enabled: true },
    },
    plotOptions: {
        bar: {
            borderRadius: 6,
            columnWidth: '50%',
            distributed: true,
        },
    },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#64748b'],
    dataLabels: { enabled: false },
    legend: { show: false },
    xaxis: {
        categories: props.charts.school_recap.categories,
        labels: {
            style: { fontSize: '11px', colors: '#64748b' },
            trim: true,
            maxHeight: 60,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { fontSize: '11px', colors: '#64748b' },
            formatter: (val: number) => `${Math.round(val)}`,
        },
    },
    grid: {
        borderColor: '#e2e8f0',
        strokeDashArray: 4,
        yaxis: { lines: { show: true } },
    },
    tooltip: {
        theme: 'light',
        y: { formatter: (val: number) => `${val} siswa` },
    },
}));

// 2. Donut Chart Options
const donutOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'inherit',
        animations: { enabled: true },
    },
    labels: props.charts.recording_status.labels,
    colors: ['#10b981', '#3b82f6', '#f59e0b'],
    stroke: { width: 2, colors: ['#ffffff'] },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: true,
                    name: {
                        show: true,
                        fontSize: '13px',
                        fontWeight: 500,
                        color: '#64748b',
                    },
                    value: {
                        show: true,
                        fontSize: '22px',
                        fontWeight: 700,
                        color: '#0f172a',
                        formatter: (val: string) => Number(val).toLocaleString('id-ID'),
                    },
                    total: {
                        show: true,
                        label: 'Total Siswa',
                        fontSize: '12px',
                        fontWeight: 500,
                        color: '#64748b',
                        formatter: () =>
                            Number(props.charts.recording_status.total || 0).toLocaleString('id-ID'),
                    },
                },
            },
        },
    },
    dataLabels: { enabled: false },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        markers: { size: 6 },
        itemMargin: { horizontal: 8, vertical: 4 },
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (val: number) => {
                const total = props.charts.recording_status.total || 1;
                const pct = Math.round((val / total) * 100);
                return `${val} siswa (${pct}%)`;
            },
        },
    },
}));

// 3. Line/Area Chart Options
const lineOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        fontFamily: 'inherit',
        animations: { enabled: true },
    },
    stroke: {
        curve: 'smooth',
        width: 2.5,
    },
    colors: ['#3b82f6', '#10b981'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.35,
            opacityTo: 0.05,
            stops: [0, 95, 100],
        },
    },
    dataLabels: { enabled: false },
    xaxis: {
        categories: props.charts.monthly_trend.categories,
        labels: {
            style: { fontSize: '11px', colors: '#64748b' },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { fontSize: '11px', colors: '#64748b' },
            formatter: (val: number) => `${Math.round(val)}`,
        },
    },
    grid: {
        borderColor: '#e2e8f0',
        strokeDashArray: 4,
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        fontSize: '12px',
        markers: { size: 6 },
    },
    tooltip: {
        theme: 'light',
        y: { formatter: (val: number) => `${val} siswa` },
    },
}));
</script>

<template>
    <div
        class="grid grid-cols-1 gap-4 lg:grid-cols-3"
        role="region"
        aria-label="Visualisasi Grafik Analitik"
    >
        <!-- Card 1: Rekap Sekolah / Berkas -->
        <div
            class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs"
        >
            <div class="flex items-center justify-between gap-2 border-b border-border/50 pb-3">
                <div class="flex items-center gap-2">
                    <span
                        class="flex size-7 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400"
                    >
                        <BarChart3 class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ isOperator ? 'Kesiapan Berkas Siswa' : 'Rekap Pendaftar per Sekolah' }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ isOperator ? 'Distribusi kelengkapan dokumen' : 'Top pendaftar terbanyak' }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-3 min-h-[260px]">
                <ApexChart
                    type="bar"
                    height="260"
                    :options="barOptions"
                    :series="charts.school_recap.series"
                />
            </div>
        </div>

        <!-- Card 2: Status Perekaman (Donut) -->
        <div
            class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs"
        >
            <div class="flex items-center justify-between gap-2 border-b border-border/50 pb-3">
                <div class="flex items-center gap-2">
                    <span
                        class="flex size-7 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400"
                    >
                        <PieChart class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            Status Perekaman KTP-el
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Proporsi progres perekaman saat ini
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-3 min-h-[260px]">
                <ApexChart
                    type="donut"
                    height="260"
                    :options="donutOptions"
                    :series="charts.recording_status.series"
                />
            </div>
        </div>

        <!-- Card 3: Tren Perekaman Jan - Des (Area/Line) -->
        <div
            class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs"
        >
            <div class="flex items-center justify-between gap-2 border-b border-border/50 pb-3">
                <div class="flex items-center gap-2">
                    <span
                        class="flex size-7 items-center justify-center rounded-md bg-violet-500/10 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400"
                    >
                        <TrendingUp class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            Tren Perekaman Tahunan
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Januari – Desember tahun berjalan
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-3 min-h-[260px]">
                <ApexChart
                    type="area"
                    height="260"
                    :options="lineOptions"
                    :series="charts.monthly_trend.series"
                />
            </div>
        </div>
    </div>
</template>
