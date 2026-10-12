<?php

namespace App\Domains\Students\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentDocumentService
{
    public const MAX_KB = 5120;

    private const DISK = 'local';

    public function store(Student $student, string $type, UploadedFile $file, User $actor): StudentDocument
    {
        if ($student->isLocked()) {
            throw new AccessRuleException(Student::LOCKED_MESSAGE);
        }

        $path = $file->storeAs("students/{$student->id}", Str::uuid().'.'.$file->extension(), self::DISK);

        try {
            [$document, $oldPath] = DB::transaction(function () use ($student, $type, $file, $path) {
                $old = $student->documents()->where('type', $type)->first();
                $document = $student->documents()->updateOrCreate(
                    ['type' => $type],
                    ['path' => $path, 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()],
                );

                return [$document, $old?->path];
            });
        } catch (Throwable $e) {
            // Tidak boleh ada berkas yatim bila baris gagal disimpan.
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk(self::DISK)->delete($oldPath);
        }
        activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['jenis' => $type])->log('dokumen.diunggah');

        return $document;
    }

    public function delete(Student $student, string $type, User $actor): void
    {
        if ($student->isLocked()) {
            throw new AccessRuleException(Student::LOCKED_MESSAGE);
        }

        $document = $student->documents()->where('type', $type)->firstOrFail();

        $document->delete();
        Storage::disk(self::DISK)->delete($document->path);
        activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['jenis' => $type])->log('dokumen.dihapus');
    }

    public function download(Student $student, string $type, User $actor): StreamedResponse
    {
        $document = $student->documents()->where('type', $type)->firstOrFail();
        abort_unless(Storage::disk(self::DISK)->exists($document->path), 404);

        activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['jenis' => $type])->log('dokumen.diunduh');

        return Storage::disk(self::DISK)->download($document->path, $this->fileName($student, $document));
    }

    public function preview(Student $student, string $type, User $actor): StreamedResponse
    {
        $document = $student->documents()->where('type', $type)->firstOrFail();
        abort_unless(Storage::disk(self::DISK)->exists($document->path), 404);

        activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['jenis' => $type])->log('dokumen.dilihat');

        return Storage::disk(self::DISK)->response(
            $document->path,
            $this->fileName($student, $document),
            [
                'Content-Type' => $document->mime ?: 'application/octet-stream',
            ],
            'inline',
        );
    }

    /** @return list<array{type: string, name: string, size: int, mime: string|null, url: string, preview_url: string}> */
    public function rows(Student $student): array
    {
        return $student->documents->map(fn (StudentDocument $d) => [
            'type' => $d->type,
            'name' => $this->fileName($student, $d),
            'size' => $d->size,
            'mime' => $d->mime,
            'url' => route('siswa.dokumen.download', [$student, $d->type]),
            'preview_url' => route('siswa.dokumen.preview', [$student, $d->type]),
        ])->values()->all();
    }

    public function fileName(Student $student, StudentDocument $document): string
    {
        return "{$document->type}-{$student->nisn}.".pathinfo($document->path, PATHINFO_EXTENSION);
    }
}
