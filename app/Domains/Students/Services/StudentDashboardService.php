<?php

namespace App\Domains\Students\Services;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Students\Models\Student;
use App\Models\User;

class StudentDashboardService
{
    public function __construct(
        private readonly EligibilityService $eligibility,
    ) {}

    /**
     * Menghasilkan struktur payload lengkap untuk halaman Beranda Siswa (Student Dashboard).
     *
     * @return array{
     *     student: array{id: int|null, name: string, nik: string|null, nisn: string|null, school_name: string, has_profile: bool},
     *     stepper: array{current_step: int, steps: list<array{id: int, title: string, status: string, description: string}>},
     *     smart_hero: array{state: string, variant: string, title: string, subtitle: string, badge: string, cta_label: string, cta_url: string, deadline_at: string|null, notes: string|null},
     *     active_entry: array{id: int, status: string, status_label: string, ticket_code: string|null, schedule_date: string, time_window: string, location: string, direct_ktp: bool, days_remaining: int}|null,
     *     school_schedules: list<array{id: int, date: string, quota: int, taken: int, remaining_quota: int, location: string, is_open: bool}>,
     *     guidelines: list<string>
     * }
     */
    public function forUser(User $user): array
    {
        /** @var Student|null $student */
        $student = $user->student()->with(['documents', 'registrationEntries.schedule.school'])->first();

        $studentInfo = [
            'id' => $student?->id,
            'name' => $student !== null ? $student->name : $user->name,
            'nik' => $student?->nik,
            'nisn' => $student?->nisn,
            'school_name' => $student?->school !== null ? $student->school->name : ($user->school !== null ? $user->school->name : '-'),
            'has_profile' => $student !== null,
        ];

        $uploadedTypes = $student ? $student->documents->pluck('type')->all() : [];
        $hasMandatoryDocs = in_array('kk', $uploadedTypes, true)
            && (in_array('akta_lahir', $uploadedTypes, true) || in_array('akta', $uploadedTypes, true));

        /** @var RegistrationStudent|null $activeEntry */
        $activeEntry = $student?->registrationEntries->first(fn (RegistrationStudent $e) => $e->status->isActive());

        [$stepper, $smartHero] = $this->determineJourney($student, $hasMandatoryDocs, $activeEntry);

        $activeEntryData = null;
        if ($activeEntry !== null && $activeEntry->schedule !== null) {
            $schedule = $activeEntry->schedule;
            $daysRemaining = (int) now()->startOfDay()->diffInDays($schedule->date->startOfDay(), false);

            $activeEntryData = [
                'id' => $activeEntry->id,
                'status' => $activeEntry->status->value,
                'status_label' => $activeEntry->status->label(),
                'ticket_code' => in_array($activeEntry->status, [RegistrationStatus::Terdaftar, RegistrationStatus::SudahRekaman], true) ? $activeEntry->ticket_code : null,
                'schedule_date' => $schedule->date->toDateString(),
                'time_window' => substr($schedule->start_time, 0, 5).' - '.substr($schedule->end_time, 0, 5).' WIB',
                'location' => $schedule->location,
                'direct_ktp' => $this->eligibility->directKtp($student, $schedule->date),
                'days_remaining' => max(0, $daysRemaining),
            ];
        }

        $schoolSchedules = [];
        if ($user->school_id !== null) {
            $schoolSchedules = Schedule::query()
                ->where('school_id', $user->school_id)
                ->whereNull('cancelled_at')
                ->where('date', '>=', now()->toDateString())
                ->withCount(['entries as taken_count' => fn ($q) => $q->withoutGlobalScope('owner')->whereIn('status', RegistrationStatus::values(RegistrationStatus::holdingSeat()))])
                ->orderBy('date')
                ->limit(5)
                ->get()
                ->map(fn (Schedule $s) => [
                    'id' => $s->id,
                    'date' => $s->date->toDateString(),
                    'quota' => $s->quota,
                    'taken' => (int) $s->taken_count,
                    'remaining_quota' => max(0, $s->quota - (int) $s->taken_count),
                    'location' => $s->location,
                    'is_open' => (int) $s->taken_count < $s->quota,
                ])
                ->values()
                ->all();
        }

        $guidelines = [
            'Bawa dokumen fisik asli: Kartu Keluarga (KK) dan Akta Kelahiran / Ijazah.',
            'Kenakan seragam sekolah rapi berkerah (bukan kaos oblong).',
            'Hadir di lokasi perekaman minimal 15 menit sebelum sesi dimulai.',
            'Tunjukkan QR Code e-Tiket pada smartphone Anda kepada petugas piket sekolah.',
        ];

        return [
            'student' => $studentInfo,
            'stepper' => $stepper,
            'smart_hero' => $smartHero,
            'active_entry' => $activeEntryData,
            'school_schedules' => $schoolSchedules,
            'guidelines' => $guidelines,
        ];
    }

    /**
     * @return array{
     *     0: array{current_step: int, steps: list<array{id: int, title: string, status: string, description: string}>},
     *     1: array{state: string, variant: string, title: string, subtitle: string, badge: string, cta_label: string, cta_url: string, deadline_at: string|null, notes: string|null}
     * }
     */
    private function determineJourney(?Student $student, bool $hasMandatoryDocs, ?RegistrationStudent $entry): array
    {
        if ($student === null) {
            return [
                [
                    'current_step' => 2,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'current', 'description' => 'Lengkapi biodata'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'upcoming', 'description' => 'Pilih jadwal sesi'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'NO_PROFILE',
                    'variant' => 'amber',
                    'title' => 'Lengkapi Data Diri Anda',
                    'subtitle' => 'Biodata Anda belum tersimpan. Lengkapi data diri untuk memulai proses pendaftaran KTP-el.',
                    'badge' => 'Perlu Tindakan',
                    'cta_label' => 'Lengkapi Data Diri',
                    'cta_url' => route('me.show', absolute: false),
                    'deadline_at' => null,
                    'notes' => null,
                ],
            ];
        }

        if (! $hasMandatoryDocs) {
            return [
                [
                    'current_step' => 2,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'current', 'description' => 'Unggah dokumen wajib'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'upcoming', 'description' => 'Pilih jadwal sesi'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'MISSING_DOCS',
                    'variant' => 'amber',
                    'title' => 'Unggah Dokumen Persyaratan',
                    'subtitle' => 'Foto/scan Kartu Keluarga dan Akta Kelahiran wajib diunggah sebelum dapat memilih sesi rekaman.',
                    'badge' => 'Dokumen Kurang',
                    'cta_label' => 'Unggah Berkas',
                    'cta_url' => route('me.show', absolute: false),
                    'deadline_at' => null,
                    'notes' => null,
                ],
            ];
        }

        if ($entry === null) {
            return [
                [
                    'current_step' => 3,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Biodata & berkas lengkap'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'current', 'description' => 'Pilih sesi rekaman'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'NO_REGISTRATION',
                    'variant' => 'blue',
                    'title' => 'Pilih Jadwal Sesi Rekaman',
                    'subtitle' => 'Data diri dan dokumen Anda sudah lengkap. Segera pilih sesi perekaman KTP-el di sekolah Anda.',
                    'badge' => 'Siap Mendaftar',
                    'cta_label' => 'Pilih Sesi Sekarang',
                    'cta_url' => route('me.sessions.index', absolute: false),
                    'deadline_at' => null,
                    'notes' => null,
                ],
            ];
        }

        return match ($entry->status) {
            RegistrationStatus::MenungguVerifikasi => [
                [
                    'current_step' => 3,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Biodata & berkas lengkap'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'current', 'description' => 'Menunggu verifikasi'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'MENUNGGU_VERIFIKASI',
                    'variant' => 'indigo',
                    'title' => 'Berkas Sedang Diverifikasi',
                    'subtitle' => 'Petugas sedang memeriksa kelengkapan data dan dokumen Anda. Anda akan menerima notifikasi setelah diverifikasi.',
                    'badge' => 'Dalam Antrean',
                    'cta_label' => 'Lihat Sesi Rekaman',
                    'cta_url' => route('me.sessions.index', absolute: false),
                    'deadline_at' => null,
                    'notes' => null,
                ],
            ],
            RegistrationStatus::PerluPerbaikan => [
                [
                    'current_step' => 3,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Biodata & berkas lengkap'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'error', 'description' => 'Perlu perbaikan berkas'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'PERBAIKAN',
                    'variant' => 'rose',
                    'title' => 'Berkas Perlu Diperbaiki',
                    'subtitle' => 'Verifikator meminta revisi pada data atau dokumen pendaftaran Anda. Silakan periksa catatan dan perbaiki sebelum batas waktu.',
                    'badge' => 'Perlu Perbaikan',
                    'cta_label' => 'Perbaiki Berkas',
                    'cta_url' => route('me.show', absolute: false),
                    'deadline_at' => $entry->fix_deadline_at?->toISOString(),
                    'notes' => $entry->note,
                ],
            ],
            RegistrationStatus::Terdaftar => [
                [
                    'current_step' => 3,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Biodata & berkas lengkap'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'completed', 'description' => 'Pendaftaran disetujui'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'current', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'TERDAFTAR',
                    'variant' => 'emerald',
                    'title' => 'Pendaftaran Disetujui! e-Tiket Anda Siap',
                    'subtitle' => 'Pendaftaran Anda telah diverifikasi dan disetujui. Buka dan unduh e-tiket QR untuk ditunjukkan saat hari-H.',
                    'badge' => 'Terdaftar',
                    'cta_label' => 'Buka e-Tiket QR',
                    'cta_url' => route('me.tickets.show', $entry->id, absolute: false),
                    'deadline_at' => null,
                    'notes' => null,
                ],
            ],
            RegistrationStatus::SudahRekaman => [
                [
                    'current_step' => 4,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Biodata & berkas lengkap'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'completed', 'description' => 'Pendaftaran disetujui'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'completed', 'description' => 'Perekaman selesai'],
                    ],
                ],
                [
                    'state' => 'SUDAH_REKAMAN',
                    'variant' => 'emerald',
                    'title' => 'Perekaman Biometrik Selesai',
                    'subtitle' => 'Terima kasih telah melakukan perekaman biometrik KTP-el. Data Anda sedang diproses oleh Disdukcapil.',
                    'badge' => 'Selesai Rekam',
                    'cta_label' => 'Lihat Bukti Rekam',
                    'cta_url' => route('me.tickets.show', $entry->id, absolute: false),
                    'deadline_at' => null,
                    'notes' => null,
                ],
            ],
            default => [
                [
                    'current_step' => 3,
                    'steps' => [
                        ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
                        ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Biodata & berkas lengkap'],
                        ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'error', 'description' => 'Pendaftaran ditolak'],
                        ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Pelaksanaan hari-H'],
                    ],
                ],
                [
                    'state' => 'DITOLAK',
                    'variant' => 'zinc',
                    'title' => 'Pendaftaran Ditolak',
                    'subtitle' => $entry->note ?? 'Pendaftaran tidak memenuhi kualifikasi.',
                    'badge' => 'Ditolak',
                    'cta_label' => 'Lihat Sesi Rekaman',
                    'cta_url' => route('me.sessions.index', absolute: false),
                    'deadline_at' => null,
                    'notes' => $entry->note,
                ],
            ],
        };
    }
}
