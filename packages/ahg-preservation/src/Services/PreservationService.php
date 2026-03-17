<?php

namespace AhgPreservation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PreservationService
{
    /**
     * Dashboard statistics: total digital objects, objects with checksums,
     * fixity checks run, fixity failures, at-risk formats, virus scans, clean count.
     */
    public function getStatistics(): object
    {
        $totalObjects = DB::table('digital_object')->count();

        $objectsWithChecksums = DB::table('preservation_checksum')
            ->distinct('digital_object_id')
            ->count('digital_object_id');

        $fixityChecksRun = DB::table('preservation_fixity_check')->count();

        $fixityFailures = DB::table('preservation_fixity_check')
            ->where('status', 'fail')
            ->count();

        $atRiskFormats = DB::table('preservation_format')
            ->whereIn('risk_level', ['high', 'critical'])
            ->count();

        $virusScans = DB::table('preservation_virus_scan')->count();

        $cleanScans = DB::table('preservation_virus_scan')
            ->where('status', 'clean')
            ->count();

        return (object) [
            'total_objects'          => $totalObjects,
            'objects_with_checksums' => $objectsWithChecksums,
            'fixity_checks_run'     => $fixityChecksRun,
            'fixity_failures'       => $fixityFailures,
            'at_risk_formats'       => $atRiskFormats,
            'virus_scans'           => $virusScans,
            'clean_scans'           => $cleanScans,
        ];
    }

    /**
     * All checksums for a given digital object.
     */
    public function getChecksums(int $digitalObjectId): \Illuminate\Support\Collection
    {
        return DB::table('preservation_checksum')
            ->where('digital_object_id', $digitalObjectId)
            ->orderByDesc('generated_at')
            ->get();
    }

    /**
     * Calculate checksum of file on disk, store in preservation_checksum, log PREMIS event.
     */
    public function generateChecksum(int $digitalObjectId, string $algorithm = 'sha256'): ?object
    {
        $digitalObject = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (!$digitalObject) {
            return null;
        }

        $uploadsBase = '/mnt/nas/heratio/archive';
        $filePath = $uploadsBase . '/' . ltrim($digitalObject->path, '/');

        if (!file_exists($filePath)) {
            return null;
        }

        $hashAlgo = str_replace('-', '', strtolower($algorithm));
        $checksumValue = hash_file($hashAlgo, $filePath);
        $fileSize = filesize($filePath);
        $now = now()->format('Y-m-d H:i:s');

        $id = DB::table('preservation_checksum')->insertGetId([
            'digital_object_id'   => $digitalObjectId,
            'algorithm'           => $algorithm,
            'checksum_value'      => $checksumValue,
            'file_size'           => $fileSize,
            'generated_at'        => $now,
            'verification_status' => 'pending',
            'created_at'          => $now,
        ]);

        $this->logEvent(
            $digitalObjectId,
            $digitalObject->object_id,
            'message_digest_calculation',
            "Generated {$algorithm} checksum: {$checksumValue}",
            'success'
        );

        return DB::table('preservation_checksum')->where('id', $id)->first();
    }

    /**
     * Compare stored checksum with current file hash, log result in preservation_fixity_check, log PREMIS event.
     */
    public function verifyFixity(int $digitalObjectId): ?object
    {
        $digitalObject = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (!$digitalObject) {
            return null;
        }

        $checksum = DB::table('preservation_checksum')
            ->where('digital_object_id', $digitalObjectId)
            ->orderByDesc('generated_at')
            ->first();

        if (!$checksum) {
            return null;
        }

        $uploadsBase = '/mnt/nas/heratio/archive';
        $filePath = $uploadsBase . '/' . ltrim($digitalObject->path, '/');

        $now = now()->format('Y-m-d H:i:s');
        $startTime = microtime(true);

        $status = 'fail';
        $actualValue = null;
        $errorMessage = null;

        if (!file_exists($filePath)) {
            $errorMessage = 'File not found: ' . $filePath;
        } else {
            $hashAlgo = str_replace('-', '', strtolower($checksum->algorithm));
            $actualValue = hash_file($hashAlgo, $filePath);
            if ($actualValue === $checksum->checksum_value) {
                $status = 'pass';
            }
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $id = DB::table('preservation_fixity_check')->insertGetId([
            'digital_object_id' => $digitalObjectId,
            'checksum_id'       => $checksum->id,
            'algorithm'         => $checksum->algorithm,
            'expected_value'    => $checksum->checksum_value,
            'actual_value'      => $actualValue,
            'status'            => $status,
            'error_message'     => $errorMessage,
            'checked_at'        => $now,
            'checked_by'        => 'system',
            'duration_ms'       => $durationMs,
            'created_at'        => $now,
        ]);

        // Update checksum verification status
        DB::table('preservation_checksum')
            ->where('id', $checksum->id)
            ->update([
                'verified_at'         => $now,
                'verification_status' => $status === 'pass' ? 'verified' : 'failed',
            ]);

        $this->logEvent(
            $digitalObjectId,
            $digitalObject->object_id,
            'fixity_check',
            "Fixity check ({$checksum->algorithm}): expected={$checksum->checksum_value}, actual={$actualValue}",
            $status === 'pass' ? 'success' : 'failure'
        );

        return DB::table('preservation_fixity_check')->where('id', $id)->first();
    }

    /**
     * Recent fixity checks with digital object info.
     */
    public function getFixityLog(int $limit = 50, ?string $status = null): \Illuminate\Support\Collection
    {
        $query = DB::table('preservation_fixity_check as fc')
            ->leftJoin('digital_object as do', 'do.id', '=', 'fc.digital_object_id')
            ->select(
                'fc.*',
                'do.name as file_name',
                'do.path as file_path',
                'do.mime_type as file_mime_type'
            );

        if ($status) {
            $query->where('fc.status', $status);
        }

        return $query->orderByDesc('fc.checked_at')->limit($limit)->get();
    }

    /**
     * PREMIS events from preservation_event.
     */
    public function getEvents(int $limit = 50, ?int $digitalObjectId = null): \Illuminate\Support\Collection
    {
        $query = DB::table('preservation_event as pe')
            ->leftJoin('digital_object as do', 'do.id', '=', 'pe.digital_object_id')
            ->select(
                'pe.*',
                'do.name as file_name',
                'do.path as file_path'
            );

        if ($digitalObjectId) {
            $query->where('pe.digital_object_id', $digitalObjectId);
        }

        return $query->orderByDesc('pe.event_datetime')->limit($limit)->get();
    }

    /**
     * Insert a PREMIS preservation_event.
     */
    public function logEvent(int $digitalObjectId, ?int $ioId, string $type, string $detail, string $outcome): int
    {
        return DB::table('preservation_event')->insertGetId([
            'digital_object_id'    => $digitalObjectId,
            'information_object_id'=> $ioId,
            'event_type'           => $type,
            'event_datetime'       => now()->format('Y-m-d H:i:s'),
            'event_detail'         => $detail,
            'event_outcome'        => $outcome,
            'linking_agent_type'   => 'system',
            'linking_agent_value'  => 'heratio-preservation',
            'created_at'           => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * All formats from preservation_format with object counts.
     */
    public function getFormats(): \Illuminate\Support\Collection
    {
        return DB::table('preservation_format as pf')
            ->leftJoin('preservation_object_format as pof', 'pof.format_id', '=', 'pf.id')
            ->select(
                'pf.*',
                DB::raw('COUNT(pof.id) as object_count')
            )
            ->groupBy('pf.id')
            ->orderBy('pf.format_name')
            ->get();
    }

    /**
     * Format info for a specific digital object from preservation_object_format.
     */
    public function getObjectFormat(int $digitalObjectId): ?object
    {
        return DB::table('preservation_object_format as pof')
            ->leftJoin('preservation_format as pf', 'pf.id', '=', 'pof.format_id')
            ->select(
                'pof.*',
                'pf.format_name as registry_format_name',
                'pf.risk_level',
                'pf.preservation_action',
                'pf.is_preservation_format'
            )
            ->where('pof.digital_object_id', $digitalObjectId)
            ->first();
    }

    /**
     * Recent virus scans from preservation_virus_scan.
     */
    public function getVirusScans(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('preservation_virus_scan as vs')
            ->leftJoin('digital_object as do', 'do.id', '=', 'vs.digital_object_id')
            ->select(
                'vs.*',
                'do.name as file_name',
                'do.path as file_path'
            )
            ->orderByDesc('vs.scanned_at')
            ->limit($limit)
            ->get();
    }

    /**
     * OAIS packages from preservation_package.
     */
    public function getPackages(int $limit = 50, ?string $type = null): \Illuminate\Support\Collection
    {
        $query = DB::table('preservation_package');

        if ($type) {
            $query->where('package_type', $type);
        }

        return $query->orderByDesc('created_at')->limit($limit)->get();
    }

    /**
     * Single package with its objects.
     */
    public function getPackage(int $id): ?object
    {
        $package = DB::table('preservation_package')->where('id', $id)->first();
        if (!$package) {
            return null;
        }

        $package->objects = DB::table('preservation_package_object as ppo')
            ->leftJoin('digital_object as do', 'do.id', '=', 'ppo.digital_object_id')
            ->select('ppo.*', 'do.name as digital_object_name', 'do.path as digital_object_path')
            ->where('ppo.package_id', $id)
            ->orderBy('ppo.sequence')
            ->get();

        $package->events = DB::table('preservation_package_event')
            ->where('package_id', $id)
            ->orderByDesc('event_datetime')
            ->get();

        return $package;
    }

    /**
     * Insert a new preservation_package with UUID.
     */
    public function createPackage(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? (string) Str::uuid();
        $data['created_at'] = $data['created_at'] ?? now()->format('Y-m-d H:i:s');

        return DB::table('preservation_package')->insertGetId($data);
    }

    /**
     * All preservation_policy records.
     */
    public function getPolicies(): \Illuminate\Support\Collection
    {
        return DB::table('preservation_policy')
            ->orderBy('name')
            ->get();
    }

    /**
     * All preservation_workflow_schedule records.
     */
    public function getSchedules(): \Illuminate\Support\Collection
    {
        return DB::table('preservation_workflow_schedule')
            ->orderBy('name')
            ->get();
    }

    /**
     * Recent runs for a given schedule.
     */
    public function getScheduleRuns(int $scheduleId, int $limit = 20): \Illuminate\Support\Collection
    {
        return DB::table('preservation_workflow_run')
            ->where('schedule_id', $scheduleId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * All preservation_replication_target records.
     */
    public function getReplicationTargets(): \Illuminate\Support\Collection
    {
        return DB::table('preservation_replication_target')
            ->orderBy('name')
            ->get();
    }

    /**
     * preservation_stats for last N days.
     */
    public function getDailyStats(int $days = 30): \Illuminate\Support\Collection
    {
        return DB::table('preservation_stats')
            ->where('stat_date', '>=', now()->subDays($days)->format('Y-m-d'))
            ->orderBy('stat_date')
            ->get();
    }

    /**
     * At-risk formats with object counts (for dashboard).
     */
    public function getAtRiskFormats(): \Illuminate\Support\Collection
    {
        return DB::table('preservation_format as pf')
            ->leftJoin('preservation_object_format as pof', 'pof.format_id', '=', 'pf.id')
            ->select(
                'pf.*',
                DB::raw('COUNT(pof.id) as object_count')
            )
            ->whereIn('pf.risk_level', ['high', 'critical'])
            ->groupBy('pf.id')
            ->orderByRaw("FIELD(pf.risk_level, 'critical', 'high')")
            ->get();
    }

    /**
     * Objects without checksums (for reports).
     */
    public function getObjectsWithoutChecksums(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('digital_object as do')
            ->leftJoin('preservation_checksum as pc', 'pc.digital_object_id', '=', 'do.id')
            ->whereNull('pc.id')
            ->select('do.*')
            ->limit($limit)
            ->get();
    }

    /**
     * Objects with stale fixity (last check > 90 days ago or never checked).
     */
    public function getStaleFixityObjects(int $days = 90, int $limit = 50): \Illuminate\Support\Collection
    {
        $cutoff = now()->subDays($days)->format('Y-m-d H:i:s');

        return DB::table('preservation_checksum as pc')
            ->leftJoin('digital_object as do', 'do.id', '=', 'pc.digital_object_id')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('pc.verified_at')
                  ->orWhere('pc.verified_at', '<', $cutoff);
            })
            ->select('pc.*', 'do.name as file_name', 'do.path as file_path')
            ->orderBy('pc.verified_at')
            ->limit($limit)
            ->get();
    }

    /**
     * High-risk format objects (for reports).
     */
    public function getHighRiskFormatObjects(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('preservation_object_format as pof')
            ->join('preservation_format as pf', 'pf.id', '=', 'pof.format_id')
            ->leftJoin('digital_object as do', 'do.id', '=', 'pof.digital_object_id')
            ->whereIn('pf.risk_level', ['high', 'critical'])
            ->select(
                'pof.*',
                'pf.format_name as registry_format_name',
                'pf.risk_level',
                'pf.preservation_action',
                'do.name as file_name',
                'do.path as file_path'
            )
            ->orderByRaw("FIELD(pf.risk_level, 'critical', 'high')")
            ->limit($limit)
            ->get();
    }

    /**
     * Recent backup verifications.
     */
    public function getBackupVerifications(int $limit = 20): \Illuminate\Support\Collection
    {
        return DB::table('preservation_backup_verification')
            ->orderByDesc('verified_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Recent replication logs.
     */
    public function getReplicationLogs(int $limit = 20): \Illuminate\Support\Collection
    {
        return DB::table('preservation_replication_log as rl')
            ->leftJoin('preservation_replication_target as rt', 'rt.id', '=', 'rl.target_id')
            ->select('rl.*', 'rt.name as target_name')
            ->orderByDesc('rl.started_at')
            ->limit($limit)
            ->get();
    }
}
