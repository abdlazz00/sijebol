<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentDocumentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDocumentController extends Controller
{
    public function __construct(private readonly StudentDocumentService $documents) {}

    public function store(Request $request, Student $student, string $type): RedirectResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.StudentDocumentService::MAX_KB]],
            [
                'file.required' => 'Pilih berkas.',
                'file.file' => 'Pilih berkas.',
                'file.mimes' => 'Berkas harus PDF, JPG, atau PNG.',
                'file.max' => 'Ukuran berkas maksimal 5 MB.',
                'file.uploaded' => 'Ukuran berkas maksimal 5 MB.',
            ],
        );

        $this->documents->store($student, $type, $request->file('file'), $request->user());

        return back()->with('success', 'Dokumen diunggah.');
    }

    public function download(Request $request, Student $student, string $type): StreamedResponse
    {
        return $this->documents->download($student, $type, $request->user());
    }

    public function destroy(Request $request, Student $student, string $type): RedirectResponse
    {
        $this->documents->delete($student, $type, $request->user());

        return back()->with('success', 'Dokumen dihapus.');
    }
}
