<?php

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $status = $request->query('filter')['status'] ?? null;

        return Inertia::render('Notifikasi/Index', [
            'items' => $this->notifications->list($request->user(), $status === 'unread'),
            'filters' => ['status' => $status],
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $id);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back();
    }
}
