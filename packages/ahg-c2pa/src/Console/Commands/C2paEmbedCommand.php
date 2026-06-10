<?php
/**
 * Heratio - native C2PA embed backfill (issue #1201).
 *
 * For provenance records that already carry a signed Ed25519 manifest, embed
 * that manifest into the on-disk master media file (JUMBF) using the native
 * c2patool binary. The signed sidecar + DB record remain authoritative; this
 * command additionally writes the credentials *into the bytes* so they travel
 * with the file when it leaves Heratio.
 *
 * Dry-run by default; pass --commit to actually write embedded copies. The
 * original master is never modified in place - c2patool writes a sibling
 * `<master>.c2pa.<ext>` copy, exactly like the live record path does.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Console\Commands;

use AhgC2pa\Services\C2paService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Backfill native C2PA embeds for already-signed provenance records.
 *
 * Mirrors the dry-run/commit + per-row loop style of ahg:optimize-models. A
 * record is eligible when it has a bound manifest (sign_status='signed'), a
 * linked digital object whose master resolves on disk, and that master is an
 * embeddable container format (JPEG/PNG/TIFF/MP4 - other formats stay
 * sidecar-only).
 */
class C2paEmbedCommand extends Command
{
    protected $signature = 'ahg:c2pa-embed '
        . '{--id= : Restrict to one information_object id} '
        . '{--commit : Actually write embedded copies (otherwise dry-run)}';

    protected $description = 'Embed signed C2PA manifests into on-disk masters via the native c2patool (JPEG/PNG/TIFF/MP4)';

    public function handle(C2paService $c2pa): int
    {
        if (!$c2pa->canEmbed()) {
            $this->error('c2patool not installed on this host. See docs/c2patool-setup.md.');
            $this->line('Set HERATIO_C2PATOOL_BIN or place the binary at /usr/local/bin/c2patool.');

            return 1;
        }
        $this->line('Using c2patool: ' . ($c2pa->toolBinary() ?? 'unknown'));

        if (!Schema::hasTable('ahg_c2pa_provenance') || !Schema::hasTable('ahg_c2pa_manifest')) {
            $this->error('C2PA tables not installed; nothing to do.');

            return 1;
        }

        $q = DB::table('ahg_c2pa_provenance')
            ->whereNotNull('manifest_id')
            ->where('sign_status', 'signed')
            ->whereNotNull('digital_object_id');
        if ($this->option('id')) {
            $q->where('information_object_id', (int) $this->option('id'));
        }
        $rows = $q->orderBy('id')->get();

        $commit = (bool) $this->option('commit');
        $this->info(($commit ? 'COMMIT' : 'DRY-RUN') . ': ' . $rows->count() . ' signed provenance record(s) with a linked master');
        if ($rows->isEmpty()) {
            return 0;
        }

        $embedded = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $master = $this->resolveMaster((int) $r->digital_object_id, (int) $r->information_object_id);
            if ($master === null) {
                $this->warn("  prov#{$r->id} io#{$r->information_object_id} do#{$r->digital_object_id} - master not found on disk, skip");
                $skipped++;
                continue;
            }
            if (!C2paService::isEmbeddableFormat($master)) {
                $ext = strtolower(pathinfo($master, PATHINFO_EXTENSION));
                $this->line("  prov#{$r->id} {$master} - .{$ext} is sidecar-only (not embeddable), skip");
                $skipped++;
                continue;
            }

            $manifest = $this->loadManifest((int) $r->manifest_id);
            if ($manifest === null) {
                $this->warn("  prov#{$r->id} - bound manifest #{$r->manifest_id} missing/corrupt, skip");
                $skipped++;
                continue;
            }

            if (!$commit) {
                $this->line("  prov#{$r->id} io#{$r->information_object_id} {$master} -> would embed");
                continue;
            }

            try {
                $out = $c2pa->embed($master, $manifest);
            } catch (Throwable $e) {
                $out = null;
                $this->error("  prov#{$r->id} {$master} - embed threw: " . $e->getMessage());
            }
            if ($out === null) {
                $this->error("  prov#{$r->id} {$master} - embed FAILED (see log)");
                $skipped++;
                continue;
            }
            $this->info("  prov#{$r->id} io#{$r->information_object_id} {$master} -> {$out}");
            $embedded++;
        }

        if ($commit) {
            $this->info("Done. Embedded {$embedded}, skipped {$skipped}. Originals untouched; embedded copies are sibling .c2pa.<ext> files.");
        } else {
            $this->info('Dry-run only. Re-run with --commit to write embedded copies.');
        }

        return 0;
    }

    /**
     * Resolve a digital object's master file on disk. Mirrors
     * ProvenanceController::resolveAssetPath() so the command and the live
     * record path agree on where masters live.
     */
    private function resolveMaster(int $digitalObjectId, int $informationObjectId): ?string
    {
        if ($digitalObjectId <= 0 || !Schema::hasTable('digital_object')) {
            return null;
        }
        $do = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->where('object_id', $informationObjectId)
            ->first(['path', 'name']);
        if ($do === null) {
            return null;
        }

        $base = (string) config('heratio.uploads_path', '');
        $path = (string) ($do->path ?? '');
        $name = (string) ($do->name ?? '');
        $candidates = array_filter([
            $base !== '' ? rtrim($base, '/') . '/' . ltrim($path . $name, '/') : null,
            $base !== '' ? rtrim($base, '/') . '/' . ltrim($path, '/') : null,
            $path . $name,
            $path,
        ]);
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Load a signed manifest row's JSON as a manifest dict.
     *
     * @return array<string,mixed>|null
     */
    private function loadManifest(int $manifestId): ?array
    {
        $row = DB::table('ahg_c2pa_manifest')->where('id', $manifestId)->first(['manifest_json']);
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) $row->manifest_json, true);
        return is_array($decoded) ? $decoded : null;
    }
}
