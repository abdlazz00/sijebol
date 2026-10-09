<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            EnsureAccountIsActive::class,
            AddLinkHeadersForPreloadedAssets::class,
            'throttle:web',
        ]);

        // ThrottleRequests berprioritas lebih awal daripada middleware biasa; tanpa ini respons 429 lolos tanpa header keamanan.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: SecurityHeaders::class);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (AccessRuleException $e) => back()->with('error', $e->getMessage()));

        // Halaman error bergaya aplikasi; permintaan JSON dan 500 saat debug tetap bawaan Laravel.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 429, 500, 503], true) || $request->expectsJson() || ($status === 500 && config('app.debug'))) {
                return $response;
            }

            $page = Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            if ($response->headers->has('Retry-After')) {
                $page->headers->set('Retry-After', (string) $response->headers->get('Retry-After'));
            }

            return $page;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
