<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import StudentStepper from '@/Components/student-dashboard/StudentStepper.vue';
import StudentSmartHero from '@/Components/student-dashboard/StudentSmartHero.vue';
import StudentActiveTicketCard from '@/Components/student-dashboard/StudentActiveTicketCard.vue';
import StudentUpcomingSchedules from '@/Components/student-dashboard/StudentUpcomingSchedules.vue';
import StudentGuidelines from '@/Components/student-dashboard/StudentGuidelines.vue';
import type { StudentDashboardProps } from '@/types/student-dashboard';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { User, Calendar, GraduationCap, ArrowRight } from '@lucide/vue';

defineProps<StudentDashboardProps>();
</script>

<template>
    <AppLayout>
        <Head title="Beranda Siswa" />

        <div class="space-y-6">
            <!-- Header Sambutan Personal Siswa -->
            <div class="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-3.5">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        <GraduationCap class="size-6" aria-hidden="true" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-lg font-bold text-slate-900 sm:text-xl dark:text-slate-100">
                                Hai, {{ student.name }}
                            </h1>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                Akun Siswa Aktif
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ student.school_name }}
                            <span v-if="student.nisn"> · NISN: {{ student.nisn }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px] text-xs">
                        <Link :href="route('me.show')">
                            <User class="mr-1.5 size-3.5" aria-hidden="true" />
                            Data Saya
                        </Link>
                    </Button>
                    <Button as-child variant="outline" size="sm" class="h-9 min-h-[36px] text-xs">
                        <Link :href="route('me.sessions.index')">
                            <Calendar class="mr-1.5 size-3.5" aria-hidden="true" />
                            Sesi Rekaman
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Seksi 1: Stepper Visual Alur Perekaman -->
            <StudentStepper
                :current-step="stepper.current_step"
                :steps="stepper.steps"
            />

            <!-- Seksi 2: Smart Hero Action Banner -->
            <StudentSmartHero :hero="smart_hero" />

            <!-- Seksi 3: Grid 2 Kolom (Tiket / Sesi Sekolah & Panduan) -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Kolom Kiri: Tiket Aktif ATAU Jadwal Sesi Tersedia -->
                <div class="space-y-6">
                    <StudentActiveTicketCard
                        v-if="active_entry"
                        :entry="active_entry"
                    />

                    <StudentUpcomingSchedules
                        v-if="!active_entry || active_entry.status !== 'terdaftar'"
                        :schedules="school_schedules"
                    />
                </div>

                <!-- Kolom Kanan: Panduan Persiapan Hari-H -->
                <div>
                    <StudentGuidelines :guidelines="guidelines" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
