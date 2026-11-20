<?php

namespace AhgRecordsManage\Services;

use AhgCore\Services\AclService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\SharedUploadsGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgRecordsManage\Services\Concerns\WritesDisposalAuditLog;

class DisposalExecutionService
{
    use WritesDisposalAuditLog;

    /**
     * Execute destruction of an information object's digital objects.
     */
    public function executeDestroy(int $disposalActionId, int $userId): array
    {
        // #1374: irreversible record destruction is administrators-only - not the
        // EDITOR-inclusive canAdmin gate the route group uses.
        if (! AclService::isAdministrator()) {
            return ['success' => false, 'error' => 'Record destruction is restricted to administrators.'];
        }

        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (! $action) {
            return ['success' => false, 'error' => 'Disposal action not found.'];
        }

        if ($action->status !== 'cleared') {
            return ['success' => false, 'error' => 'Disposal action must be in "cleared" status to execute. Current status: '.$action->status];
        }

        // Separation of duties (#1374): the executor may not be the approver or the
        // legal-clearer - the destruction certificate must be a four-eyes act.
        if (in_array((int) $userId, [(int) $action->approved_by, (int) $action->legal_cleared_by], true)) {
            return ['success' => false, 'error' => 'Separation of duties: destruction must be executed by someone other than the approver or legal-clearer.'];
        }

        // Verify no active legal hold on the record or an aggregation above it
        if (LegalHoldGuard::isHeld((int) $action->information_object_id)) {
            return ['success' => false, 'error' => __('Cannot execute destruction: the record, or a file or series it belongs to, is under an active legal hold.')];
        }

        // heratio#1740: digital_object.path is the web folder (/uploads/r/<id>/),
        // not the file, so it was never found on disk and nothing was deleted.
        // Resolve the real file for the master AND its derivatives (reference
        // copy, thumbnail) - they are copies of the record too.
        $targets = [];
        foreach ($this->componentsOf((int) $action->information_object_id) as $do) {
            $targets[] = [$do, DigitalObjectService::resolveDiskPath($do)];
        }

        // A file another system's catalogue still uses (shared uploads folder)
        // is never deleted. Refuse the whole destruction before touching anything.
        foreach ($targets as [$do, $file]) {
            if ($file && ($why = SharedUploadsGuard::refuse($file))) {
                return ['success' => false, 'error' => __('Cannot execute destruction: :file - :reason', ['file' => $do->name, 'reason' => $why])];
            }
        }

        return DB::transaction(function () use ($action, $disposalActionId, $userId, $targets) {
            $ioId = $action->information_object_id;
            $filesDeleted = [];
            $contentHashes = [];

            foreach ($targets as [$do, $filePath]) {
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
                } else {
                    \Log::warning("Disposal destruction: file not found [{$do->path}{$do->name}] for IO [{$ioId}]");
                    $filesDeleted[] = [
                        'digital_object_id' => $do->id,
                        'path' => $do->path.$do->name,
                        'hash' => null,
                        'note' => 'File not found on disk',
                    ];
                }
            }

            // Generate combined content hash for certificate
            $combinedHash = ! empty($contentHashes) ? hash('sha256', implode('', $contentHashes)) : null;

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

            $authorizedBy = $user->authorized_form_of_name ?? $user->username ?? 'User #'.$userId;

            $certificateId = DB::table('destruction_certificate')->insertGetId([
                'information_object_id' => $ioId,
                'certificate_number' => $certificateNumber,
                'destruction_date' => Carbon::now(),
                'destruction_method' => 'file_deletion', // the files are unlinked, not overwritten: say so on the certificate
                'authorized_by' => $authorizedBy,
                'content_hash' => $combinedHash,
                'metadata' => json_encode([
                    'files_deleted' => $filesDeleted,
                    'disposal_action_id' => $disposalActionId,
                    'action_type' => $action->action_type,
                    // heratio#1740: the certificate carries the justification and
                    // the four-eyes chain, so it stands on its own as evidence.
                    'reason' => $action->reason,
                    'disposal_class_id' => $action->disposal_class_id,
                    'initiated_by' => $action->initiated_by,
                    'initiated_at' => $action->initiated_at,
                    'recommended_by' => $action->recommended_by,
                    'approved_by' => $action->approved_by,
                    'approved_at' => $action->approved_at,
                    'legal_cleared_by' => $action->legal_cleared_by,
                    'legal_cleared_at' => $action->legal_cleared_at,
                    'executed_by' => $userId,
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
        if (! $action) {
            return ['success' => false, 'error' => 'Disposal action not found.'];
        }

        if ($action->status !== 'cleared') {
            return ['success' => false, 'error' => 'Disposal action must be in "cleared" status to execute. Current status: '.$action->status];
        }

        // heratio#1740: a held record may not leave custody either.
        if (LegalHoldGuard::isHeld((int) $action->information_object_id)) {
            return ['success' => false, 'error' => __('Cannot execute transfer: the record, or a file or series it belongs to, is under an active legal hold.')];
        }

        $ioId = $action->information_object_id;

        // P3.6 - Transfer to archives: build a BagIt AIP via OaisLifecycleService BEFORE
        // we mark the disposal as executed, so a build failure aborts the whole transition.
        // External transfers (action_type='transfer_external') skip the bag - they're handed
        // off to a third party out-of-band; the AIP is only meaningful when the destination is
        // the in-house preservation store.
        $aipResult = null;
        $aipError = null;
        if ($action->action_type === 'transfer_archives') {
            try {
                $oais = app(\AhgPreservation\Services\OaisLifecycleService::class);
                $aipResult = $oais->createAipFromIO((int) $ioId, [
                    'description' => 'Transfer to archives via disposal action #'.$disposalActionId,
                    'source_organization' => 'Heratio',
                    'submission_agreement' => 'Disposal action #'.$disposalActionId,
                    'include_descendants' => true,
                    'zip' => true,
                ], 'disposal-action-'.$disposalActionId);
            } catch (\Throwable $e) {
                $aipError = $e->getMessage();
            }
            if ($aipError !== null) {
                return [
                    'success' => false,
                    'error' => 'AIP build failed during transfer: '.$aipError,
                ];
            }
        }

        return DB::transaction(function () use ($action, $disposalActionId, $destination, $userId, $aipResult) {
            $ioId = $action->information_object_id;

            // Compose a notes line that captures the AIP linkage for audit.
            $existingNotes = (string) ($action->notes ?? '');
            $aipLine = $aipResult
                ? sprintf('Transfer to archives: AIP package #%d, %d files, %d bytes, bag at %s',
                    $aipResult['aip_id'],
                    $aipResult['payload_files'],
                    $aipResult['total_size'],
                    $aipResult['bag_path'])
                : null;

            $newNotes = trim(($existingNotes !== '' ? $existingNotes."\n" : '').($aipLine ?? ''));

            DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
                'status' => 'executed',
                'executed_by' => $userId,
                'executed_at' => Carbon::now(),
                'transfer_destination' => $aipResult
                    ? sprintf('Heratio archives (AIP #%d)', $aipResult['aip_id'])
                    : $destination,
                'notes' => $newNotes !== '' ? $newNotes : null,
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
                'aip_package_id' => $aipResult['aip_id'] ?? null,
                'aip_files' => $aipResult['payload_files'] ?? null,
                'aip_bytes' => $aipResult['total_size'] ?? null,
            ]);

            return [
                'success' => true,
                'destination' => $destination,
                'aip_package_id' => $aipResult['aip_id'] ?? null,
                'aip_files' => $aipResult['payload_files'] ?? null,
                'aip_bytes' => $aipResult['total_size'] ?? null,
                'aip_bag_path' => $aipResult['bag_path'] ?? null,
            ];
        });
    }

    /**
     * Execute retain - mark as permanently retained.
     */
    public function executeRetain(int $disposalActionId, int $userId, string $reason): array
    {
        $action = DB::table('rm_disposal_action')->where('id', $disposalActionId)->first();
        if (! $action) {
            return ['success' => false, 'error' => 'Disposal action not found.'];
        }

        if (! in_array($action->status, ['cleared', 'approved'])) {
            return ['success' => false, 'error' => 'Disposal action must be in "cleared" or "approved" status to retain. Current status: '.$action->status];
        }

        return DB::transaction(function () use ($action, $disposalActionId, $userId, $reason) {
            // Update disposal action
            DB::table('rm_disposal_action')->where('id', $disposalActionId)->update([
                'status' => 'retained',
                'executed_by' => $userId,
                'executed_at' => Carbon::now(),
                'notes' => $action->notes ? $action->notes."\nRetained: ".$reason : 'Retained: '.$reason,
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
        if (! $action) {
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
                $failures[] = 'Certificate number format invalid: '.$certificate->certificate_number;
            }

            // 3. Check: content_hash is populated
            if (! empty($certificate->content_hash)) {
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

        // 4. Check: all files of the record (masters and derivatives) are gone from disk
        $allFilesGone = true;
        foreach ($this->componentsOf((int) $action->information_object_id) as $do) {
            if ($file = DigitalObjectService::resolveDiskPath($do)) {
                $allFilesGone = false;
                $failures[] = 'File still exists on disk: '.$file;
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
     * The record's digital objects: its masters plus their derivatives
     * (reference copies and thumbnails, which hang off the master by parent_id).
     */
    private function componentsOf(int $ioId): \Illuminate\Support\Collection
    {
        $masters = DB::table('digital_object')->where('object_id', $ioId)->get();
        if ($masters->isEmpty()) {
            return $masters;
        }

        return $masters->merge(DB::table('digital_object')->whereIn('parent_id', $masters->pluck('id'))->get());
    }

    /**
     * Generate a unique destruction certificate number in DC-YYYY-NNNNN format.
     */
    private function generateCertificateNumber(): string
    {
        $year = date('Y');
        $prefix = 'DC-'.$year.'-';

        $lastCert = DB::table('destruction_certificate')
            ->where('certificate_number', 'LIKE', $prefix.'%')
            ->orderBy('certificate_number', 'desc')
            ->value('certificate_number');

        if ($lastCert) {
            $lastNum = (int) substr($lastCert, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix.str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

}
