<?php

/**
 * OcflBackfillEmbeddedMetadataExtensionCommand - retrofit the
 * `ahg-embedded-metadata` OCFL v1.1 extension onto OCFL objects that
 * were ingested before the extension shipped.
 *
 * Walks every OCFL object id known to the storage root, reads its
 * inventory.json, and - per OCFL v1.1 §3.7 - bumps it to a fresh
 * vN+1 carrying the new `extensions.ahg-embedded-metadata` block.
 *
 * The bump is a metadata-only version (no content files added /
 * removed): state + manifest of the new version are inherited from
 * the previous head. Per OCFL v1.1 §3.5 a version directory is
 * always created with its own inventory.json; we honour that by
 * writing both the per-version inventory.json and the canonical
 * (root) inventory.json.
 *
 * Idempotence: an object that already carries the extension block
 * is skipped (verified via Inventory::hasExtension). Re-running the
 * command therefore never produces a no-op version bump.
 *
 * Flags:
 *   --object=ID   restrict to a single OCFL object id or IO id
 *                 (the URN form is accepted verbatim; bare digits are
 *                 wrapped in `urn:heratio:io:`)
 *   --dry-run     report what WOULD be backfilled; no writes
 *   --limit=N     stop after N rewrites (excludes already-tagged
 *                 objects from the count, but they still count
 *                 toward "scanned")
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Console\Commands;

use AhgOcfl\Layout\Inventory;
use AhgOcfl\Layout\StorageRoot;
use AhgOcfl\Layout\Version;
use AhgOcfl\Metadata\DbEmbeddedMetadataPiiGate;
use AhgOcfl\Metadata\DbEmbeddedMetadataSource;
use AhgOcfl\Metadata\EmbeddedMetadataExtension;
use AhgOcfl\Metadata\EmbeddedMetadataPiiGate;
use AhgOcfl\Metadata\EmbeddedMetadataSource;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;

class OcflBackfillEmbeddedMetadataExtensionCommand extends Command
{
    protected $signature = 'ahg:ocfl:backfill-embedded-metadata-extension
        {--object= : Restrict to one OCFL object id (urn:heratio:io:N or bare N)}
        {--dry-run : Report what would change; perform no writes}
        {--limit=0 : Stop after this many rewrites (0 = no limit)}';

    protected $description = 'Add the ahg-embedded-metadata extension to existing OCFL objects that lack it.';

    public function handle(StorageRoot $root): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = max(0, (int) $this->option('limit'));

        $source = $this->resolveSource();
        $gate   = $this->resolveGate();

        // Wire the gate so the helper path on StorageRoot honours it
        // when we hand it the inventory after the metadata-only bump.
        $root->withEmbeddedMetadataSource($source);
        $root->withPiiGate($gate);

        $objectIds = $this->resolveObjectScope($root);
        if ($objectIds === []) {
            $this->warn('No OCFL objects found in storage root; nothing to backfill.');
            return self::SUCCESS;
        }

        $scanned     = 0;
        $alreadyDone = 0;
        $rewritten   = 0;
        $skippedNoData = 0;
        $errors      = 0;

        foreach ($objectIds as $objectId) {
            $scanned++;
            try {
                $object = $root->read($objectId);
                $inv    = $object->inventory;
                if ($inv->hasExtension(EmbeddedMetadataExtension::NAME)) {
                    $alreadyDone++;
                    continue;
                }

                $raw = $source->fetch($objectId);
                if ($raw === []) {
                    $skippedNoData++;
                    $this->line("  [skip] {$objectId}: no sidecar data");
                    continue;
                }
                $block = EmbeddedMetadataExtension::build($raw);
                if ($block === null) {
                    $skippedNoData++;
                    $this->line("  [skip] {$objectId}: sidecar produced empty block");
                    continue;
                }
                if ($gate !== null) {
                    $block = $gate->redact($objectId, $block);
                    if ($block === null || $block === []) {
                        $skippedNoData++;
                        $this->line("  [skip] {$objectId}: PII gate suppressed entire block");
                        continue;
                    }
                }

                if ($dryRun) {
                    $this->line("  [dry] {$objectId}: would bump {$inv->head} -> {$inv->nextVersionId()} with ".count($block).' fields');
                    $rewritten++;
                } else {
                    $this->bumpInventory($root, $objectId, $inv, $block);
                    $rewritten++;
                    $this->line("  [bump] {$objectId}: {$inv->head} -> {$inv->nextVersionId()}");
                }

                if ($limit > 0 && $rewritten >= $limit) {
                    $this->info("Reached --limit={$limit}; stopping.");
                    break;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  [error] {$objectId}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Backfill complete. scanned=%d already_tagged=%d rewritten=%d no_data=%d errors=%d%s',
            $scanned,
            $alreadyDone,
            $rewritten,
            $skippedNoData,
            $errors,
            $dryRun ? ' (dry-run)' : '',
        ));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Append a metadata-only version that carries the new extension.
     * State + manifest are inherited (no new content files), so the
     * version dir contains only the per-version inventory.json + its
     * sidecar. This is spec-conformant under OCFL v1.1 §3.5.
     */
    protected function bumpInventory(StorageRoot $root, string $objectId, Inventory $inv, array $block): void
    {
        // Inherit prior state under the new version id.
        $priorVersion = $inv->versions[$inv->head] ?? null;
        $inheritedState = $priorVersion?->state ?? [];

        $newVersion = new Version(
            created:     (new DateTimeImmutable('now'))->format(DateTimeInterface::RFC3339),
            state:       $inheritedState,
            message:     'Backfill ahg-embedded-metadata extension (issue #753)',
            userName:    (string) config('ocfl.cli_user_name', 'cli'),
            userAddress: config('ocfl.cli_user_address'),
        );

        // No new content - empty manifest delta. withNewVersion preserves
        // the existing manifest entries (OCFL §3.5.3.1 reuse).
        $next = $inv->withNewVersion($newVersion, [])
            ->withExtension(EmbeddedMetadataExtension::NAME, $block);

        // Write per-version + canonical inventory.json + sidecars. We
        // do this directly via the adapter because StorageRoot::write()
        // is shaped for content-bearing versions; a metadata-only bump
        // is a narrower path.
        $this->persistInventory($root, $objectId, $next);
    }

    /**
     * Persist a bumped inventory: vN/inventory.json + sidecar, plus the
     * canonical inventory.json + sidecar at the object root. Idempotent
     * if called twice with the same inventory bytes.
     */
    protected function persistInventory(StorageRoot $root, string $objectId, Inventory $inv): void
    {
        $rootPath = $root->objectRoot($objectId);
        $bytes    = $inv->toJson();
        $alg      = $inv->digestAlgorithm;
        $sidecar  = hash($alg, $bytes).' inventory.json'."\n";

        $root->adapter->put($rootPath.'/'.$inv->head.'/inventory.json', $bytes);
        $root->adapter->put($rootPath.'/'.$inv->head.'/inventory.json.'.$alg, $sidecar);
        $root->adapter->put($rootPath.'/inventory.json', $bytes);
        $root->adapter->put($rootPath.'/inventory.json.'.$alg, $sidecar);

        // Best-effort: keep object map current.
        try {
            \Illuminate\Support\Facades\DB::table('ahg_ocfl_object_map')
                ->where('ocfl_object_id', $objectId)
                ->update(['head_version' => $inv->head, 'updated_at' => now()]);
        } catch (\Throwable) {
            // Map absent (CI / dry test fixture) - swallow.
        }
    }

    protected function resolveObjectScope(StorageRoot $root): array
    {
        $opt = $this->option('object');
        if ($opt !== null && $opt !== '') {
            $opt = (string) $opt;
            if (ctype_digit($opt)) {
                $opt = "urn:heratio:io:{$opt}";
            }
            return [$opt];
        }
        return $root->list();
    }

    /**
     * Resolve the metadata source. Overridable in tests via container
     * binding (\Illuminate\Container\Container::make).
     */
    protected function resolveSource(): EmbeddedMetadataSource
    {
        try {
            if (function_exists('app')) {
                $src = app(EmbeddedMetadataSource::class);
                if ($src instanceof EmbeddedMetadataSource) {
                    return $src;
                }
            }
        } catch (\Throwable) {
            // Container not booted or no binding - fall through.
        }
        return new DbEmbeddedMetadataSource();
    }

    /**
     * Resolve the PII gate. Container binding takes precedence so an
     * operator can swap in a jurisdiction-specific gate (#751 follow-up).
     */
    protected function resolveGate(): ?EmbeddedMetadataPiiGate
    {
        try {
            if (function_exists('app')) {
                $gate = app(EmbeddedMetadataPiiGate::class);
                if ($gate instanceof EmbeddedMetadataPiiGate) {
                    return $gate;
                }
            }
        } catch (\Throwable) {
            // Fall through to default.
        }
        return new DbEmbeddedMetadataPiiGate();
    }
}
