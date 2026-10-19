<?php

namespace App\Domains\Reports\Http\Controllers;

use App\Domains\Reports\Exports\RecapExport;
use App\Domains\Reports\Exports\SessionRecapExport;
use App\Domains\Reports\Http\Requests\ReportRequest;
use App\Domains\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
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
