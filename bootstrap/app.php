<?php

use App\Exceptions\ApiDomainException;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $headers = $exception->getHeaders();

            return ApiResponse::error(
                code: 'rate_limited',
                message: 'Too many requests. Please try again later.',
                status: 429,
                meta: [
                    'retryable' => true,
                    'retry_after' => max(1, (int) ($headers['Retry-After'] ?? 1)),
                ],
            )->withHeaders($headers);
        });

        $exceptions->render(function (ApiDomainException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::log($exception->logLevel(), 'api.domain_exception', $exception->logContext() + [
                'api_code' => $exception->apiCode(),
            ]);

            return ApiResponse::error(
                code: $exception->apiCode(),
                message: $exception->getMessage(),
                status: $exception->status(),
                errors: $exception->errors(),
                meta: $exception->meta(),
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                code: 'validation_failed',
                message: 'The request could not be processed.',
                status: $exception->status,
                errors: $exception->errors(),
            );
        });
    })->create();
