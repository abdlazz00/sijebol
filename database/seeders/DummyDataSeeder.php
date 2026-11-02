<?php

namespace Database\Seeders;

use App\Domains\Access\Models\Role;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentDocument;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data dummy untuk pengembangan dan simulasi: siswa, sesi, pendaftaran beserta riwayat, dokumen, dan akun,
 * di atas sekolah yang sudah ada (sekolah tidak diubah). Penanda: NIK berawalan 9999, NISN 99, email @dummy.sijebol.test.
 * Spesifikasi: docs/superpowers/specs/2026-11-02-data-dummy-design.md.
 */
class DummyDataSeeder extends Seeder
{
    public const EMAIL_DOMAIN = '@dummy.sijebol.test';

    public const DEFAULT_STUDENTS = 1500;

    private const ZONE = 'Asia/Jakarta';

    /** Umur siswa (tahun) => bobot. */
    private const AGES = [14 => 5, 15 => 15, 16 => 30, 17 => 30, 18 => 20];

    /** Selisih hari sesi dari hari ini; 0 selalu ada agar ada sesi yang berlangsung hari ini. */
    private const SESSION_OFFSETS = [-60, -40, -25, -12, -4, 0, 7, 25];

    private const WINDOWS = [['08:00', '11:00'], ['09:00', '12:00'], ['13:00', '15:30']];

    private const LOCATIONS = ['Aula sekolah', 'Perpustakaan', 'Ruang multimedia', 'Ruang serbaguna', 'Laboratorium komputer'];

    /** Status akhir menurut waktu sesi => bobot. */
    private const PAST = ['sudah_rekaman' => 55, 'tidak_hadir' => 15, 'belum_rekaman' => 10, 'kedaluwarsa' => 10, 'ditolak' => 5, 'dibatalkan' => 5];

    private const TODAY = ['sudah_rekaman' => 50, 'terdaftar' => 35, 'belum_rekaman' => 15];

    private const FUTURE = ['terdaftar' => 45, 'menunggu_verifikasi' => 30, 'perlu_perbaikan' => 15, 'ditolak' => 5, 'dibatalkan' => 5];

    /** Status lama yang tidak aktif untuk pendaftaran terdahulu. */
    private const PRIOR = ['tidak_hadir', 'ditolak', 'dibatalkan', 'kedaluwarsa'];

    /** Status setelah Menunggu verifikasi yang dilalui hingga status akhir (jalur sah state machine). */
    private const PATHS = [
        'menunggu_verifikasi' => [],
        'perlu_perbaikan' => ['perlu_perbaikan'],
        'terdaftar' => ['terdaftar'],
        'sudah_rekaman' => ['terdaftar', 'sudah_rekaman'],
        'tidak_hadir' => ['terdaftar', 'tidak_hadir'],
        'belum_rekaman' => ['terdaftar', 'belum_rekaman'],
        'ditolak' => ['ditolak'],
        'dibatalkan' => ['dibatalkan'],
        'kedaluwarsa' => ['kedaluwarsa'],
    ];

    private const NOTES = [
        'perlu_perbaikan' => ['Foto Kartu Keluarga buram, mohon unggah ulang.', 'Akta kelahiran terpotong, unggah halaman penuh.', 'Nama pada KK tidak sama dengan data siswa.'],
        'ditolak' => ['NIK tidak sesuai dengan dokumen.', 'Dokumen tidak terbaca setelah perbaikan.', 'Data ganda dengan pendaftaran lain.'],
    ];

    /** @var array<int, int> Kursi yang ditahan per sesi selama pengisian. */
    private array $taken = [];

    public function run(): void
    {
        $this->generate(self::DEFAULT_STUDENTS);
    }

    /**
     * Bersihkan dummy lama lalu isi ulang.
     *
     * @return array{schedules: int, students: int, registrations: int, accounts: int}
     */
    public function generate(int $students): array
    {
        $this->guard();
        $schools = School::query()->orderBy('id')->get();
        if ($schools->isEmpty()) {
            throw new RuntimeException('Belum ada sekolah. Data dummy dibuat di atas sekolah yang sudah ada.');
        }
        foreach ([Role::SISWA, 'operator-sekolah', 'admin-kecamatan'] as $role) {
            if (! Role::query()->where('name', $role)->exists()) {
                throw new RuntimeException("Role {$role} belum ada. Jalankan AccessSeeder dulu.");
            }
        }

        $this->clean();

        return $this->quiet(fn () => DB::transaction(fn () => $this->fill($schools, $students)));
    }

    /**
     * Hapus hanya yang bertanda dummy. Sesi dummy yang sudah dipakai pendaftaran asli dibiarkan.
     *
     * @return array{registrations: int, students: int, schedules: int, accounts: int}
     */
    public function clean(): array
    {
        $this->guard();

        return $this->quiet(fn () => DB::transaction(function () {
            $students = Student::withoutGlobalScopes()->where('nik', 'like', '9999%');
            $entries = RegistrationStudent::withoutGlobalScopes()->whereIn('student_id', (clone $students)->select('id'));
            $accounts = User::withoutGlobalScopes()->where('email', 'like', '%'.self::EMAIL_DOMAIN);

            DB::table('registration_status_histories')->whereIn('registration_student_id', (clone $entries)->select('id'))->delete();
            $registrations = (clone $entries)->delete();
            foreach ((clone $students)->pluck('id') as $id) {
                Storage::disk('local')->deleteDirectory("students/{$id}");
            }
            $deletedStudents = (clone $students)->delete();
            $schedules = Schedule::withoutGlobalScopes()->whereIn('created_by', (clone $accounts)->select('id'))
                ->whereDoesntHave('entries', fn ($q) => $q->withoutGlobalScopes())->delete();
            $users = (clone $accounts)->whereNotIn('id', Schedule::withoutGlobalScopes()->select('created_by'))->get()->each->delete()->count();

            return ['registrations' => $registrations, 'students' => $deletedStudents, 'schedules' => $schedules, 'accounts' => $users];
        }));
    }

    private function guard(): void
    {
        // ponytail: hanya lokal; menyalakannya di UAT butuh faker di require dan saklar eksplisit (lihat spec, "Menuju UAT").
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Data dummy hanya boleh dibuat di lingkungan local.');
        }
    }

    /** Log aktivitas dimatikan: data dummy tidak boleh mengotori jejak audit. */
    private function quiet(callable $callback): mixed
    {
        activity()->disableLogging();
        try {
            return $callback();
        } finally {
            activity()->enableLogging();
        }
    }

    /** @return array{schedules: int, students: int, registrations: int, accounts: int} */
    private function fill(Collection $schools, int $total): array
    {
        $this->taken = [];
        $admin = $this->account('admin.dummy', 'Admin Kecamatan Dummy', 'admin-kecamatan', null);
        $accounts = 1;
        foreach ($schools as $school) {
            $this->account("operator.{$school->npsn}", "Operator {$school->name}", 'operator-sekolah', $school->id);
            $accounts++;
        }

        $assignments = $this->assignSchools($schools, $total);
        $sessions = $schools->mapWithKeys(fn (School $s) => [$s->id => $this->sessions($s, $admin, (int) ($assignments[$s->id] ?? 0))]);

        $registrations = 0;
        $png = file_get_contents(__DIR__.'/assets/dokumen-contoh.png');
        $eligibility = app(EligibilityService::class);
        $now = Carbon::now(self::ZONE);

        foreach ($assignments as $schoolId => $count) {
            for ($i = 0; $i < $count; $i++) {
                $student = Student::factory()->dummy($this->weighted(self::AGES))->create(['school_id' => $schoolId]);
                if (random_int(1, 100) <= 30) {
                    $this->studentAccount($student);
                    $accounts++;
                }
                if (random_int(1, 100) > 75) {
                    continue;
                }
                $made = $this->register($student, $sessions[$schoolId], $admin, $now, $eligibility);
                if ($made > 0) {
                    $this->documents($student, $png);
                    $registrations += $made;
                }
            }
        }

        return ['schedules' => $sessions->sum(fn (Collection $c) => $c->count()), 'students' => $total, 'registrations' => $registrations, 'accounts' => $accounts];
    }

    /** @return array<int, int> Jumlah siswa per sekolah (bobot acak 1–4). */
    private function assignSchools(Collection $schools, int $total): array
    {
        $pool = $schools->flatMap(fn (School $s) => array_fill(0, random_int(1, 4), $s->id))->values()->all();
        $counts = array_fill_keys($schools->pluck('id')->all(), 0);
        for ($i = 0; $i < $total; $i++) {
            $counts[$pool[array_rand($pool)]]++;
        }

        return $counts;
    }

    /** @return Collection<int, Schedule> Sesi satu sekolah; kuota sebanding perkiraan pendaftar agar ada yang penuh dan ada yang longgar. */
    private function sessions(School $school, User $admin, int $students): Collection
    {
        $perSession = max(1, $students * 0.6 / count(self::SESSION_OFFSETS));
        $today = Carbon::now(self::ZONE)->startOfDay();
        // Indeks di luar daftar berarti tidak ada sesi dibatalkan: sekitar separuh sekolah punya satu.
        $cancelled = random_int(0, 2 * count(self::SESSION_OFFSETS) - 1);

        return collect(self::SESSION_OFFSETS)->map(function (int $offset, int $index) use ($school, $admin, $perSession, $today, $cancelled) {
            $date = $today->copy()->addDays($offset === 0 ? 0 : $offset + random_int(-3, 3));
            [$start, $end] = self::WINDOWS[array_rand(self::WINDOWS)];
            $factory = Schedule::factory();
            if ($index === $cancelled && $offset !== 0) {
                $factory = $factory->cancelled();
            }

            return $factory->create([
                'school_id' => $school->id,
                'date' => $date->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'registration_closes_at' => $date->copy()->subDays((int) config('sijebol.registration_close_days'))->setTime(23, 59, 59),
                'location' => self::LOCATIONS[array_rand(self::LOCATIONS)],
                'quota' => max(20, (int) round($perSession * random_int(80, 160) / 100)),
                'created_by' => $admin->id,
            ]);
        });
    }

    /** @param  Collection<int, Schedule>  $sessions  Sesi sekolah siswa. @return int Jumlah pendaftaran yang dibuat. */
    private function register(Student $student, Collection $sessions, User $admin, Carbon $now, EligibilityService $eligibility): int
    {
        $eligible = $sessions->filter(fn (Schedule $s) => $eligibility->age($student, $s->date) >= EligibilityService::MIN_AGE)->values();
        if ($eligible->isEmpty()) {
            return 0;
        }

        $session = $eligible->random();
        $status = $this->finalStatus($session, $now);
        $made = 0;

        if (random_int(1, 100) <= 12) {
            $earlier = $eligible->filter(fn (Schedule $s) => $s->date->lt($session->date) && $s->date->lt($now->copy()->startOfDay()) && ! $s->isCancelled());
            if ($earlier->isNotEmpty()) {
                $this->entry($student, $earlier->random(), S::from($this->weighted(array_fill_keys(self::PRIOR, 1))), $admin, $now);
                $made++;
            }
        }
        $this->entry($student, $session, $status, $admin, $now);

        return $made + 1;
    }

    private function finalStatus(Schedule $session, Carbon $now): S
    {
        if ($session->isCancelled()) {
            return S::Dibatalkan;
        }
        $weights = match (true) {
            $session->date->lt($now->copy()->startOfDay()) => self::PAST,
            $session->date->isSameDay($now) => self::TODAY,
            default => self::FUTURE,
        };

        return S::from($this->weighted($weights));
    }

    /** Buat satu pendaftaran lengkap dengan riwayatnya; kursi yang tidak tersedia mengubah status menjadi Dibatalkan. */
    private function entry(Student $student, Schedule $session, S $status, User $admin, Carbon $now): void
    {
        if (in_array($status, S::holdingSeat(), true)) {
            if (($this->taken[$session->id] ?? 0) >= $session->quota) {
                $status = S::Dibatalkan;
            } else {
                $this->taken[$session->id] = ($this->taken[$session->id] ?? 0) + 1;
            }
        }

        $closes = Carbon::parse($session->registration_closes_at);
        $at = $closes->copy()->subDays(random_int(1, 8))->subMinutes(random_int(0, 600));
        if ($at->gt($now)) {
            $at = $now->copy()->subHours(random_int(2, 96));
        }
        $start = Carbon::parse("{$session->date->toDateString()} {$session->start_time}", self::ZONE);
        $sessionDay = $start->copy()->addMinutes(random_int(0, 90));

        $steps = self::PATHS[$status->value];
        $rows = [['from' => null, 'to' => S::MenungguVerifikasi, 'at' => $at->copy(), 'by' => null, 'note' => null]];
        $previous = S::MenungguVerifikasi;
        $time = $at->copy();
        foreach ($steps as $value) {
            $to = S::from($value);
            // Penandaan hadir/tidak hadir terjadi pada hari sesi; keputusan verifikasi sebelum sesi.
            $next = in_array($to, [S::SudahRekaman, S::TidakHadir, S::BelumRekaman], true)
                ? $sessionDay->copy()
                : $time->copy()->addHours(random_int(3, 30))->min($start->copy()->subHour());
            $time = $next->max($time)->min($now);
            $rows[] = ['from' => $previous, 'to' => $to, 'at' => $time->copy(), 'by' => $admin->id, 'note' => $this->note($to)];
            $previous = $to;
        }

        $decision = collect($rows)->skip(1)->first(fn ($r) => in_array($r['to'], [S::Terdaftar, S::PerluPerbaikan, S::Ditolak], true));
        $issued = in_array(S::Terdaftar->value, $steps, true);
        $last = end($rows);

        $entry = RegistrationStudent::factory()->create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'schedule_id' => $session->id,
            'status' => $status,
            'note' => $this->note($status),
            'decided_by' => $decision ? $admin->id : null,
            'decided_at' => $decision['at'] ?? null,
            'ticket_code' => $issued ? Str::random(32) : null,
            'fix_deadline_at' => $status === S::PerluPerbaikan ? $session->fixDeadlineFrom($now) : null,
            'scanned_at' => $status === S::SudahRekaman ? $last['at'] : null,
            'created_at' => $at,
            'updated_at' => $last['at'],
        ]);

        DB::table('registration_status_histories')->insert(array_map(fn (array $r) => [
            'registration_student_id' => $entry->id,
            'from_status' => $r['from']?->value,
            'to_status' => $r['to']->value,
            'by_user_id' => $r['by'],
            'note' => $r['note'],
            'created_at' => $r['at'],
        ], $rows));
    }

    private function note(S $status): ?string
    {
        $options = self::NOTES[$status->value] ?? null;

        return $options ? $options[array_rand($options)] : null;
    }

    private function documents(Student $student, string $png): void
    {
        foreach (['kk', 'akta_lahir'] as $type) {
            $path = "students/{$student->id}/".Str::uuid().'.png';
            Storage::disk('local')->put($path, $png);
            StudentDocument::query()->create(['student_id' => $student->id, 'type' => $type, 'path' => $path, 'mime' => 'image/png', 'size' => strlen($png)]);
        }
    }

    private function studentAccount(Student $student): void
    {
        $user = User::factory()->create([
            'name' => $student->name,
            'email' => "siswa.{$student->nisn}".self::EMAIL_DOMAIN,
            'school_id' => $student->school_id,
        ]);
        $user->assignRole(Role::SISWA);
        $student->update(['user_id' => $user->id]);
    }

    private function account(string $local, string $name, string $role, ?int $schoolId): User
    {
        // Dipakai ulang bila ada: akun yang masih memegang sesi dengan pendaftaran asli tidak ikut terhapus oleh clean().
        $email = $local.self::EMAIL_DOMAIN;
        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        if ($user === null) {
            $user = User::factory()->create(['name' => $name, 'email' => $email, 'school_id' => $schoolId]);
            $user->assignRole($role);
        }

        return $user;
    }

    /** @param  array<int|string, int>  $weights */
    private function weighted(array $weights): int|string
    {
        $pick = random_int(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($pick -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
