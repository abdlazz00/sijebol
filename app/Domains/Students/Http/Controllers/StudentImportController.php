<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Exports\StudentTemplateExport;
use App\Domains\Students\Models\StudentImport;
use App\Domains\Students\Services\StudentImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    public function __construct(private readonly StudentImportService $import) {}

    public function template(): BinaryFileResponse
    {
        return Excel::download(new StudentTemplateExport, 'template-siswa.xlsx');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            [
                'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
                'school_id' => $request->user()->school_id === null ? ['nullable', 'integer', 'exists:schools,id'] : ['nullable'],
            ],
            [
                'file.required' => 'Pilih berkas Excel (.xlsx).',
                'file.file' => 'Pilih berkas Excel (.xlsx).',
                'file.mimes' => 'Berkas harus berformat .xlsx.',
                'file.max' => 'Ukuran berkas maksimal 2 MB.',
                'file.uploaded' => 'Ukuran berkas maksimal 2 MB.',
            ],
        );

        $import = $this->import->prepare($request->file('file'), $request->user(), $request->integer('school_id') ?: null);

        return back()->with('import', ['id' => $import->id]);
    }

    public function show(Request $request, StudentImport $import): JsonResponse
    {
        abort_unless($import->visibleTo($request->user()), 404);

        return response()->json($import->payload());
    }
}
