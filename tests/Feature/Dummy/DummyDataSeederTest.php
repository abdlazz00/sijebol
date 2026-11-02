<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentDocument;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\DummyDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    School::factory()->count(3)->create();
    Storage::fake('local');
    $this->seeder = app(DummyDataSeeder::class);
});

function dummyStudents()
{
    return Student::withoutGlobalScopes()->where('nik', 'like', '9999%');
}

it('menolak berjalan di luar env local atau testing tanpa menulis apa pun', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => $this->seeder->generate(20))->toThrow(RuntimeException::class);
    expect(Student::withoutGlobalScopes()->count())->toBe(0)->and(User::withoutGlobalScopes()->count())->toBe(0);
});

it('menolak bila belum ada sekolah', function () {
    School::query()->delete();

    expect(fn () => $this->seeder->generate(20))->toThrow(RuntimeException::class, 'sekolah');
});

it('menolak bila role belum dibuat oleh AccessSeeder', function () {
    DB::table('roles')->delete();

    expect(fn () => $this->seeder->generate(20))->toThrow(RuntimeException::class, 'AccessSeeder');
});

it('mengisi siswa, sesi, dan pendaftaran tanpa mengubah sekolah dan tanpa log aktivitas', function () {
    $schools = School::query()->orderBy('id')->pluck('name', 'id')->all();
    $logs = Activity::query()->count();

    $result = $this->seeder->generate(120);

    expect($result['students'])->toBe(120)
        ->and(dummyStudents()->count())->toBe(120)
        ->and(Schedule::withoutGlobalScopes()->count())->toBe(3 * 8)
        ->and(School::query()->orderBy('id')->pluck('name', 'id')->all())->toBe($schools)
        ->and(Activity::query()->count())->toBe($logs);
});

it('menjaga konsistensi pendaftaran: satu aktif per siswa, kuota, waktu sesi, tiket, dan umur', function () {
    $this->seeder->generate(150);
    $eligibility = app(EligibilityService::class);
    $today = Schedule::today();

    $entries = RegistrationStudent::withoutGlobalScopes()->with(['student', 'schedule', 'histories'])->get();
    expect($entries)->not->toBeEmpty();

    $activePerStudent = $entries->filter(fn ($e) => $e->status->isActive())->groupBy('student_id');
    expect($activePerStudent->filter(fn ($g) => $g->count() > 1))->toBeEmpty();

    foreach (Schedule::withoutGlobalScopes()->get() as $schedule) {
        expect($schedule->seatsTaken())->toBeLessThanOrEqual($schedule->quota);
    }

    foreach ($entries as $e) {
        expect($e->school_id)->toBe($e->student->school_id)->and($e->school_id)->toBe($e->schedule->school_id);
        expect($eligibility->age($e->student, $e->schedule->date))->toBeGreaterThanOrEqual(16);
        expect($e->histories->last()->to_status)->toBe($e->status);
        if ($e->status === S::SudahRekaman) {
            expect($e->schedule->date->toDateString())->toBeLessThanOrEqual($today)->and($e->scanned_at)->not->toBeNull();
        }
        if (in_array($e->status, [S::Terdaftar, S::SudahRekaman, S::TidakHadir, S::BelumRekaman], true)) {
            expect($e->ticket_code)->toHaveLength(32);
        }
        if ($e->schedule->isCancelled()) {
            expect($e->status)->toBe(S::Dibatalkan);
        }
    }
});

it('memberi dokumen kepada siswa yang punya pendaftaran dan akun kepada sebagian siswa', function () {
    $this->seeder->generate(200);

    $registered = RegistrationStudent::withoutGlobalScopes()->pluck('student_id')->unique();
    expect(StudentDocument::query()->whereIn('student_id', $registered)->where('type', 'kk')->count())->toBe($registered->count())
        ->and(StudentDocument::query()->whereIn('student_id', $registered)->where('type', 'akta_lahir')->count())->toBe($registered->count());
    $doc = StudentDocument::query()->first();
    Storage::disk('local')->assertExists($doc->path);

    $accounts = dummyStudents()->whereNotNull('user_id')->count();
    expect($accounts)->toBeGreaterThan(0)->toBeLessThan(200);
    $user = User::withoutGlobalScopes()->find(dummyStudents()->whereNotNull('user_id')->first()->user_id);
    expect($user->email)->toEndWith('@dummy.sijebol.test')->and($user->hasRole('siswa'))->toBeTrue();
});

it('membuat satu operator sekolah per sekolah dan satu admin kecamatan dummy', function () {
    $this->seeder->generate(30);

    $operators = User::withoutGlobalScopes()->where('email', 'like', 'operator.%@dummy.sijebol.test')->get();
    expect($operators)->toHaveCount(3)
        ->and($operators->every(fn ($u) => $u->hasRole('operator-sekolah') && $u->school_id !== null))->toBeTrue()
        ->and(User::withoutGlobalScopes()->where('email', 'admin.dummy@dummy.sijebol.test')->first()->hasRole('admin-kecamatan'))->toBeTrue();
});

it('aman dijalankan ulang tanpa menggandakan data dummy', function () {
    $this->seeder->generate(80);
    $this->seeder->generate(80);

    expect(dummyStudents()->count())->toBe(80)
        ->and(Schedule::withoutGlobalScopes()->count())->toBe(3 * 8)
        ->and(User::withoutGlobalScopes()->where('email', 'like', 'operator.%')->count())->toBe(3);
});

it('membersihkan hanya data dummy dan membiarkan data asli', function () {
    $sekolah = School::query()->first();
    $siswaAsli = Student::factory()->for($sekolah)->create();
    $sesiAsli = makeSchedule($sekolah, '2026-12-01');
    $entriAsli = makeEntry(S::Terdaftar, $sekolah, $siswaAsli, $sesiAsli);
    $penggunaAsli = User::factory()->create();

    $this->seeder->generate(100);
    $dokumen = StudentDocument::query()->first()->path;
    $this->seeder->clean();

    expect(dummyStudents()->count())->toBe(0)
        ->and(User::withoutGlobalScopes()->where('email', 'like', '%@dummy.sijebol.test')->count())->toBe(0)
        ->and(Student::withoutGlobalScopes()->find($siswaAsli->id))->not->toBeNull()
        ->and(Schedule::withoutGlobalScopes()->pluck('id')->all())->toBe([$sesiAsli->id])
        ->and(RegistrationStudent::withoutGlobalScopes()->pluck('id')->all())->toBe([$entriAsli->id])
        ->and(DB::table('registration_status_histories')->where('registration_student_id', '!=', $entriAsli->id)->count())->toBe(0)
        ->and(User::withoutGlobalScopes()->find($penggunaAsli->id))->not->toBeNull();
    Storage::disk('local')->assertMissing($dokumen);
});

it('menjalankan generator dan pembersihan lewat perintah artisan', function () {
    $this->artisan('sijebol:isi-data-dummy', ['--siswa' => 40])->expectsOutputToContain('students: 40')->assertSuccessful();
    expect(dummyStudents()->count())->toBe(40);

    $this->artisan('sijebol:isi-data-dummy', ['--bersihkan' => true])->assertSuccessful();
    expect(dummyStudents()->count())->toBe(0);
});

it('menampilkan pesan ramah dan gagal bila pengaman menolak', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('sijebol:isi-data-dummy')->expectsOutputToContain('lingkungan local')->assertFailed();
});

describe('di luar lokal (UAT)', function () {
    beforeEach(fn () => app()->detectEnvironment(fn () => 'staging'));

    it('menolak bila saklar SIJEBOL_ALLOW_DUMMY_DATA belum dinyalakan', function () {
        expect(fn () => $this->seeder->generate(20))->toThrow(RuntimeException::class, 'SIJEBOL_ALLOW_DUMMY_DATA');
        expect(dummyStudents()->count())->toBe(0);
    });

    it('berjalan bila saklar dinyalakan dan memakai kata sandi yang diberikan', function () {
        config(['sijebol.allow_dummy_data' => true]);

        $this->seeder->generate(40, 'RahasiaUAT1');

        $operator = User::withoutGlobalScopes()->where('email', 'like', 'operator.%')->first();
        $siswa = User::withoutGlobalScopes()->find(dummyStudents()->whereNotNull('user_id')->first()->user_id);
        expect(Hash::check('RahasiaUAT1', $operator->password))->toBeTrue()
            ->and(Hash::check('RahasiaUAT1', $siswa->password))->toBeTrue()
            ->and(Hash::check('password', $operator->password))->toBeFalse();
    });

    it('membuat admin pembuat sesi tanpa bisa dipakai masuk', function () {
        config(['sijebol.allow_dummy_data' => true]);

        $this->seeder->generate(20, 'RahasiaUAT1');

        expect(User::withoutGlobalScopes()->where('email', 'admin.dummy@dummy.sijebol.test')->first()->is_active)->toBeFalse();
    });

    it('perintah menolak kata sandi bawaan atau pendek', function () {
        config(['sijebol.allow_dummy_data' => true]);

        $this->artisan('sijebol:isi-data-dummy', ['--siswa' => 10, '--force' => true])->expectsOutputToContain('--sandi')->assertFailed();
        $this->artisan('sijebol:isi-data-dummy', ['--siswa' => 10, '--force' => true, '--sandi' => 'pendek'])->assertFailed();
        expect(dummyStudents()->count())->toBe(0);
    });

    it('perintah meminta konfirmasi dan batal bila dijawab tidak', function () {
        config(['sijebol.allow_dummy_data' => true]);

        $this->artisan('sijebol:isi-data-dummy', ['--siswa' => 10, '--sandi' => 'RahasiaUAT1'])->expectsConfirmation('Lingkungan staging. Data dummy akan ditambahkan ke sekolah yang ada. Lanjut?', 'no')->assertFailed();
        expect(dummyStudents()->count())->toBe(0);
    });

    it('perintah jalan dengan --force dan kata sandi kuat', function () {
        config(['sijebol.allow_dummy_data' => true]);

        $this->artisan('sijebol:isi-data-dummy', ['--siswa' => 10, '--sandi' => 'RahasiaUAT1', '--force' => true])->assertSuccessful();
        expect(dummyStudents()->count())->toBe(10);
    });
});
