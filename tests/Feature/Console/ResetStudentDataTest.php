<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStatusHistory;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentDocument;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

const KONFIRMASI = 'Hapus SEMUA data siswa, pendaftaran, jadwal, dan dokumen? Akun pengguna tidak ikut dihapus.';

function isiDataSiswa(): User
{
    Storage::fake('local');
    $user = User::factory()->create();
    $entry = makeEntry();
    $entry->student->documents()->create(['type' => 'kk', 'path' => "students/{$entry->student_id}/kk.pdf", 'mime' => 'application/pdf', 'size' => 1]);
    Storage::disk('local')->put("students/{$entry->student_id}/kk.pdf", 'isi');
    app(StatusTransitioner::class)->move($entry, S::Terdaftar, $user);
    activity('uji')->log('sebelum');

    return $user;
}

test('tanpa konfirmasi tidak menghapus apa pun', function () {
    isiDataSiswa();

    $this->artisan('sijebol:bersihkan-data-siswa')->expectsConfirmation(KONFIRMASI, 'no')->assertExitCode(1);

    expect(Student::withoutGlobalScopes()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('students'))->toHaveCount(1);
});

test('mode non-interaktif tanpa --force tidak menghapus apa pun', function () {
    isiDataSiswa();

    $kode = Artisan::call('sijebol:bersihkan-data-siswa', ['--no-interaction' => true]);

    expect($kode)->toBe(1)
        ->and(Student::withoutGlobalScopes()->count())->toBe(1);
});

test('dengan --force data siswa, pendaftaran, jadwal, dan berkas dihapus, tetapi akun, sekolah, dan log tetap', function () {
    $user = isiDataSiswa();
    $sekolah = School::count();
    $akun = User::count();
    $log = Activity::count();

    $this->artisan('sijebol:bersihkan-data-siswa', ['--force' => true])->assertExitCode(0);

    expect(Student::withoutGlobalScopes()->count())->toBe(0)
        ->and(StudentDocument::count())->toBe(0)
        ->and(RegistrationStudent::withoutGlobalScopes()->count())->toBe(0)
        ->and(RegistrationStatusHistory::count())->toBe(0)
        ->and(Schedule::withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('students'))->toBe([])
        ->and(School::count())->toBe($sekolah)
        ->and(User::count())->toBe($akun)
        ->and(User::find($user->id))->not->toBeNull()
        ->and(Activity::count())->toBe($log);
});

test('folder dokumen yang belum ada tidak membuat error', function () {
    Storage::fake('local');

    $this->artisan('sijebol:bersihkan-data-siswa', ['--force' => true])->assertExitCode(0);
});
