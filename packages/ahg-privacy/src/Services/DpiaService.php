<?php

/**
 * DpiaService - GDPR Article 35 Data Protection Impact Assessment workflow.
 *
 * Sign-off writes a chained row through ahg-audit-trail's ChainedAuditWriter
 * (#676 Phase 5) so the assessment is tamper-evident. We resolve the writer
 * defensively because Phase 5 is shipped in audit-trail but may not have
 * provisioned its Ed25519 keypair on every install yet - in that case the
 * writer falls back to an unsigned-row insert and the sign-off still records.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Services;

use AhgPrivacy\Models\Dpia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DpiaService
{
    /** @return Collection<int,Dpia> */
    public function listAll(): Collection
    {
        return Dpia::query()->orderByDesc('updated_at')->get();
    }

    public function find(int $id): ?Dpia
    {
        return Dpia::query()->find($id);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, ?int $createdByUserId = null): Dpia
    {
        $payload = $this->sanitize($data);
        $payload['created_by_user_id'] = $createdByUserId;
        if (! in_array($payload['status'] ?? 'draft', Dpia::statuses(), true)) {
            $payload['status'] = Dpia::STATUS_DRAFT;
        }
        return Dpia::query()->create($payload);
    }

    /** @param array<string,mixed> $data */
    public function update(Dpia $dpia, array $data): Dpia
    {
        $dpia->fill($this->sanitize($data));
        $dpia->save();
        return $dpia;
    }

    public function moveToReview(Dpia $dpia): Dpia
    {
        $dpia->status = Dpia::STATUS_REVIEW;
        $dpia->save();
        return $dpia;
    }

    /**
     * Sign the DPIA off as completed. Writes a tamper-evident chain row via
     * ChainedAuditWriter on best effort - the DPIA row still updates even if
     * the chain writer is unavailable.
     */
    public function signOff(Dpia $dpia, int $userId, ?string $note = null): Dpia
    {
        $dpia->status = Dpia::STATUS_COMPLETED;
        $dpia->signed_off_by_user_id = $userId;
        $dpia->signed_off_at = now();
        if ($dpia->completed_at === null) {
            $dpia->completed_at = now()->toDateString();
        }
        $dpia->save();

        $this->writeChainEvent('dpia.signoff', $dpia, $userId, $note);
        return $dpia;
    }

    public function archive(Dpia $dpia, int $userId): Dpia
    {
        $dpia->status = Dpia::STATUS_ARCHIVED;
        $dpia->save();
        $this->writeChainEvent('dpia.archive', $dpia, $userId, null);
        return $dpia;
    }

    /** @param array<string,mixed> $data */
    private function sanitize(array $data): array
    {
        return [
            'name'                     => trim((string) ($data['name'] ?? '')),
            'processing_activity_id'   => isset($data['processing_activity_id']) && $data['processing_activity_id'] !== ''
                ? (int) $data['processing_activity_id']
                : null,
            'description'              => $this->nullableText($data['description'] ?? null),
            'necessity_proportionality'=> $this->nullableText($data['necessity_proportionality'] ?? null),
            'risks_to_subjects'        => $this->nullableText($data['risks_to_subjects'] ?? null),
            'measures_to_mitigate'     => $this->nullableText($data['measures_to_mitigate'] ?? null),
            'residual_risks'           => $this->nullableText($data['residual_risks'] ?? null),
            'dpo_opinion'              => $this->nullableText($data['dpo_opinion'] ?? null),
            'dpo_consulted_at'         => $this->nullableDate($data['dpo_consulted_at'] ?? null),
            'completed_at'             => $this->nullableDate($data['completed_at'] ?? null),
            'status'                   => in_array($data['status'] ?? '', Dpia::statuses(), true)
                ? (string) $data['status']
                : Dpia::STATUS_DRAFT,
        ];
    }

    private function nullableText($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    /**
     * Best-effort chain row via the audit-trail package. Failures are logged
     * but never raised - the DPIA workflow must not be blocked by chain issues.
     */
    private function writeChainEvent(string $action, Dpia $dpia, int $userId, ?string $note): void
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return;
        }
        try {
            $row = [
                'user_id'     => $userId,
                'action'      => $action,
                'entity_type' => 'ahg_dpia',
                'entity_id'   => (int) $dpia->id,
                'old_values'  => null,
                'new_values'  => json_encode([
                    'status'                 => $dpia->status,
                    'signed_off_at'          => optional($dpia->signed_off_at)->format('Y-m-d\TH:i:s\Z'),
                    'processing_activity_id' => $dpia->processing_activity_id,
                    'note'                   => $note,
                ], JSON_UNESCAPED_SLASHES),
                'ip_address'  => $this->clientIp(),
                'user_agent'  => $this->userAgent(),
                'created_at'  => now(),
            ];

            $writer = $this->resolveChainWriter();
            if ($writer !== null) {
                $writer->append($row);
                return;
            }
            DB::table('ahg_audit_log')->insert($row);
        } catch (Throwable $e) {
            Log::warning('privacy: dpia chain write failed', [
                'dpia_id' => $dpia->id,
                'action'  => $action,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function resolveChainWriter(): ?object
    {
        $class = 'AhgAuditTrail\\Services\\ChainedAuditWriter';
        if (! class_exists($class)) {
            return null;
        }
        try {
            return app($class);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function clientIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function userAgent(): ?string
    {
        try {
            return request()?->userAgent();
        } catch (Throwable $e) {
            return null;
        }
    }
}
