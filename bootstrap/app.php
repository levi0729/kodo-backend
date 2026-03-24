<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code'    => 'UNAUTHENTICATED',
                        'message' => 'You must be logged in to perform this action.',
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => 'Please check the highlighted fields and try again.',
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'error' => [
                        'code'    => 'NOT_FOUND',
                        'message' => "The requested {$model} could not be found.",
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = $e->getStatusCode();
                $messages = [
                    403 => 'You do not have permission to perform this action.',
                    404 => 'The requested resource could not be found.',
                    405 => 'This action is not supported.',
                    409 => 'This action conflicts with the current state.',
                    423 => 'This account is temporarily locked. Please try again later.',
                    429 => 'Too many requests. Please slow down and try again shortly.',
                    500 => 'Something went wrong on our end. Please try again later.',
                ];
                $codes = [
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    409 => 'CONFLICT',
                    423 => 'ACCOUNT_LOCKED',
                    429 => 'RATE_LIMITED',
                    500 => 'SERVER_ERROR',
                ];
                return response()->json([
                    'error' => [
                        'code'    => $codes[$status] ?? 'ERROR',
                        'message' => $e->getMessage() ?: ($messages[$status] ?? 'An unexpected error occurred.'),
                    ],
                ], $status);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code'    => 'SERVER_ERROR',
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile() . ':' . $e->getLine(),
                    ],
                ], 500);
            }
        });
    })->create();
