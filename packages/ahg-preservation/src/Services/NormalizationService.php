<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0
 */

declare(strict_types=1);

namespace AhgPreservation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use AhgCore\Services\DigitalObjectService;

/**
 * #1385 Phase 1 - Archivematica-style normalization.
 *
 * Produces a preservation master (open, long-lived format) for a digital
 * object, driven by the preservation_normalization_rule registry (the FPR).
 * The output is:
 *   - stored next to the source file,
 *   - recorded in preservation_format_conversion,
 *   - attached as a linked child digital_object (usage = Preservation Master),
 *   - fixity-checksummed, and
 *   - logged as a PREMIS `normalization` event.
 *
 * Idempotent (skips a DO that already has a completed master) and fail-soft.
 */
class NormalizationService
{
    /** Shell-safe option allowlists (mirrors the AtoM executor). */
    private const VALID_PRESETS = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'];
    private const VALID_PDF_SETTINGS = ['/default', '/screen', '/ebook', '/printer', '/prepress'];
    private const VALID_COMPRESS = ['lzw', 'zip', 'jpeg', 'none', 'rle', 'group4', 'fax'];

    /**
     * Normalize one digital object. Returns the conversion result array, or
     * null when there is nothing to do (no rule / already normalized / missing
     * file / tool unavailable).
     *
     * @return array{conversion_id:int,derivative_do_id:?int,target_format:string}|null
     */
    public function normalizeDigitalObject(int $digitalObjectId, string $purpose = 'preservation'): ?array
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (! $do) {
            return null;
        }

        $mime = (string) ($do->mime_type ?? '');
        if ($mime === '') {
            return null;
        }

        // Find the matching active rule (PRONOM wins over MIME when present).
        $rule = $this->matchRule($digitalObjectId, $mime, $purpose);
        if (! $rule) {
            Log::info("[normalization] no {$purpose} rule for DO {$digitalObjectId} ({$mime})");
            return null;
        }

        // Target usage depends on purpose: preservation master vs access copy
        // (reference). Resolved up front so idempotency keys on the derivative.
        $usageId = $purpose === 'access'
            ? 141 // QubitTerm reference (the dissemination/access copy)
            : $this->preservationMasterUsageId();

        // Idempotency: skip if this DO already carries a child derivative of the
        // target usage (a preservation master / access copy already exists).
        if ($usageId) {
            $hasChild = DB::table('digital_object')
                ->where('parent_id', $digitalObjectId)
                ->where('usage_id', $usageId)
                ->exists();
            if ($hasChild) {
                return null;
            }
        }

        $sourcePath = DigitalObjectService::resolveDiskPath($do);
        if (! $sourcePath || ! is_file($sourcePath)) {
            Log::warning("[normalization] source file missing for DO {$digitalObjectId}");
            return null;
        }

        if (! $this->toolAvailable($rule->tool)) {
            Log::warning("[normalization] tool '{$rule->tool}' unavailable - skipping DO {$digitalObjectId}");
            return null;
        }

        $now = now()->format('Y-m-d H:i:s');
        $sourceSize = filesize($sourcePath) ?: null;
        $sourceChecksum = hash_file('sha256', $sourcePath);
        $outputPath = $this->buildOutputPath($sourcePath, (string) $rule->target_ext);

        $conversionId = DB::table('preservation_format_conversion')->insertGetId([
            'digital_object_id' => $digitalObjectId,
            'source_format' => pathinfo($sourcePath, PATHINFO_EXTENSION),
            'source_mime_type' => $mime,
            'target_format' => $rule->target_format,
            'target_mime_type' => $rule->target_mime,
            'conversion_tool' => $rule->tool,
            'status' => 'processing',
            'source_path' => $sourcePath,
            'source_size' => $sourceSize,
            'source_checksum' => $sourceChecksum,
            'output_path' => $outputPath,
            'conversion_options' => $rule->options,
            'started_at' => $now,
            'created_by' => 'ingest-normalization:' . $purpose,
            'created_at' => $now,
        ]);

        $start = microtime(true);
        $options = is_string($rule->options ?? null) ? (json_decode($rule->options, true) ?: []) : [];

        try {
            $result = $this->executeConversion($rule->tool, $sourcePath, $outputPath, (string) $rule->target_ext, $options);

            // LibreOffice names output by the source basename; reconcile.
            if ($rule->tool === 'libreoffice' && ! is_file($outputPath)) {
                $lo = dirname($outputPath) . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.' . $rule->target_ext;
                if (is_file($lo)) {
                    @rename($lo, $outputPath);
                }
            }

            if (! ($result['success'] ?? false) || ! is_file($outputPath)) {
                DB::table('preservation_format_conversion')->where('id', $conversionId)->update([
                    'status' => 'failed',
                    'error_message' => substr((string) ($result['error'] ?? 'conversion produced no output'), 0, 2000),
                    'completed_at' => now()->format('Y-m-d H:i:s'),
                    'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                ]);
                return null;
            }

            $outSize = filesize($outputPath) ?: null;
            $outChecksum = hash_file('sha256', $outputPath);

            DB::table('preservation_format_conversion')->where('id', $conversionId)->update([
                'status' => 'completed',
                'output_size' => $outSize,
                'output_checksum' => $outChecksum,
                'completed_at' => now()->format('Y-m-d H:i:s'),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            // Attach the output as a linked child digital_object (preservation
            // master or access copy, per purpose).
            $derivativeId = $this->attachDerivative($do, $outputPath, (string) $rule->target_mime, $outSize, $outChecksum, $usageId);

            // PREMIS normalization event.
            try {
                app(PreservationService::class)->logEvent(
                    $derivativeId ?: $digitalObjectId,
                    isset($do->object_id) ? (int) $do->object_id : null,
                    'normalization',
                    ucfirst($purpose) . " copy created: normalized to {$rule->target_format} via {$rule->tool} (source DO {$digitalObjectId})",
                    'success'
                );
            } catch (\Throwable $e) {
                Log::warning('[normalization] PREMIS event failed: ' . $e->getMessage());
            }

            return [
                'conversion_id' => $conversionId,
                'derivative_do_id' => $derivativeId,
                'target_format' => $rule->target_format,
            ];
        } catch (\Throwable $e) {
            DB::table('preservation_format_conversion')->where('id', $conversionId)->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 2000),
                'completed_at' => now()->format('Y-m-d H:i:s'),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
            Log::warning("[normalization] DO {$digitalObjectId} failed: " . $e->getMessage());
            return null;
        }
    }

    /** Match the highest-priority active rule for a format. */
    private function matchRule(int $doId, string $mime, string $purpose): ?object
    {
        $pronom = DB::table('preservation_object_format')
            ->where('digital_object_id', $doId)
            ->value('puid');

        $q = DB::table('preservation_normalization_rule')
            ->where('purpose', $purpose)
            ->where('is_active', 1);

        if ($pronom) {
            $byPronom = (clone $q)->where('source_pronom', $pronom)->orderBy('priority')->first();
            if ($byPronom) {
                return $byPronom;
            }
        }

        return $q->where('source_mime', $mime)->orderBy('priority')->first();
    }

    /** Output path: alongside the source, with a .preservation.<ext> suffix. */
    private function buildOutputPath(string $sourcePath, string $targetExt): string
    {
        $dir = dirname($sourcePath);
        $base = pathinfo($sourcePath, PATHINFO_FILENAME);
        return $dir . '/' . $base . '.preservation.' . $targetExt;
    }

    /**
     * Insert the normalized output as a child digital_object (usage =
     * Preservation Master), mirroring MediaDerivativeService::insertDerivative.
     */
    private function attachDerivative(object $sourceDo, string $diskPath, string $mime, ?int $byteSize, string $checksum, ?int $usageId): ?int
    {
        if (! $usageId) {
            return null; // usage missing - record stays in the conversion log only
        }

        $ioId = isset($sourceDo->object_id) ? (int) $sourceDo->object_id : null;
        if (! $ioId) {
            return null;
        }

        $now = now()->format('Y-m-d H:i:s');
        $newOid = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('digital_object')->insert([
            'id' => $newOid,
            'object_id' => $ioId,
            'usage_id' => $usageId,
            'mime_type' => $mime,
            'media_type_id' => $sourceDo->media_type_id ?? null,
            'name' => basename($diskPath),
            'path' => $sourceDo->path ?: ('/uploads/r/' . $ioId . '/'),
            'byte_size' => $byteSize,
            'checksum' => $checksum,
            'checksum_type' => 'sha256',
            'parent_id' => (int) $sourceDo->id,
        ]);

        return $newOid;
    }

    /** Resolve (cached) the "Preservation Master" usage term id (taxonomy 47). */
    private function preservationMasterUsageId(): ?int
    {
        static $id = null;
        if ($id !== null) {
            return $id ?: null;
        }
        $id = (int) (DB::table('term_i18n')
            ->join('term', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 47)
            ->where('term_i18n.name', 'Preservation Master')
            ->value('term.id') ?? 0);

        return $id ?: null;
    }

    /** Tools the executor knows how to drive (matches preservation_normalization_rule.tool). */
    public const SUPPORTED_TOOLS = ['imagemagick', 'ffmpeg', 'ghostscript', 'libreoffice'];

    /** Map a rule tool id to its host binary, or null if unknown. */
    public function toolBinary(string $tool): ?string
    {
        return match ($tool) {
            'imagemagick' => 'convert',
            'ffmpeg' => 'ffmpeg',
            'ghostscript' => 'gs',
            'libreoffice' => 'libreoffice',
            default => null,
        };
    }

    private function toolAvailable(string $tool): bool
    {
        $bin = $this->toolBinary($tool);
        if (! $bin) {
            return false;
        }
        $which = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');

        return ! empty(trim((string) $which));
    }

    /**
     * #1385 Phase 4 - preflight health for every executor tool: is its binary
     * on PATH on this host? Drives the FPR admin health surface so operators
     * can see which rules can actually run before enabling normalization
     * (rules whose tool is absent are silently skipped at runtime).
     *
     * @return array<string,array{bin:?string,available:bool}>
     */
    public function toolHealth(): array
    {
        $health = [];
        foreach (self::SUPPORTED_TOOLS as $tool) {
            $health[$tool] = [
                'bin' => $this->toolBinary($tool),
                'available' => $this->toolAvailable($tool),
            ];
        }

        return $health;
    }

    /**
     * Per-tool default wall-clock timeout (seconds). Video is the long pole;
     * images are quick. Overridable per-host via
     * config('ahg-preservation.normalize_timeout.<tool>') and per-rule via the
     * options 'timeout' key. #1385 Phase 4.
     */
    private const TOOL_TIMEOUTS = [
        'imagemagick' => 120,
        'ghostscript' => 300,
        'libreoffice' => 300,
        'ffmpeg'      => 3600,
    ];

    /**
     * Run the conversion. Shell-safe argument handling + option allowlists,
     * bounded by a per-tool timeout and run through Laravel's Process facade
     * so a hung tool can't wedge the queue worker (#1385 Phase 4). LibreOffice
     * gets a throwaway per-invocation user profile so concurrent jobs don't
     * collide on the shared ~/.config profile lock.
     *
     * @return array{success:bool,output?:string,error:?string}
     */
    private function executeConversion(string $tool, string $input, string $output, string $targetExt, array $options): array
    {
        $in = escapeshellarg($input);
        $out = escapeshellarg($output);
        $cleanup = [];

        switch ($tool) {
            case 'imagemagick':
                if (in_array($targetExt, ['jpg', 'jpeg'], true)) {
                    // Access copy: flatten + quality, no TIFF compression flag.
                    $quality = (int) ($options['quality'] ?? 85);
                    $cmd = "convert {$in}[0] -quality {$quality} -flatten {$out}";
                } else {
                    $quality = (int) ($options['quality'] ?? 95);
                    $compress = in_array($options['compress'] ?? 'lzw', self::VALID_COMPRESS, true) ? ($options['compress'] ?? 'lzw') : 'lzw';
                    $cmd = "convert {$in} -quality {$quality} -compress {$compress} {$out}";
                }
                break;

            case 'ffmpeg':
                if (in_array($targetExt, ['wav', 'flac', 'mp3', 'ogg', 'm4a', 'aac'], true)) {
                    // Audio target - let ffmpeg pick the codec from the extension.
                    $cmd = "ffmpeg -nostdin -i {$in} -y {$out}";
                } elseif ($targetExt === 'mkv') {
                    // FFV1 + FLAC in Matroska - a common preservation video target.
                    $cmd = "ffmpeg -nostdin -i {$in} -c:v ffv1 -level 3 -c:a flac -y {$out}";
                } else {
                    $preset = in_array($options['preset'] ?? 'medium', self::VALID_PRESETS, true) ? ($options['preset'] ?? 'medium') : 'medium';
                    $crf = (int) ($options['crf'] ?? 23);
                    $cmd = "ffmpeg -nostdin -i {$in} -preset {$preset} -crf {$crf} -y {$out}";
                }
                break;

            case 'ghostscript':
                $pdf = in_array($options['pdf_settings'] ?? '/printer', self::VALID_PDF_SETTINGS, true) ? ($options['pdf_settings'] ?? '/printer') : '/printer';
                // PDF/A-2b output for preservation.
                $cmd = "gs -dPDFA=2 -dBATCH -dNOPAUSE -dQUIET -sColorConversionStrategy=UseDeviceIndependentColor -sDEVICE=pdfwrite -dPDFACompatibilityPolicy=1 -dPDFSETTINGS={$pdf} -sOutputFile={$out} {$in}";
                break;

            case 'libreoffice':
                $outDir = escapeshellarg(dirname($output));
                $safe = preg_replace('/[^a-zA-Z0-9]/', '', $targetExt);
                // Throwaway per-invocation user profile - LibreOffice serialises
                // on its shared ~/.config/libreoffice profile and a second
                // concurrent headless run silently hangs/fails without this.
                $profileDir = rtrim(sys_get_temp_dir(), '/') . '/ahg-lo-' . bin2hex(random_bytes(6));
                @mkdir($profileDir, 0700, true);
                $cleanup[] = $profileDir;
                $profileArg = escapeshellarg('-env:UserInstallation=file://' . $profileDir);
                $cmd = "libreoffice --headless {$profileArg} --convert-to {$safe} --outdir {$outDir} {$in}";
                break;

            default:
                return ['success' => false, 'error' => "Unknown tool: {$tool}"];
        }

        $timeout = $this->timeoutFor($tool, $options);

        try {
            $result = Process::timeout($timeout)->run($cmd);
            $combined = trim($result->output() . "\n" . $result->errorOutput());
            $success = $result->successful();
            $error = $success ? null : ($combined !== '' ? $combined : ('exit code ' . $result->exitCode()));
        } catch (ProcessTimedOutException $e) {
            $combined = '';
            $success = false;
            $error = "tool '{$tool}' timed out after {$timeout}s";
        } finally {
            foreach ($cleanup as $path) {
                $this->removeDir($path);
            }
        }

        // Output-size ceiling guard - drop a runaway derivative rather than
        // let a bad rule balloon storage. Off unless the rule sets max_output_mb.
        if ($success && is_file($output)) {
            $maxMb = (int) ($options['max_output_mb'] ?? 0);
            if ($maxMb > 0 && (filesize($output) ?: 0) > $maxMb * 1024 * 1024) {
                @unlink($output);
                return ['success' => false, 'error' => "output exceeded {$maxMb}MB ceiling"];
            }
        }

        return ['success' => $success, 'output' => $combined, 'error' => $error];
    }

    /**
     * Resolve the wall-clock timeout for a tool: per-rule option override (if
     * positive) else per-host config else the built-in default, clamped to a
     * sane 10s..2h band. #1385 Phase 4.
     */
    private function timeoutFor(string $tool, array $options): int
    {
        $default = (int) config('ahg-preservation.normalize_timeout.' . $tool, self::TOOL_TIMEOUTS[$tool] ?? 300);
        $override = (int) ($options['timeout'] ?? 0);
        $timeout = $override > 0 ? $override : $default;

        return max(10, min($timeout, 7200));
    }

    /** Recursively remove a sandbox temp directory (best-effort). */
    private function removeDir(string $dir): void
    {
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
