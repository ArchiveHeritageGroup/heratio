<?php

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DisposalExecutionService
{
    /**
     * Execute destruction of an information object's digital objects.
     */
    public function executeDestroy(int $disposalActionId, int $userId): array
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action) {
            return ['success' => false, 'error' => 'Disposal action not found.'];
        }

        if ($action->status !== 'cleared') {
            return ['success' => false, 'error' => 'Disposal action must be in "cleared" status to execute. Current status: ' . $action->status];
        }

        // Verify no active legal hold
        if (Schema::hasTable('integrity_legal_hold')) {
            $activeHold = DB::table('integrity_legal_hold')
                ->where('information_object_id', $action->information_object_id)
                ->where('status', 'active')
                ->exists();

            if ($activeHold) {
                return ['success' => false, 'error' => 'Cannot execute destruction: information object is under active legal hold.'];
            }
        }

        return DB::transaction(function () use ($action, $disposalActionId, $userId) {
            $ioId = $action->information_object_id;
            $filesDeleted = [];
            $contentHashes = [];

            // Get digital objects for this IO
            $digitalObjects = DB::table('digital_object')
                ->where('object_id', $ioId)
                ->get();

            foreach ($digitalObjects as $do) {
                $filePath = $do->path;

                // Compute final content hash before deletion
                $hash = null;
                if ($filePath && file_exists($filePath)) {
                    $hash = hash_file('sha256', $filePath);
                    $contentHashes[] = $hash;

                    // Log the file path before deleting
                    \Log::info("Disposal destruction: deleting file [{$filePath}] for IO [{$ioId}], hash [{$hash}]");

                    // Delete the physical file
                    unlink($filePath);

                    $filesDeleted[] = [
                        'digital_object_id' => $do->id,
                        'path' => $filePath,
                        'hash' => $hash,
                    ];
                } elseif ($filePath) {
                    \Log::warning("Disposal destruction: file not found [{$filePath}] for IO [{$ioId}]");
                    $filesDeleted[] = [
                        'digital_object_id' => $do->id,
                        'path' => $filePath,
                        'hash' => null,
                        'note' => 'File not found on disk',
                    ];
                }
            }

            // Generate combined content hash for certificate
            $combinedHash = !empty($contentHashes) ? hash('sha256', implode('', $contentHashes)) : null;

            // Generate destruction certificate
            $certificateNumber = $this->generateCertificateNumber();

            $user = DB::table('user')
                ->leftJoin('actor_i18n', function ($join) {
                    $join->on('user.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', 'en');
                })
                ->where('user.id', $userId)
                ->select('user.username', 'actor_i18n.authorized_form_of_name')
                ->first();

            $authorizedBy = $user->authorized_form_of_name ?? $user->username ?? 'User #' . $userId;

            $certificateId = DB::table('destruction_certificate')->insertGetId([
                'information_object_id' => $ioId,
                'certificate_number' => $certificateNumber,
                'destruction_date' => Carbon::now(),
                'destruction_method' => 'secure_delete',
                'authorized_by' => $authorizedBy,
                'content_hash' => $combinedHash,
                'metadata' => json_encode([
                    'files_deleted' => $filesDeleted,
                    'disposal_action_id' => $disposalActionId,
                    'action_type' => $action->action_type,
                ]),
                'created_at' => Carbon::now(),
            ]);

            // Update disposal action
            DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
                'status' => 'executed',
                'executed_by' => $userId,
                'executed_at' => Carbon::now(),
                'certificate_id' => $certificateId,
                'updated_at' => Carbon::now(),
            ]);

            // Update record_declaration if exists
            if (Schema::hasTable('record_declaration')) {
                DB::table('record_declaration')
                    ->where('information_object_id', $ioId)
                    ->update([
                        'status' => 'destroyed',
                        'updated_at' => Carbon::now(),
                    ]);
            }

            // Audit log
            $this->auditLog($userId, 'delete', 'rm_disposal_action', $disposalActionId, 'Disposal executed: destruction', [
                'certificate_number' => $certificateNumber,
                'files_deleted' => count($filesDeleted),
                'information_object_id' => $ioId,
            ]);

            return [
                'success' => true,
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber,
                'files_deleted' => $filesDeleted,
            ];
        });
    }

    /**
     * Execute transfer of an information object.
     */
    public function executeTransfer(int $disposalActionId, string $destination, int $userId): array
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action) {
            return ['success' => false, 'error' => 'Disposal action not found.'];
        }

        if ($action->status !== 'cleared') {
            return ['success' => false, 'error' => 'Disposal action must be in "cleared" status to execute. Current status: ' . $action->status];
        }

        return DB::transaction(function () use ($action, $disposalActionId, $destination, $userId) {
            $ioId = $action->information_object_id;

            // Update disposal action
            DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
                'status' => 'executed',
                'executed_by' => $userId,
                'executed_at' => Carbon::now(),
                'transfer_destination' => $destination,
                'updated_at' => Carbon::now(),
            ]);

            // Update record_declaration if exists
            if (Schema::hasTable('record_declaration')) {
                DB::table('record_declaration')
                    ->where('information_object_id', $ioId)
                    ->update([
                        'status' => 'transferred',
                        'updated_at' => Carbon::now(),
                    ]);
            }

            // Audit log
            $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal executed: transfer', [
                'destination' => $destination,
                'information_object_id' => $ioId,
            ]);

            return [
                'success' => true,
                'destination' => $destination,
            ];
        });
    }

    /**
     * Execute retain — mark as permanently retained.
     */
    public function executeRetain(int $disposalActionId, int $userId, string $reason): array
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action) {
            return ['success' => false, 'error' => 'Disposal action not found.'];
        }

        if (!in_array($action->status, ['cleared', 'approved'])) {
            return ['success' => false, 'error' => 'Disposal action must be in "cleared" or "approved" status to retain. Current status: ' . $action->status];
        }

        return DB::transaction(function () use ($action, $disposalActionId, $userId, $reason) {
            // Update disposal action
            DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
                'status' => 'retained',
                'executed_by' => $userId,
                'executed_at' => Carbon::now(),
                'notes' => $action->notes ? $action->notes . "\nRetained: " . $reason : "Retained: " . $reason,
                'updated_at' => Carbon::now(),
            ]);

            // Audit log
            $this->auditLog($userId, 'update', 'rm_disposal_action', $disposalActionId, 'Disposal executed: retain', [
                'reason' => $reason,
                'information_object_id' => $action->information_object_id,
            ]);

            return [
                'success' => true,
                'reason' => $reason,
            ];
        });
    }

    /**
     * Verify destruction per DoD 5015.2 requirements.
     */
    public function verifyDestruction(int $disposalActionId): array
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (!$action) {
            return ['verified' => false, 'checks' => [], 'failures' => ['Disposal action not found.']];
        }

        $checks = [];
        $failures = [];

        // 1. Check: certificate exists and has valid certificate_number
        $certificate = null;
        if ($action->certificate_id && Schema::hasTable('destruction_certificate')) {
            $certificate = DB::table('destruction_certificate')
                ->where('id', $action->certificate_id)
                ->first();
        }

        if ($certificate) {
            $checks[] = ['name' => 'Destruction certificate exists', 'passed' => true];

            // Validate certificate number format DC-YYYY-NNNNN
            $validFormat = (bool) preg_match('/^DC-\d{4}-\d{5}$/', $certificate->certificate_number);
            if ($validFormat) {
                $checks[] = ['name' => 'Certificate number valid', 'passed' => true];
            } else {
                $checks[] = ['name' => 'Certificate number valid', 'passed' => false];
                $failures[] = 'Certificate number format invalid: ' . $certificate->certificate_number;
            }

            // 3. Check: content_hash is populated
            if (!empty($certificate->content_hash)) {
                $checks[] = ['name' => 'Content hash recorded', 'passed' => true];
            } else {
                $checks[] = ['name' => 'Content hash recorded', 'passed' => false];
                $failures[] = 'No content hash recorded on destruction certificate.';
            }
        } else {
            $checks[] = ['name' => 'Destruction certificate exists', 'passed' => false];
            $checks[] = ['name' => 'Certificate number valid', 'passed' => false];
            $checks[] = ['name' => 'Content hash recorded', 'passed' => false];
            $failures[] = 'No destruction certificate found for this disposal action.';
        }

        // 4. Check: all digital_object files for this IO are actually gone from disk
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $action->information_object_id)
            ->get();

        $allFilesGone = true;
        foreach ($digitalObjects as $do) {
            if ($do->path && file_exists($do->path)) {
                $allFilesGone = false;
                $failures[] = 'File still exists on disk: ' . $do->path;
            }
        }
        $checks[] = ['name' => 'Digital object files removed from disk', 'passed' => $allFilesGone];

        // 5. Check: ahg_audit_log has deletion entry
        $auditEntryExists = false;
        if (Schema::hasTable('ahg_audit_log')) {
            $auditEntryExists = DB::table('ahg_audit_log')
                ->where('entity_type', 'rm_disposal_action')
                ->where('entity_id', $disposalActionId)
                ->where('action', 'delete')
                ->exists();
        }
        if ($auditEntryExists) {
            $checks[] = ['name' => 'Audit log entry exists', 'passed' => true];
        } else {
            $checks[] = ['name' => 'Audit log entry exists', 'passed' => false];
            $failures[] = 'No deletion audit log entry found.';
        }

        $verified = empty($failures);

        // Store verification result
        DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
            'verification_status' => $verified ? 'verified' : 'failed',
            'verification_details' => json_encode(['checks' => $checks, 'failures' => $failures]),
            'verified_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return [
            'verified' => $verified,
            'checks' => $checks,
            'failures' => $failures,
        ];
    }

    /**
     * Generate a unique destruction certificate number in DC-YYYY-NNNNN format.
     */
    private function generateCertificateNumber(): string
    {
        $year = date('Y');
        $prefix = 'DC-' . $year . '-';

        $lastCert = DB::table('destruction_certificate')
            ->where('certificate_number', 'LIKE', $prefix . '%')
            ->orderBy('certificate_number', 'desc')
            ->value('certificate_number');

        if ($lastCert) {
            $lastNum = (int) substr($lastCert, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Write to ahg_audit_log if the table exists.
     */
    private function auditLog(int $userId, string $action, string $entityType, int $entityId, string $title, array $metadata = []): void
    {
        if (!Schema::hasTable('ahg_audit_log')) {
            return;
        }

        $user = DB::table('user')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->select('user.username', 'user.email', 'actor_i18n.authorized_form_of_name')
            ->first();

        DB::table('ahg_audit_log')->insert([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $userId,
            'username' => $user->username ?? null,
            'user_email' => $user->email ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_title' => $title,
            'module' => 'records-manage',
            'action_name' => $action,
            'request_method' => request()->method(),
            'request_uri' => request()->getRequestUri(),
            'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            'status' => 'success',
            'created_at' => Carbon::now(),
        ]);
    }
}
