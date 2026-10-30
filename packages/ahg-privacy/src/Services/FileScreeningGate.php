<?php

/**
 * FileScreeningGate - in a repository or collection that requires screening,
 * a record's digital objects (thumbnail, reference, master, IIIF, download)
 * are withheld from visitors until the record has been screened for personal
 * information and found clear, redacted, or released by a person (heratio#1574).
 * The catalogue description is not affected.
 *
 * Scope comes from two settings, each a comma-separated id list:
 *   privacy_screening_repositories  - repository ids
 *   privacy_screening_collections   - information object ids (whole subtree)
 * Both empty (the default) means nothing needs screening. The ground (POPIA,
 * GDPR, ...) is the scan report's jurisdiction, so the rule is market-neutral.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Services;

use AhgPrivacy\Models\PiiScanReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FileScreeningGate
{
    /** Scan statuses that release a record's files even though the scan had hits. */
    private const RELEASED = [PiiScanReport::STATUS_REDACTED, PiiScanReport::STATUS_ACCEPTED_RISK];

    /** Does this record's files have to wait for screening, and has it not cleared it? */
    public function withholds(int $ioId): bool
    {
        return $this->requiresScreening($ioId) && ! $this->cleared($ioId);
    }

    public function requiresScreening(int $ioId): bool
    {
        $repos = $this->ids('privacy_screening_repositories');
        $roots = $this->ids('privacy_screening_collections');
        if ($repos === [] && $roots === []) {
            return false;
        }

        $io = DB::table('information_object')->where('id', $ioId)->first(['id', 'repository_id', 'lft']);
        if (! $io) {
            return false;
        }
        if ($repos !== [] && in_array((int) $io->repository_id, $repos, true)) {
            return true;
        }
        if ($roots === []) {
            return false;
        }
        if (in_array($ioId, $roots, true)) {
            return true;
        }
        // Same two sources DisclosureGate::subtreeIds() trusts: the closure table,
        // and the nested-set range for records the closure has not caught up with.
        if (Schema::hasTable('information_object_closure')
            && DB::table('information_object_closure')->where('descendant', $ioId)->whereIn('ancestor', $roots)->exists()) {
            return true;
        }

        return $io->lft !== null && DB::table('information_object')->whereIn('id', $roots)
            ->where('lft', '<=', $io->lft)->where('rgt', '>=', $io->lft)->exists();
    }

    /**
     * Cleared = on the latest scan report, a person released or redacted the
     * files, or a finished scan that read the files' text (coverage 'files')
     * found nothing and nobody has withheld them since ('reviewed' is a
     * person's withhold).
     */
    public function cleared(int $ioId): bool
    {
        if (! Schema::hasTable('ahg_pii_scan_report')) {
            return false;
        }
        $latest = DB::table('ahg_pii_scan_report')->where('information_object_id', $ioId)
            ->orderByDesc('id')->first();
        if ($latest === null) {
            return false;
        }
        if (in_array($latest->status, self::RELEASED, true)) {
            return true;
        }

        return $latest->scan_finished_at !== null && (int) $latest->hits_total === 0
            && $latest->status === PiiScanReport::STATUS_PENDING
            && ($latest->coverage ?? 'metadata') === 'files';
    }

    /**
     * Release or withhold a record's files by hand, on the record's latest scan,
     * and audit who, why and when. Withholding with no scan on file records one;
     * releasing needs a scan, so nothing is released unread.
     */
    public function decide(int $ioId, bool $release, string $ground, ?string $note = null): void
    {
        $latest = DB::table('ahg_pii_scan_report')->where('information_object_id', $ioId)->orderByDesc('id')->first();
        $old = $latest ? ['status' => $latest->status] : null;
        $status = $release ? PiiScanReport::STATUS_ACCEPTED_RISK : PiiScanReport::STATUS_REVIEWED;

        if ($latest) {
            DB::table('ahg_pii_scan_report')->where('id', $latest->id)->update(['status' => $status, 'notes' => $note, 'updated_at' => now()]);
        } elseif ($release) {
            throw new \RuntimeException('A record must be scanned before its files can be released.');
        } else {
            DB::table('ahg_pii_scan_report')->insert([
                'information_object_id' => $ioId, 'scan_started_at' => now(), 'scan_finished_at' => now(),
                'jurisdiction' => $ground, 'status' => $status, 'scanned_by_user_id' => Auth::id(), 'notes' => $note,
            ]);
        }

        DB::table('privacy_audit_log')->insert([
            'entity_type' => 'information_object_files',
            'entity_id' => $ioId,
            'action' => $release ? 'files_released' : 'files_withheld',
            'user_id' => Auth::id(),
            'ip_address' => request()?->ip(),
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => json_encode(['status' => $status, 'ground' => $ground]),
            'notes' => $note,
            'created_at' => now(),
        ]);
    }

    /** @return int[] */
    private function ids(string $key): array
    {
        try {
            $raw = (string) DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw)), fn ($id) => $id > 0));
    }
}
