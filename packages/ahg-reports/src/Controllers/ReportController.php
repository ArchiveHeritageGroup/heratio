<?php

namespace AhgReports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function dashboard()
    {
        // Count queries for stats
        $ioCount = DB::table('information_object')->count();
        $actorCount = DB::table('actor')->count();
        $repositoryCount = DB::table('repository')->count();
        $accessionCount = DB::table('accession')->count();
        $digitalObjectCount = DB::table('digital_object')->count();
        $userCount = DB::table('user')->count();

        // Recent activity — prefer ahg_audit_log, fall back to audit_log
        $recentActivity = [];

        if (Schema::hasTable('ahg_audit_log')) {
            $recentActivity = DB::table('ahg_audit_log')
                ->select([
                    'id',
                    'uuid',
                    'user_id',
                    'username',
                    'user_email',
                    'ip_address',
                    'user_agent',
                    'session_id',
                    'action',
                    'entity_type',
                    'entity_id',
                    'entity_slug',
                    'entity_title',
                    'module',
                    'action_name',
                    'request_method',
                    'request_uri',
                    'old_values',
                    'new_values',
                    'changed_fields',
                    'metadata',
                    'security_classification',
                    'status',
                    'error_message',
                    'created_at',
                    'culture_id',
                ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->toArray();
        } elseif (Schema::hasTable('audit_log')) {
            $recentActivity = DB::table('audit_log')
                ->select([
                    'id',
                    'table_name',
                    'record_id',
                    'action',
                    'field_name',
                    'old_value',
                    'new_value',
                    'old_record',
                    'new_record',
                    'user_id',
                    'username',
                    'ip_address',
                    'user_agent',
                    'module',
                    'action_description',
                    'created_at',
                ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->toArray();
        }

        $auditTable = Schema::hasTable('ahg_audit_log') ? 'ahg_audit_log' : 'audit_log';

        return view('ahg-reports::dashboard', [
            'stats' => [
                'descriptions' => $ioCount,
                'authorities' => $actorCount,
                'repositories' => $repositoryCount,
                'accessions' => $accessionCount,
                'digital_objects' => $digitalObjectCount,
                'users' => $userCount,
            ],
            'recentActivity' => $recentActivity,
            'auditTable' => $auditTable,
        ]);
    }
}
