<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\AuditLog::class,
        ]);

        $middleware->alias([
            'auth.required' => \App\Http\Middleware\RequireAuth::class,
            'auth.forbid' => \App\Http\Middleware\RequireAuthForbid::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'acl' => \App\Http\Middleware\CheckAcl::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Log all exceptions to ahg_error_log table
        $exceptions->report(function (\Throwable $e) {
            try {
                $request = request();
                $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'error');

                DB::table('ahg_error_log')->insert([
                    'level' => $level,
                    'status_code' => $statusCode,
                    'message' => mb_substr($e->getMessage() ?: get_class($e), 0, 65535),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'exception_class' => get_class($e),
                    'request_id' => $request?->header('X-Request-ID'),
                    'url' => mb_substr($request?->fullUrl() ?? '', 0, 2000),
                    'http_method' => $request?->method(),
                    'client_ip' => $request?->ip(),
                    'user_agent' => mb_substr($request?->userAgent() ?? '', 0, 500),
                    'user_id' => Auth::id(),
                    'hostname' => gethostname(),
                    'trace' => mb_substr($e->getTraceAsString(), 0, 65535),
                    'is_read' => 0,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $logException) {
                // Don't let logging failure break the app
            }

            return false; // Continue to default Laravel logging as well
        });

        // Return JSON error responses for API routes
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                ], 429);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') && !app()->hasDebugModeEnabled()) {
                return response()->json([
                    'error' => 'Internal Server Error',
                    'message' => 'An unexpected error occurred.',
                ], 500);
            }
        });
    })->create();
