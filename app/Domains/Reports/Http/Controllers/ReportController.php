<?php

namespace App\Domains\Reports\Http\Controllers;

use App\Domains\Reports\Exports\RecapExport;
use App\Domains\Reports\Exports\SessionRecapExport;
use App\Domains\Reports\Exports\StudentRecordExport;
use App\Domains\Reports\Http\Requests\ReportRequest;
use App\Domains\Reports\Http\Requests\StudentRecordRequest;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportRequest $request): Response
    {
        [$from, $to] = [$request->validated('dari'), $request->validated('sampai')];

        return Inertia::render('Laporan/Index', [
            'recap' => $this->reports->recap($from, $to),
            'session_recap' => $this->reports->sessionRecap($from, $to),
            'filters' => ['dari' => $from, 'sampai' => $to],
        ]);
    }

    /** Kolom baris yang boleh sampai ke browser: NIK, tanggal lahir, dan jenis kelamin hanya untuk Excel. */
    private const PAGE_COLUMNS = ['id', 'name', 'nisn', 'school', 'group', 'session_date', 'eligible_from', 'reason'];

    private const PER_PAGE = 25;

    public function students(StudentRecordRequest $request, SchoolService $schools): Response
    {
        $filters = $request->filters();
        $result = $this->reports->studentRecords($filters);
        $rows = collect($result['rows'])->map(fn (array $row) => Arr::only($row, self::PAGE_COLUMNS));
        $page = LengthAwarePaginator::resolveCurrentPage();

        return Inertia::render('Laporan/Siswa', [
            'records' => (new LengthAwarePaginator(
                $rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, ['path' => $request->url()],
            ))->withQueryString(),
            'groups' => collect(ReportService::GROUPS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label, 'count' => $result['counts'][$key]])
                ->values(),
            'filters' => $filters,
            'schools' => $schools->optionsFor($request->user()),
            'schedules' => $this->reports->sessionOptions(),
        ]);
    }

    public function exportStudents(StudentRecordRequest $request): BinaryFileResponse
    {
        $filters = $request->filters();
        $rows = $this->reports->studentRecords($filters)['rows'];
        $fullNik = $request->user()->can('laporan.unduh-nik');
        $this->reports->recordExport($request->user(), $filters['dari'] ?? null, $filters['sampai'] ?? null, 'ktp-el-pelajar', [
            'filter' => $filters, 'nik_penuh' => $fullNik, 'jumlah' => count($rows),
        ]);

        return Excel::download(new StudentRecordExport($rows, $fullNik), 'laporan-ktp-el-pelajar.xlsx');
    }

    public function export(ReportRequest $request): BinaryFileResponse
    {
        [$from, $to] = [$request->validated('dari'), $request->validated('sampai')];
        $this->reports->recordExport($request->user(), $from, $to);

        return Excel::download(new RecapExport($this->reports->recap($from, $to)), 'rekap-pendaftaran.xlsx');
    }

    public function exportSessions(ReportRequest $request): BinaryFileResponse
    {
        [$from, $to] = [$request->validated('dari'), $request->validated('sampai')];
        $this->reports->recordExport($request->user(), $from, $to, 'kehadiran-sesi');

        return Excel::download(new SessionRecapExport($this->reports->sessionRecap($from, $to)), 'rekap-kehadiran-sesi.xlsx');
    }
}
