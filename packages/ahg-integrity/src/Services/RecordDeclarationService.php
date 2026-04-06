<?php

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RecordDeclarationService
{
    /**
     * Declare an information object as a formal record.
     * Creates a pending declaration, optionally starting a workflow.
     *
     * @return int|null The declaration ID or workflow task ID
     */
    public function declareRecord(int $ioId, int $userId): ?int
    {
        if (!Schema::hasTable('record_declaration')) {
            return null;
        }

        // Check if already declared
        $existing = DB::table('record_declaration')
            ->where('information_object_id', $ioId)
            ->first();

        if ($existing && $existing->status === 'declared') {
            return (int) $existing->id;
        }

        $now = Carbon::now();

        if ($existing) {
            // Update existing record to pending_approval
            DB::table('record_declaration')
                ->where('id', $existing->id)
                ->update([
                    'status' => 'pending_approval',
                    'updated_at' => $now,
                ]);
            $declarationId = (int) $existing->id;
        } else {
            // Insert new declaration
            $declarationId = (int) DB::table('record_declaration')->insertGetId([
                'information_object_id' => $ioId,
                'status' => 'pending_approval',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Check if a "Record Declaration" workflow exists and start it
        if (Schema::hasTable('ahg_workflow')) {
            $workflow = DB::table('ahg_workflow')
                ->where('name', 'Record Declaration')
                ->where('is_active', 1)
                ->first();

            if ($workflow) {
                try {
                    $workflowService = app(\AhgWorkflow\Services\WorkflowService::class);
                    $taskId = $workflowService->startWorkflow(
                        $workflow->id,
                        $ioId,
                        'information_object',
                        $userId
                    );

                    if ($taskId) {
                        DB::table('record_declaration')
                            ->where('id', $declarationId)
                            ->update([
                                'workflow_task_id' => $taskId,
                                'updated_at' => Carbon::now(),
                            ]);
                        return $taskId;
                    }
                } catch (\Throwable $e) {
                    // Workflow service unavailable; continue without workflow
                }
            }
        }

        return $declarationId;
    }

    /**
     * Approve a pending declaration, marking the IO as a declared record.
     */
    public function approveDeclaration(int $ioId, int $userId): bool
    {
        if (!Schema::hasTable('record_declaration')) {
            return false;
        }

        $now = Carbon::now();

        $affected = DB::table('record_declaration')
            ->where('information_object_id', $ioId)
            ->where('status', 'pending_approval')
            ->update([
                'status' => 'declared',
                'declared_by' => $userId,
                'declared_at' => $now,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Check if an IO is a declared record.
     */
    public function isRecord(int $ioId): bool
    {
        if (!Schema::hasTable('record_declaration')) {
            return false;
        }

        return DB::table('record_declaration')
            ->where('information_object_id', $ioId)
            ->where('status', 'declared')
            ->exists();
    }

    /**
     * Get the record declaration status for an IO.
     */
    public function getRecordStatus(int $ioId): ?string
    {
        if (!Schema::hasTable('record_declaration')) {
            return null;
        }

        $record = DB::table('record_declaration')
            ->where('information_object_id', $ioId)
            ->first();

        return $record ? $record->status : null;
    }

    /**
     * Get paginated list of declared records.
     */
    public function getDeclaredRecords(int $page = 1, int $perPage = 25): array
    {
        return $this->getDeclarationsByStatus('declared', $page, $perPage);
    }

    /**
     * Get all pending declarations.
     */
    public function getPendingDeclarations(): array
    {
        if (!Schema::hasTable('record_declaration')) {
            return [];
        }

        $culture = app()->getLocale();

        return DB::table('record_declaration as rd')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('rd.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('user as u', 'rd.declared_by', '=', 'u.id')
            ->where('rd.status', 'pending_approval')
            ->select([
                'rd.*',
                'io_i18n.title as io_title',
                'u.username as declared_by_name',
            ])
            ->orderBy('rd.created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get paginated declarations by status.
     */
    private function getDeclarationsByStatus(string $status, int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('record_declaration')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('record_declaration as rd')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('rd.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('user as u', 'rd.declared_by', '=', 'u.id')
            ->where('rd.status', $status);

        $total = $query->count();

        $data = $query->select([
                'rd.*',
                'io_i18n.title as io_title',
                'u.username as declared_by_name',
            ])
            ->orderBy('rd.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get all declarations (any status) paginated.
     */
    public function getAllDeclarations(int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('record_declaration')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('record_declaration as rd')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('rd.information_object_id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('user as u', 'rd.declared_by', '=', 'u.id');

        $total = $query->count();

        $data = $query->select([
                'rd.*',
                'io_i18n.title as io_title',
                'u.username as declared_by_name',
            ])
            ->orderBy('rd.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
