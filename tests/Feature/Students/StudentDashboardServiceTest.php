<?php

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentDocument;
use App\Domains\Students\Services\StudentDashboardService;
use App\Models\User;
use Database\Seeders\AccessSeeder;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
});

test('menghasilkan state NO_PROFILE jika siswa belum memiliki record student', function () {
    $user = User::factory()->create()->assignRole('siswa');
    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['student']['has_profile'])->toBeFalse()
        ->and($data['smart_hero']['state'])->toBe('NO_PROFILE')
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.show', absolute: false))
        ->and($data['stepper']['current_step'])->toBe(2)
        ->and($data['stepper']['steps'][0]['status'])->toBe('completed')
        ->and($data['stepper']['steps'][1]['status'])->toBe('current');
});

test('menghasilkan state MISSING_DOCS jika dokumen wajib belum lengkap', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id])->assignRole('siswa');
    Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['student']['has_profile'])->toBeTrue()
        ->and($data['smart_hero']['state'])->toBe('MISSING_DOCS')
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.show', absolute: false))
        ->and($data['stepper']['current_step'])->toBe(2)
        ->and($data['stepper']['steps'][1]['status'])->toBe('current');
});

test('menghasilkan state NO_REGISTRATION jika data dan dokumen lengkap tetapi belum memilih sesi', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id])->assignRole('siswa');
    $student = Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'kk', 'path' => 'docs/kk.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'akta_lahir', 'path' => 'docs/akta.pdf', 'mime' => 'application/pdf', 'size' => 1024]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['student']['has_profile'])->toBeTrue()
        ->and($data['smart_hero']['state'])->toBe('NO_REGISTRATION')
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.sessions.index', absolute: false))
        ->and($data['stepper']['current_step'])->toBe(3)
        ->and($data['stepper']['steps'][1]['status'])->toBe('completed')
        ->and($data['stepper']['steps'][2]['status'])->toBe('current');
});

test('menghasilkan state MENUNGGU_VERIFIKASI jika pendaftaran aktif dalam antrean verifikasi', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id])->assignRole('siswa');
    $student = Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'kk', 'path' => 'docs/kk.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'akta_lahir', 'path' => 'docs/akta.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'date' => now()->addDays(5)]);
    RegistrationStudent::factory()->create([
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => RegistrationStatus::MenungguVerifikasi,
    ]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['smart_hero']['state'])->toBe('MENUNGGU_VERIFIKASI')
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.sessions.index', absolute: false))
        ->and($data['stepper']['current_step'])->toBe(3);
});

test('menghasilkan state PERBAIKAN jika verifikator meminta revisi berkas', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id])->assignRole('siswa');
    $student = Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'kk', 'path' => 'docs/kk.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'akta_lahir', 'path' => 'docs/akta.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'date' => now()->addDays(5)]);
    $deadline = now()->addDays(2);
    RegistrationStudent::factory()->create([
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => RegistrationStatus::PerluPerbaikan,
        'note' => 'Foto KK buram, tolong unggah ulang.',
        'fix_deadline_at' => $deadline,
    ]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['smart_hero']['state'])->toBe('PERBAIKAN')
        ->and($data['smart_hero']['variant'])->toBe('rose')
        ->and($data['smart_hero']['notes'])->toBe('Foto KK buram, tolong unggah ulang.')
        ->and($data['smart_hero']['deadline_at'])->not->toBeNull()
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.show', absolute: false))
        ->and($data['stepper']['steps'][2]['status'])->toBe('error');
});

test('menghasilkan state TERDAFTAR jika pendaftaran disetujui', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id])->assignRole('siswa');
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'birth_date' => now()->subYears(18),
    ]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'kk', 'path' => 'docs/kk.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'akta_lahir', 'path' => 'docs/akta.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'date' => now()->addDays(5)]);
    $entry = RegistrationStudent::factory()->create([
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => RegistrationStatus::Terdaftar,
        'ticket_code' => 'TKT-TEST-001',
    ]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['smart_hero']['state'])->toBe('TERDAFTAR')
        ->and($data['active_entry']['ticket_code'])->toBe('TKT-TEST-001')
        ->and($data['active_entry']['direct_ktp'])->toBeTrue()
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.tickets.show', $entry->id, absolute: false))
        ->and($data['stepper']['current_step'])->toBe(3)
        ->and($data['stepper']['steps'][2]['status'])->toBe('completed');
});

test('menghasilkan state SUDAH_REKAMAN jika biometrik selesai', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id])->assignRole('siswa');
    $student = Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'kk', 'path' => 'docs/kk.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    StudentDocument::create(['student_id' => $student->id, 'type' => 'akta_lahir', 'path' => 'docs/akta.pdf', 'mime' => 'application/pdf', 'size' => 1024]);
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'date' => now()->subDay()]);
    $entry = RegistrationStudent::factory()->create([
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => RegistrationStatus::SudahRekaman,
        'ticket_code' => 'TKT-TEST-002',
    ]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['smart_hero']['state'])->toBe('SUDAH_REKAMAN')
        ->and($data['stepper']['current_step'])->toBe(4)
        ->and($data['stepper']['steps'][3]['status'])->toBe('completed');
});
