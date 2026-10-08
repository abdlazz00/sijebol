<?php

namespace App\Domains\Audit\Http\Controllers;

use App\Domains\Audit\Services\ActivityLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogService $activities) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Log/Index', [
            'activities' => $this->activities->list(),
            'filters' => $request->query('filter', []),
            'types' => $this->activities->typeOptions(),
            'users' => $this->activities->userOptions(),
        ]);
    }
}
