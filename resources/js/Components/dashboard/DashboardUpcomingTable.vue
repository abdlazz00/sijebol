<script setup lang="ts">
import { usePermission } from '@/composables/usePermission';
import { formatDate } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import type { UpcomingScheduleRow } from '@/types/dashboard';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, MapPin, ScanLine, Users } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    schedules: UpcomingScheduleRow[];
}>();

const { can } = usePermission();
// Kolom Aksi hanya muncul bila ada sesi hari ini dan pengguna boleh memindai.
const showScan = computed(
    () => can(PERMISSIONS.RekamanPindai) && props.schedules.some((s) => s.status === 'Hari Ini'),
);

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'Hari Ini':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 border-blue-200/80 dark:border-blue-800';
        case 'Terlaksana':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 border-emerald-200/80 dark:border-emerald-800';
        case 'Dijadwalkan':
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border-amber-200/80 dark:border-amber-800';
    }
}
</script>

<template>
    <div
        class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs"
        role="region"
        aria-label="Tabel Jadwal Perekaman Terdekat"
    >
        <div class="flex items-center justify-between gap-2 border-b border-border/50 pb-3">
            <div class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        Jadwal Perekaman Terdekat
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Agenda layanan jemput bola mendatang
                    </p>
                </div>
            </div>

            <Link
                href="/jadwal"
                class="inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary/80 hover:underline"
            >
                <span>Lihat Semua</span>
                <ChevronRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table v-if="schedules.length" class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-border/60 text-muted-foreground">
                        <th class="py-2.5 pr-2 font-medium">No</th>
                        <th class="py-2.5 px-3 font-medium">Tanggal</th>
                        <th class="py-2.5 px-3 font-medium">Sekolah</th>
                        <th class="py-2.5 px-3 font-medium">Kursi</th>
                        <th class="py-2.5 px-3 font-medium">Lokasi</th>
                        <th class="py-2.5 pl-3 text-right font-medium">Status</th>
                        <th v-if="showScan" class="py-2.5 pl-3 text-right font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <tr
                        v-for="(row, idx) in schedules"
                        :key="row.id || idx"
                        class="group transition-colors hover:bg-muted/40"
                    >
                        <td class="py-3 pr-2 font-medium text-muted-foreground">
                            {{ idx + 1 }}
                        </td>
                        <td class="py-3 px-3 font-medium whitespace-nowrap text-foreground">
                            {{ formatDate(row.date) }}
                        </td>
                        <td class="py-3 px-3 font-medium text-foreground">
                            {{ row.school_name || row.school || '-' }}
                        </td>
                        <td class="py-3 px-3 whitespace-nowrap text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <Users class="size-3.5 text-muted-foreground/70" aria-hidden="true" />
                                <span>{{ row.participants }}/{{ row.quota }} kursi</span>
                            </span>
                            <span class="block text-xs leading-4">
                                {{ row.waiting }} menunggu verifikasi · {{ row.present }} sudah rekaman
                            </span>
                        </td>
                        <td class="py-3 px-3 text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <MapPin class="size-3.5 shrink-0 text-muted-foreground/70" aria-hidden="true" />
                                <span class="max-w-[150px] truncate" :title="row.location">
                                    {{ row.location || '-' }}
                                </span>
                            </span>
                        </td>
                        <td class="py-3 pl-3 text-right whitespace-nowrap">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold',
                                    statusBadgeClass(row.status),
                                ]"
                            >
                                <span
                                    v-if="row.status === 'Hari Ini'"
                                    class="relative flex size-2"
                                >
                                    <span
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
                                    />
                                    <span
                                        class="relative inline-flex size-2 rounded-full bg-blue-600"
                                    />
                                </span>
                                {{ row.status }}
                            </span>
                        </td>
                        <td v-if="showScan" class="py-3 pl-3 text-right whitespace-nowrap">
                            <Link
                                v-if="row.status === 'Hari Ini'"
                                :href="route('jadwal.pindai.show', row.id)"
                                class="inline-flex min-h-9 items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                            >
                                <ScanLine class="size-3.5" aria-hidden="true" />
                                Pindai
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-else
                class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
            >
                <CalendarDays class="size-8 opacity-40 mb-2" aria-hidden="true" />
                <p class="text-sm font-medium">Belum ada jadwal perekaman mendatang</p>
                <p class="text-xs">Jadwal baru yang ditambahkan staf kecamatan akan tampil di sini.</p>
            </div>
        </div>
    </div>
</template>
