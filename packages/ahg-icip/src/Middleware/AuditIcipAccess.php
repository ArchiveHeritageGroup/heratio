<?php

namespace AhgIcip\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AuditIcipAccess
 *
 * Middleware to record ICIP object view/access when audit_all_icip_access is enabled.
 */
class AuditIcipAccess
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('icip_config')) {
                $flag = DB::table('icip_config')->where('config_key', 'audit_all_icip_access')->value('config_value');
                if ($flag && intval($flag) === 1) {
                    $userId = auth()->check() ? auth()->id() : null;
                    $ip = $request->ip();
                    $path = $request->path();
                    $ts = now();
                    // lightweight audit insert - non-blocking
                    DB::table('icip_access_log')->insert([
                        'user_id' => $userId,
                        'ip_address' => $ip,
                        'path' => $path,
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ]);
                    Log::info('ICIP access audit', ['user_id' => $userId, 'ip' => $ip, 'path' => $path]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AuditIcipAccess middleware failed: ' . $e->getMessage());
        }

        return $next($request);
    }
}
