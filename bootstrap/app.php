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
        // SessionTimeout has to run *before* StartSession so the
        // session.lifetime override (sourced from
        // ahg_settings.security_session_timeout_minutes) takes effect on
        // cookie issuance. prepend() puts it at the top of the web stack,
        // ahead of Laravel's built-ins. Closes audit issue #90.
        $middleware->web(prepend: [
            // Phase 2 of #677 — must run FIRST so every downstream log line
            // in this request has the request_id. Generates UUID per
            // request, binds to container, echoes as X-Request-Id header.
            \App\Http\Middleware\RequestIdMiddleware::class,
            // Phase 3 of #677 - Prometheus instrumentation. Runs AFTER the
            // request-id middleware (so terminate() has the id in scope if
            // we ever want to label by it) but BEFORE any auth middleware,
            // so we see and count anonymous / blocked / 401 requests too.
            // Counts are pushed in terminate() so the response is flushed
            // first; a registry blip cannot affect the response.
            \AhgObservability\Http\Middleware\PrometheusHttpMiddleware::class,
            \App\Http\Middleware\SessionTimeout::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            // Issue #690 — enforces post-login MFA verify when the session
            // carries the `pending_mfa` flag. No-op for users without MFA
            // and for users who have already verified this session.
            \App\Http\Middleware\RequireMfaCompletion::class,
            \App\Http\Middleware\AuditLog::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Spatie\Csp\AddCspHeaders::class,
            // Must run after AddCspHeaders so the nonce is bound in the
            // container by the time we post-process the response body.
            \App\Http\Middleware\InjectCspNonces::class,
            // Resolves the active tenant (host -> session -> primary ->
            // default) and binds it as app('tenant.current'). No-op when
            // ahg_settings.tenant_enabled is false, so single-tenant
            // installs are unchanged.
            \AhgMultiTenant\Http\Middleware\ResolveTenantMiddleware::class,
            // Injects a "Version history (N)" banner into IO / actor /
            // sector show pages. Silent no-op when the entity has no
            // captured versions or the response isn't HTML.
            \AhgVersionControl\Http\Middleware\VersionLinkInjector::class,
            // Injects a "Share record" button + Bootstrap modal into IO
            // show pages. Silent no-op when the user is anonymous, lacks
            // share_link.create ACL, or the response isn't HTML.
            \AhgShareLink\Http\Middleware\ShareLinkInjector::class,
            // Phase 1 of #670 SEO: injects Schema.org JSON-LD into the
            // <head> of info-object / actor / repository show pages so
            // Google + Bing can discover archival records via structured
            // data. Silent no-op on non-HTML responses or unknown slugs.
            \AhgInformationObjectManage\Http\Middleware\SchemaJsonLdInjector::class,
        ]);

        $middleware->alias([
            'auth.required' => \App\Http\Middleware\RequireAuth::class,
            'auth.forbid' => \App\Http\Middleware\RequireAuthForbid::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'acl' => \App\Http\Middleware\CheckAcl::class,
            // Issue #40 c5 — gate plugin URLs by per-user grant
            'plugin' => \AhgCore\Http\Middleware\PluginAccessMiddleware::class,
            // Issue #72 — gate /admin/privacy/* on the dp_enabled master toggle.
            // Registered here (not in AhgPrivacyServiceProvider via
            // Route::aliasMiddleware) because in Laravel 11/12 the alias map is
            // snapshotted at app-boot before service providers boot, so a
            // provider-level alias registration arrives too late for routes
            // that resolve middleware on the first request.
            'dp.enabled' => \AhgPrivacy\Middleware\EnsureDataProtectionEnabled::class,
        ]);

        // Payment gateway webhooks — server-to-server, no CSRF token available
        $middleware->validateCsrfTokens(except: [
            'cart/payment/notify',
            'marketplace/payfast/notify',
            // IIIF Web Annotations REST API (#100). Called by the
            // mirador-annotations plugin from within the embedded viewer;
            // the plugin's fetch shape doesn't know about Laravel CSRF.
            // Endpoint is session-auth-gated for writes (auth.required
            // middleware on POST/PUT/DELETE) so cross-site forgery is
            // already blocked at the auth layer.
            'api/annotations',
            'api/annotations/*',
            // OAI-PMH 2.0 spec mandates POST verb support (#655 Phase 2).
            // Harvesters are server-to-server clients that have no CSRF
            // token; the endpoint enforces its own auth via optional
            // X-API-Key when ahg_settings.oai_authentication_enabled='1'.
            'oai',
            // #674 Phase 2 - upstream mail provider webhook. HMAC-validated
            // against ahg_settings.email_bounce_webhook_secret; no session.
            'webhooks/email/bounce',
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
            if ($request->is('api/*') && ! app()->hasDebugModeEnabled()) {
                return response()->json([
                    'error' => 'Internal Server Error',
                    'message' => 'An unexpected error occurred.',
                ], 500);
            }
        });
    })->create();
