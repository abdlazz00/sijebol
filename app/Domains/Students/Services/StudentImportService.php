<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Jobs\ImportStudentsJob;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentImport;
use App\Domains\Students\Support\StudentRules;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentImportService
{
    public const MAX_ROWS = 500;

    /** Kunci heading hasil pembacaan (heading berkas di-slug oleh pembaca). */
    private const REQUIRED = ['nik', 'nisn', 'nama', 'tanggal_lahir', 'jenis_kelamin'];

    /**
     * Pra-periksa sinkron (sekolah, format, kolom wajib, batas baris), simpan berkas, buat baris impor, dan antrekan job.
     *
     * @throws ValidationException
     */
    public function prepare(UploadedFile $file, User $actor, ?int $schoolId): StudentImport
    {
        $schoolId = $actor->school_id ?? $schoolId;
        if ($schoolId === null) {
            throw ValidationException::withMessages(['school_id' => 'Pilih sekolah.']);
        }

        $this->read($file);

        $import = StudentImport::create([
            'user_id' => $actor->id,
            'school_id' => $schoolId,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->storeAs('imports', Str::uuid().'.xlsx', 'local'),
        ]);
        ImportStudentsJob::dispatch($import->id);

        return $import;
    }

    /** Isi job: memproses baris, menyimpan hasil, dan menghapus berkas (juga bila gagal di tengah jalan). */
    public function run(StudentImport $import): void
    {
        $import->update(['status' => StudentImport::BERJALAN, 'started_at' => now()]);

        try {
            $rows = $this->read((string) $import->path, 'local');
            $imported = 0;
            $failed = [];
            $seen = [];

            foreach ($rows as $index => $row) {
                if ($this->isBlank($row)) {
                    continue;
                }

                $reason = $this->importRow($row, $import->school_id, $seen);
                if ($reason === null) {
                    $imported++;
                } else {
                    $failed[] = ['row' => $index + 2, 'reason' => $reason]; // baris 1 adalah judul
                }
            }

            $import->update(['status' => StudentImport::SELESAI, 'imported' => $imported, 'failed' => $failed, 'finished_at' => now()]);
            activity('siswa')->causedBy($import->user)->withProperties(['berhasil' => $imported, 'gagal' => count($failed)])->log('siswa.diimpor');
        } finally {
            $this->discardFile($import);
        }
    }

    public function markFailed(StudentImport $import): void
    {
        $import->update([
            'status' => StudentImport::GAGAL,
            'error' => 'Impor gagal diproses. Periksa berkas lalu coba lagi.',
            'finished_at' => now(),
        ]);
        $this->discardFile($import);
    }

    public function findVisible(int $id, User $user): ?StudentImport
    {
        $import = StudentImport::query()->find($id);

        return $import !== null && $user->can('siswa.impor') && $import->visibleTo($user) ? $import : null;
    }

    private function discardFile(StudentImport $import): void
    {
        if ($import->path !== null) {
            Storage::disk('local')->delete($import->path);
            $import->update(['path' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seen
     */
    private function importRow(array $row, int $schoolId, array &$seen): ?string
    {
        // Excel menyimpan angka dengan 15 digit signifikan: NIK numerik tidak bisa dipercaya.
        if ($row['nik'] !== null && ! is_string($row['nik'])) {
            return 'NIK harus diisi sebagai teks (format kolom NIK = Teks).';
        }

        $nik = trim((string) $row['nik']);
        if (isset($seen[$nik])) {
            return 'NIK ganda di berkas.';
        }

        $data = [
            'nik' => $nik,
            'nisn' => $this->nisn($row['nisn']),
            'name' => trim((string) $row['nama']),
            'birth_date' => $this->date($row['tanggal_lahir']),
            'gender' => $this->gender($row['jenis_kelamin']),
        ];

        $validator = Validator::make($data, StudentRules::fields(), StudentRules::messages());
        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        // Satu log ringkas per impor, bukan satu log per siswa.
        $student = new Student([...$data, 'school_id' => $schoolId]);
        $student->disableLogging();
        $student->save();
        $seen[$nik] = true;

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function read(UploadedFile|string $file, ?string $disk = null): array
    {
        // WithLimit menghentikan pembacaan di baris ke-501, jadi berkas yang mengembang tidak dimuat seluruhnya.
        $reader = new class implements ToArray, WithHeadingRow, WithLimit
        {
            public function array(array $array): void {}

            public function limit(): int
            {
                return StudentImportService::MAX_ROWS + 1;
            }
        };

        /** @var list<array<string, mixed>> $rows */
        $rows = Excel::toArray($reader, $file, $disk)[0] ?? [];

        // Pembaca bisa menambah satu baris kosong di ujung; hanya baris terisi yang dihitung.
        if (count(array_filter($rows, fn (array $row) => ! $this->isBlank($row))) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per impor.']);
        }
        if ($rows !== [] && ($missing = array_diff(self::REQUIRED, array_keys($rows[0]))) !== []) {
            throw ValidationException::withMessages(['file' => 'Kolom wajib tidak lengkap: '.implode(', ', $missing).'.']);
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function isBlank(array $row): bool
    {
        return collect($row)->every(fn ($v) => $v === null || trim((string) $v) === '');
    }

    /** NISN numerik kehilangan nol di depan; dipulihkan ke 10 digit. */
    private function nisn(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return str_pad((string) (int) $value, 10, '0', STR_PAD_LEFT);
        }

        return trim((string) $value);
    }

    private function gender(mixed $value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'l', 'laki-laki', 'laki laki', 'pria' => 'L',
            'p', 'perempuan', 'wanita' => 'P',
            default => null,
        };
    }

    /** Menerima YYYY-MM-DD, DD/MM/YYYY, atau nomor seri tanggal Excel. */
    private function date(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (InvalidFormatException) {
                continue;
            }
            // Pemeriksaan bolak-balik menolak tanggal yang tak mungkin (mis. 30 Feb).
            if ($date->format($format) === $text) {
                return $date->toDateString();
            }
        }

        return $text === '' ? null : $text;
    }
}
