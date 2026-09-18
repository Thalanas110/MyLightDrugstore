<?php

use App\Http\Errors\ApiErrorResponder;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\ValidateCsrfToken;
use App\Modules\Transport\Presentation\Middleware\DecryptTransportRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AttachRequestId::class);
        $middleware->appendToGroup('api', DecryptTransportRequest::class);
        $middleware->replaceInGroup('web', PreventRequestForgery::class, ValidateCsrfToken::class);
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(static function (Throwable $exception, Request $request): ?JsonResponse {
            return app(ApiErrorResponder::class)->respond($exception, $request);
        });

        $exceptions->context(static function (Throwable $exception, array $context): array {
            $requestId = request()->attributes->get(AttachRequestId::ATTRIBUTE);

            return is_string($requestId) ? ['request_id' => $requestId] : [];
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
