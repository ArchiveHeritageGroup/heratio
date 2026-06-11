<?php

/**
 * PreservationMaturityService - Heratio ahg-core
 *
 * Read-only preservation MATURITY self-assessment. It scores the running
 * Heratio instance against the five functional areas of the NDSA Levels of
 * Digital Preservation (Levels of Preservation, v2.0), using ONLY evidence the
 * platform actually tracks in its own tables. Nothing here is aspirational: a
 * missing table, a blank column, or a never-run job all push the achieved level
 * DOWN and surface a concrete gap recommendation. Absence is never inflated
 * into a higher score.
 *
 * The NDSA Levels (a widely used, jurisdiction-neutral self-assessment grid)
 * group preservation practice into five functional areas, each scored Level 1
 * (Know your content) through Level 4 (Repair your content):
 *
 *   1. Storage                - multiple copies + geographic / provider diversity
 *   2. Integrity              - fixity (checksums), fixity-checking cadence, write protection
 *   3. Control (security)     - access controls + audit logging of who did what
 *   4. Metadata               - administrative / descriptive / technical / preservation metadata
 *   5. Content (file formats) - format identification (PRONOM/PUID) + format diversity / monitoring
 *
 * We map "Level 0 / Not yet" for an area with no qualifying evidence at all, so
 * an empty instance reads honestly rather than scoring a phantom Level 1.
 *
 * EVERY probe is Schema::hasTable / hasColumn guarded and wrapped in its own
 * try/catch, and every figure is a cheap aggregate (COUNT / EXISTS / DISTINCT)
 * with no per-record loop. A missing optional table simply means "no evidence",
 * which lowers the level - it never throws and never 500s. The service performs
 * NO writes, no ALTER, and makes no AI calls.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreservationMaturityService
{
    /** Highest NDSA level. Levels run 1..4; 0 means "Not yet" (no evidence). */
    public const MAX_LEVEL = 4;

    /** Human-readable names for each achieved level, jurisdiction-neutral. */
    public const LEVEL_NAMES = [
        0 => 'Not yet',
        1 => 'Level 1',
        2 => 'Level 2',
        3 => 'Level 3',
        4 => 'Level 4',
    ];

    /**
     * Build the full maturity assessment.
     *
     * @return array{
     *     areas: array<int, array{key:string,name:string,subtitle:string,level:int,level_name:string,evidence:string,gap:string}>,
     *     overall_level:int,
     *     overall_level_name:string,
     *     max_level:int,
     *     framework:string,
     *     framework_note:string,
     *     digital_objects:int,
     *     generated_at:string,
     *     error:bool
     * }
     */
    public function assess(): array
    {
        $digitalObjects = $this->countDigitalObjects();

        $areas = [];
        $error = false;

        foreach (['storage', 'integrity', 'control', 'metadata', 'content'] as $key) {
            try {
                $method = 'assess'.ucfirst($key);
                $area = $this->{$method}($digitalObjects);
            } catch (\Throwable $e) {
                \Log::warning('[ahg-core] preservation-maturity '.$key.' failed: '.$e->getMessage());
                $area = $this->emptyArea($key);
                $error = true;
            }
            $area['level_name'] = self::LEVEL_NAMES[$area['level']] ?? (string) $area['level'];
            $areas[] = $area;
        }

        // Overall maturity follows the NDSA reading: an organisation is only as
        // mature as its weakest functional area (the chain is only as strong as
        // its weakest link). We report the MINIMUM achieved level across the five.
        $levels = array_map(fn ($a) => (int) $a['level'], $areas);
        $overall = empty($levels) ? 0 : min($levels);

        return [
            'areas'              => $areas,
            'overall_level'      => $overall,
            'overall_level_name' => self::LEVEL_NAMES[$overall] ?? (string) $overall,
            'max_level'          => self::MAX_LEVEL,
            'framework'          => 'NDSA Levels of Digital Preservation',
            'framework_note'     => 'Scored against the five functional areas of the NDSA Levels of Digital Preservation (v2.0). The NDSA Levels are a widely used, jurisdiction-neutral self-assessment grid. Scores reflect only what this instance can evidence from its own records; absence of evidence lowers the level rather than being assumed.',
            'digital_objects'    => $digitalObjects,
            'generated_at'       => now()->toDateTimeString(),
            'error'              => $error,
        ];
    }

    // ---------------------------------------------------------------------
    // Area 1: Storage and geographic location
    // NDSA: L1 two copies; L2 three copies + geographic separation; L3
    // copies in geographic + provider/system diversity; L4 ongoing managed
    // replication. Evidence: preservation_replication_target (configured,
    // active copies), target_type diversity (provider/system diversity), and
    // preservation_replication_log (replication actually running). The primary
    // store always counts as one copy.
    // ---------------------------------------------------------------------
    private function assessStorage(int $digitalObjects): array
    {
        $key = 'storage';
        $name = 'Storage and geographic location';
        $subtitle = 'Multiple copies, with geographic and provider or system diversity';

        // The primary repository store is always present (one copy).
        $copies = 1;
        $activeTargets = 0;
        $typeDiversity = 0;
        $replicating = false;

        if (Schema::hasTable('preservation_replication_target')) {
            $activeTargets = $this->guardCount(function () {
                $q = DB::table('preservation_replication_target');
                if (Schema::hasColumn('preservation_replication_target', 'is_active')) {
                    $q->where('is_active', 1);
                }

                return $q->count();
            });

            if (Schema::hasColumn('preservation_replication_target', 'target_type')) {
                $typeDiversity = $this->guardCount(function () {
                    $q = DB::table('preservation_replication_target');
                    if (Schema::hasColumn('preservation_replication_target', 'is_active')) {
                        $q->where('is_active', 1);
                    }

                    return $q->distinct()->count('target_type');
                });
            }
        }

        if (Schema::hasTable('preservation_replication_log')) {
            $synced = $this->guardCount(fn () => DB::table('preservation_replication_log')->count());
            $replicating = $synced > 0;
        }

        $copies += $activeTargets;

        // Score conservatively from copies + diversity + active replication.
        if ($copies <= 1) {
            $level = 0;
            $evidence = 'Only the primary repository store is configured; no additional preservation copies are defined.';
            $gap = 'Configure at least one replication target (preservation_replication_target) so a second, independent copy of every file exists.';
        } elseif ($copies === 2) {
            $level = 1;
            $evidence = $activeTargets.' replication target(s) defined, giving two copies in total.';
            $gap = 'Add a third copy and ensure copies are held in geographically separate locations to reach Level 2.';
        } elseif ($copies >= 3 && $typeDiversity < 2) {
            $level = 2;
            $evidence = $copies.' copies across '.$activeTargets.' target(s), but all use the same storage type.';
            $gap = 'Use at least two different storage providers or system types (e.g. local plus S3 or SFTP) so a single provider or technology failure cannot take out every copy. That lifts this area to Level 3.';
        } elseif ($copies >= 3 && $typeDiversity >= 2 && ! $replicating) {
            $level = 3;
            $evidence = $copies.' copies across '.$typeDiversity.' distinct storage types, but no replication runs are recorded yet.';
            $gap = 'Run and log replication (preservation_replication_log) on a managed schedule, and verify copies, to demonstrate Level 4 ongoing managed storage.';
        } else {
            $level = 4;
            $evidence = $copies.' copies across '.$typeDiversity.' distinct storage types, with replication runs recorded.';
            $gap = 'Maintain the replication schedule and periodically test full restores from each target.';
        }

        return compact('key', 'name', 'subtitle', 'level', 'evidence', 'gap');
    }

    // ---------------------------------------------------------------------
    // Area 2: Integrity (fixity and write protection)
    // NDSA: L1 record fixity (checksums) on ingest; L2 verify fixity on a
    // schedule + write protection; L3 detect corruption + audit fixity; L4
    // repair/replace corrupted content. Evidence: digital_object.checksum /
    // checksum_type and preservation_checksum (recorded fixity),
    // preservation_fixity_check (verification actually run + outcomes), and
    // integrity_legal_hold / integrity_retention_policy (write protection).
    // ---------------------------------------------------------------------
    private function assessIntegrity(int $digitalObjects): array
    {
        $key = 'integrity';
        $name = 'Integrity (fixity and write protection)';
        $subtitle = 'Checksums recorded, fixity verified on a cadence, and content protected from change';

        $withChecksum = 0;
        $withAlgorithm = 0;
        if (Schema::hasTable('digital_object') && Schema::hasColumn('digital_object', 'checksum')) {
            $withChecksum = $this->guardCount(fn () => DB::table('digital_object')
                ->whereNotNull('checksum')
                ->whereRaw("TRIM(checksum) <> ''")
                ->count());
            if (Schema::hasColumn('digital_object', 'checksum_type')) {
                $withAlgorithm = $this->guardCount(fn () => DB::table('digital_object')
                    ->whereNotNull('checksum')
                    ->whereRaw("TRIM(checksum) <> ''")
                    ->whereNotNull('checksum_type')
                    ->whereRaw("TRIM(checksum_type) <> ''")
                    ->count());
            }
        }

        // Dedicated preservation checksum store adds strength (algorithm + status).
        $presChecksums = 0;
        if (Schema::hasTable('preservation_checksum')) {
            $presChecksums = $this->guardCount(fn () => DB::table('preservation_checksum')->count());
        }
        $fixityRecorded = max($withChecksum, $presChecksums);

        // Fixity actually verified on a cadence?
        $fixityChecks = 0;
        $fixityFailuresDetected = false;
        if (Schema::hasTable('preservation_fixity_check')) {
            $fixityChecks = $this->guardCount(fn () => DB::table('preservation_fixity_check')->count());
            if ($fixityChecks > 0 && Schema::hasColumn('preservation_fixity_check', 'status')) {
                $fixityFailuresDetected = $this->guardCount(fn () => DB::table('preservation_fixity_check')
                    ->whereIn('status', ['fail', 'error', 'missing'])
                    ->limit(1)
                    ->count()) > 0;
            }
        }

        // Write protection: legal holds or retention/disposition policy.
        $writeProtection = false;
        if (Schema::hasTable('integrity_legal_hold')) {
            $writeProtection = $this->guardCount(fn () => DB::table('integrity_legal_hold')->limit(1)->count()) > 0;
        }
        if (! $writeProtection && Schema::hasTable('integrity_retention_policy')) {
            $writeProtection = $this->guardCount(fn () => DB::table('integrity_retention_policy')->limit(1)->count()) > 0;
        }

        if ($fixityRecorded <= 0) {
            $level = 0;
            $evidence = 'No checksums are recorded for any digital object.';
            $gap = 'Record a cryptographic checksum (e.g. SHA-256) for every file on ingest so its integrity can be verified later. That establishes Level 1.';
        } elseif ($fixityChecks <= 0) {
            $level = 1;
            $evidence = number_format($fixityRecorded).' digital object(s) carry a recorded checksum'.($withAlgorithm > 0 ? ', with the algorithm named' : '').', but no fixity verification has been run.';
            $gap = 'Verify those checksums on a schedule (preservation_fixity_check) and apply write protection (retention policy or legal hold) to reach Level 2.';
        } elseif (! $writeProtection) {
            $level = 2;
            $evidence = number_format($fixityChecks).' fixity check(s) have been run, but no retention policy or legal hold is in place to protect content from change or deletion.';
            $gap = 'Add a retention policy or legal hold (write protection) and keep verifying fixity on a cadence so corruption is actively detected, lifting this area to Level 3.';
        } elseif (! $fixityFailuresDetected) {
            $level = 3;
            $evidence = number_format($fixityChecks).' fixity check(s) run with write protection in place; no integrity failures have had to be repaired.';
            $gap = 'Demonstrate the ability to repair or replace a corrupted file from a known-good copy (detect-and-repair) to reach Level 4.';
        } else {
            $level = 3;
            $evidence = number_format($fixityChecks).' fixity check(s) run with write protection in place; some checks detected problems.';
            $gap = 'Resolve detected fixity failures by restoring from a known-good copy and record the repair, evidencing Level 4 detect-and-repair.';
        }

        return compact('key', 'name', 'subtitle', 'level', 'evidence', 'gap');
    }

    // ---------------------------------------------------------------------
    // Area 3: Control (information security)
    // NDSA: L1 identify who has authority to make changes / who can read;
    // L2 restrict access (access controls); L3 maintain logs of who did what
    // (audit trail); L4 perform audit on logs. Evidence: acl_group /
    // acl_permission and object_security_classification (access controls), and
    // ahg_audit_log / ahg_audit_access / ahg_audit_authentication (audit logging).
    // ---------------------------------------------------------------------
    private function assessControl(int $digitalObjects): array
    {
        $key = 'control';
        $name = 'Information security and access control';
        $subtitle = 'Restricting who can read or change content, and logging who did what';

        $accessControls = false;
        if (Schema::hasTable('acl_group') && Schema::hasTable('acl_permission')) {
            $accessControls = $this->guardCount(fn () => DB::table('acl_permission')->limit(1)->count()) > 0;
        }
        $classification = false;
        if (Schema::hasTable('object_security_classification')) {
            $classification = $this->guardCount(fn () => DB::table('object_security_classification')->limit(1)->count()) > 0;
        }

        $auditLog = 0;
        if (Schema::hasTable('ahg_audit_log')) {
            $auditLog = $this->guardCount(fn () => DB::table('ahg_audit_log')->count());
        }
        $accessLog = 0;
        if (Schema::hasTable('ahg_audit_access')) {
            $accessLog = $this->guardCount(fn () => DB::table('ahg_audit_access')->count());
        }
        $authLog = 0;
        if (Schema::hasTable('ahg_audit_authentication')) {
            $authLog = $this->guardCount(fn () => DB::table('ahg_audit_authentication')->count());
        }
        $auditTotal = $auditLog + $accessLog + $authLog;

        if (! $accessControls) {
            $level = 0;
            $evidence = 'No permission groups are defined, so there is no record of who has authority to read or change content.';
            $gap = 'Define permission groups and grants (acl_group / acl_permission) so authority over content is explicit. That establishes Level 1.';
        } elseif ($auditTotal <= 0) {
            $level = 1;
            $evidence = 'Permission groups are defined'.($classification ? ' and security classifications are in use' : '').', but no access or change activity is being logged.';
            $gap = 'Enable the audit trail (ahg_audit_log / ahg_audit_access / ahg_audit_authentication) so every read and change is recorded, reaching Level 2 then Level 3.';
        } elseif ($auditLog <= 0 || $accessLog <= 0) {
            $level = 2;
            $evidence = number_format($auditTotal).' audit record(s) captured, but coverage of both change actions and read access is not yet complete.';
            $gap = 'Log both change actions (ahg_audit_log) and read access (ahg_audit_access) comprehensively, then periodically review the logs, to reach Level 3 and Level 4.';
        } else {
            $level = 3;
            $evidence = number_format($auditTotal).' audit record(s) across change, access, and authentication logs, with access controls in place.';
            $gap = 'Perform regular reviews of the audit logs (look for anomalous access) and act on findings to evidence Level 4.';
        }

        return compact('key', 'name', 'subtitle', 'level', 'evidence', 'gap');
    }

    // ---------------------------------------------------------------------
    // Area 4: Metadata
    // NDSA: L1 minimal inventory metadata; L2 store admin, transformative and
    // preservation metadata; L3 store standard technical + descriptive metadata;
    // L4 store standard preservation (PREMIS) metadata. Evidence: descriptive
    // (information_object_i18n), administrative/provenance (event), technical
    // (digital_object_metadata), preservation/PREMIS (preservation_event).
    // ---------------------------------------------------------------------
    private function assessMetadata(int $digitalObjects): array
    {
        $key = 'metadata';
        $name = 'Metadata';
        $subtitle = 'Descriptive, administrative, technical and preservation (PREMIS) metadata';

        $descriptive = false;
        if (Schema::hasTable('information_object_i18n')) {
            $descriptive = $this->guardCount(fn () => DB::table('information_object_i18n')
                ->whereNotNull('title')
                ->whereRaw("TRIM(title) <> ''")
                ->limit(1)
                ->count()) > 0;
        }
        $administrative = false;
        if (Schema::hasTable('event')) {
            $administrative = $this->guardCount(fn () => DB::table('event')->limit(1)->count()) > 0;
        }
        $technical = 0;
        if (Schema::hasTable('digital_object_metadata')) {
            $technical = $this->guardCount(fn () => DB::table('digital_object_metadata')->count());
        }
        $premis = 0;
        if (Schema::hasTable('preservation_event')) {
            $premis = $this->guardCount(fn () => DB::table('preservation_event')->count());
        }

        if (! $descriptive) {
            $level = 0;
            $evidence = 'No descriptive metadata (record titles) is present yet.';
            $gap = 'Catalogue records with at least a title and basic inventory metadata to establish Level 1.';
        } elseif (! $administrative) {
            $level = 1;
            $evidence = 'Descriptive metadata is present, but no administrative or provenance events are recorded.';
            $gap = 'Record administrative and provenance metadata (events such as creation and acquisition) to reach Level 2.';
        } elseif ($technical <= 0) {
            $level = 2;
            $evidence = 'Descriptive and administrative metadata are present, but no standard technical metadata has been captured for digital files.';
            $gap = 'Extract and store technical metadata for digital objects (digital_object_metadata: format, dimensions, duration, etc.) to reach Level 3.';
        } elseif ($premis <= 0) {
            $level = 3;
            $evidence = 'Descriptive, administrative and technical metadata are present ('.number_format($technical).' technical record(s)), but no preservation (PREMIS) events are logged.';
            $gap = 'Record standard preservation events in PREMIS form (preservation_event) - ingest, fixity, format identification, migration - to reach Level 4.';
        } else {
            $level = 4;
            $evidence = 'Descriptive, administrative, technical, and PREMIS preservation metadata are all present ('.number_format($premis).' preservation event(s)).';
            $gap = 'Keep preservation events current as content is ingested and acted upon.';
        }

        return compact('key', 'name', 'subtitle', 'level', 'evidence', 'gap');
    }

    // ---------------------------------------------------------------------
    // Area 5: Content (file formats)
    // NDSA: L1 know what file formats you hold (identification); L2 identify
    // formats to a standard (PRONOM/PUID) and limit formats; L3 monitor format
    // obsolescence (risk registry); L4 act to keep content renderable
    // (migration). Evidence: digital_object.mime_type (basic identification),
    // preservation_object_format.puid (PRONOM identification), distinct mime
    // types (format diversity), preservation_format risk registry (monitoring).
    // ---------------------------------------------------------------------
    private function assessContent(int $digitalObjects): array
    {
        $key = 'content';
        $name = 'Content and file formats';
        $subtitle = 'Format identification (PRONOM/PUID), diversity, and obsolescence monitoring';

        $withMime = 0;
        $distinctMime = 0;
        if (Schema::hasTable('digital_object') && Schema::hasColumn('digital_object', 'mime_type')) {
            $withMime = $this->guardCount(fn () => DB::table('digital_object')
                ->whereNotNull('mime_type')
                ->whereRaw("TRIM(mime_type) <> ''")
                ->count());
            $distinctMime = $this->guardCount(fn () => DB::table('digital_object')
                ->whereNotNull('mime_type')
                ->whereRaw("TRIM(mime_type) <> ''")
                ->distinct()
                ->count('mime_type'));
        }

        $puidIdentified = 0;
        if (Schema::hasTable('preservation_object_format') && Schema::hasColumn('preservation_object_format', 'puid')) {
            $puidIdentified = $this->guardCount(fn () => DB::table('preservation_object_format')
                ->whereNotNull('puid')
                ->whereRaw("TRIM(puid) <> ''")
                ->count());
        }

        // Obsolescence monitoring: a populated format risk registry.
        $riskRegistry = 0;
        $monitoring = false;
        if (Schema::hasTable('preservation_format')) {
            $riskRegistry = $this->guardCount(fn () => DB::table('preservation_format')->count());
            if ($riskRegistry > 0 && Schema::hasColumn('preservation_format', 'preservation_action')) {
                $monitoring = $this->guardCount(fn () => DB::table('preservation_format')
                    ->whereIn('preservation_action', ['monitor', 'migrate', 'normalize'])
                    ->limit(1)
                    ->count()) > 0;
            }
        }

        if ($withMime <= 0 && $puidIdentified <= 0) {
            $level = 0;
            $evidence = 'No file-format information is recorded for any digital object.';
            $gap = 'Record at least the MIME type of every file so you know what formats you hold. That establishes Level 1.';
        } elseif ($puidIdentified <= 0) {
            $level = 1;
            $evidence = number_format($withMime).' digital object(s) carry a MIME type across '.number_format($distinctMime).' distinct format(s), but no PRONOM (PUID) identification has been performed.';
            $gap = 'Identify formats to the PRONOM standard with a tool such as Siegfried or DROID (preservation_object_format.puid) to reach Level 2.';
        } elseif ($riskRegistry <= 0 || ! $monitoring) {
            $level = 2;
            $evidence = number_format($puidIdentified).' digital object(s) identified to a PRONOM PUID, but no format risk / obsolescence registry is being monitored.';
            $gap = 'Maintain a format risk registry (preservation_format) with preservation actions so format obsolescence is actively monitored, reaching Level 3.';
        } else {
            $level = 3;
            $evidence = number_format($puidIdentified).' object(s) identified to PRONOM, with a monitored format risk registry of '.number_format($riskRegistry).' format(s).';
            $gap = 'Act on at-risk formats by migrating or normalising them and recording the migration, to evidence Level 4.';
        }

        return compact('key', 'name', 'subtitle', 'level', 'evidence', 'gap');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** Total digital objects (any usage) - context figure for the dashboard. */
    private function countDigitalObjects(): int
    {
        if (! Schema::hasTable('digital_object')) {
            return 0;
        }

        return $this->guardCount(fn () => DB::table('digital_object')->count());
    }

    /**
     * Run a cheap aggregate closure, returning 0 on any failure. Keeps every
     * probe non-throwing so the assessment degrades to a lower (honest) level
     * rather than 500-ing.
     */
    private function guardCount(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation-maturity probe failed: '.$e->getMessage());

            return 0;
        }
    }

    /** An honest "Not yet" area used when a whole area probe throws. */
    private function emptyArea(string $key): array
    {
        $names = [
            'storage'   => ['Storage and geographic location', 'Multiple copies, with geographic and provider or system diversity'],
            'integrity' => ['Integrity (fixity and write protection)', 'Checksums recorded, fixity verified on a cadence, and content protected from change'],
            'control'   => ['Information security and access control', 'Restricting who can read or change content, and logging who did what'],
            'metadata'  => ['Metadata', 'Descriptive, administrative, technical and preservation (PREMIS) metadata'],
            'content'   => ['Content and file formats', 'Format identification (PRONOM/PUID), diversity, and obsolescence monitoring'],
        ];
        [$name, $subtitle] = $names[$key] ?? [$key, ''];

        return [
            'key'      => $key,
            'name'     => $name,
            'subtitle' => $subtitle,
            'level'    => 0,
            'evidence' => 'This area could not be assessed from the catalogue right now.',
            'gap'      => 'Try again later. If this persists, check that the preservation tables are installed.',
        ];
    }
}
