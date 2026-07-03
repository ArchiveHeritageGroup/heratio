<?php

/**
 * BundleWorkerCommand - the consumer side of the portable_export queue.
 *
 * The wizard inserts rows with status='pending'. This command picks them
 * up (one at a time, FIFO unless --id forces a specific row), dumps the
 * scoped entities to JSON, copies digital-object derivative files into
 * an assets/ tree, emits a self-contained vanilla-JS HTML viewer for
 * read_only/editable modes, zips the result, and updates status/output_*
 * on the row.
 *
 * Wiring:
 *   - PortableExportController::apiStart dispatches via Artisan::queue so
 *     pending rows get picked up immediately on the queue worker.
 *   - AhgPortableExportServiceProvider::boot() schedules a daily safety-
 *     net run (--all-pending) for any rows the queue dispatch missed.
 *
 * Honours the 11 settings audited in #88:
 *   - portable_export_enabled       (master kill - pending rows held back)
 *   - portable_export_include_*     (which derivative usages get copied)
 *   - portable_export_max_size_mb   (already gated at apiStart; rechecked
 *                                    here as a defence-in-depth)
 *   - portable_export_default_*     (apiStart already applied; we just
 *                                    consume the row's stamped values)
 *   - portable_export_retention_days (PortableCleanupCommand handles)
 *
 * Out of scope (deliberately, for first-pass): per-locale entity dump,
 * advanced HTML viewer (search facets, taxonomy browser), incremental
 * resume on partial failure. The worker is single-shot; a failure marks
 * status='failed' with error_message and the operator restarts.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgPortableExport\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BundleWorkerCommand extends Command
{
    protected $signature = 'ahg:portable-export-worker
        {--id= : Specific portable_export.id to process (else next pending FIFO)}
        {--all-pending : Drain every pending row in this run}';

    protected $description = 'Process pending portable_export rows: dump entities, copy assets, build viewer, zip.';

    /**
     * #1389 disclosure tally for the run in progress — records withheld by each
     * gate. Reset per row in processOne().
     */
    private array $excluded = ['unpublished' => 0, 'icip' => 0, 'odrl' => 0, 'redacted_objects' => 0];

    /** #role-based — whether the exporting operator may see draft/unpublished records. */
    private bool $operatorCanViewDraft = false;

    public function handle(): int
    {
        $rows = $this->pickRows();
        if ($rows->isEmpty()) {
            $this->info('No pending rows.');

            return self::SUCCESS;
        }

        // Master kill-switch: if portable_export_enabled is off we leave
        // pending rows alone (the operator may still flip it back on).
        $enabled = (string) DB::table('ahg_settings')
            ->where('setting_key', 'portable_export_enabled')
            ->value('setting_value') ?: 'true';
        if (! in_array(strtolower($enabled), ['1', 'true', 'yes', 'on'], true)) {
            $this->info('portable_export_enabled is off - leaving '.$rows->count().' row(s) pending.');

            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;
        foreach ($rows as $row) {
            try {
                $this->processOne($row);
            } catch (\Throwable $e) {
                Log::error('Portable-export worker failed for id='.$row->id.': '.$e->getMessage(), [
                    'trace' => $e->getFile().':'.$e->getLine(),
                ]);
                DB::table('portable_export')->where('id', $row->id)->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 1000),
                    'completed_at' => now(),
                ]);
                $this->error('id='.$row->id.' FAILED: '.$e->getMessage());
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    private function pickRows(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('portable_export')) {
            return collect();
        }
        $q = DB::table('portable_export')->where('status', 'pending')->orderBy('id');
        $id = $this->option('id');
        if ($id) {
            return $q->where('id', (int) $id)->limit(1)->get();
        }
        if ($this->option('all-pending')) {
            return $q->get();
        }

        return $q->limit(1)->get();
    }

    private function processOne(object $row): void
    {
        $this->info('id='.$row->id.' "'.$row->title.'" ('.$row->scope_type.'/'.$row->mode.')');
        DB::table('portable_export')->where('id', $row->id)->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 1,
        ]);

        // Resolve scope -> IO id list, then apply the #1389 disclosure gates
        // (publication status, ICIP/TK protocols, ODRL) BEFORE anything is
        // written — over-inclusion into an offline package is unrecoverable.
        $this->excluded = ['unpublished' => 0, 'icip' => 0, 'odrl' => 0, 'redacted_objects' => 0,
            'perm_masters' => 0, 'perm_references' => 0, 'perm_thumbnails' => 0];

        // #role-based export — the operator can only export what THEIR role permits.
        // Force off any derivative tier they lack the ACL grant for (so a user
        // without master access can never dump masters), and gate draft inclusion
        // on viewDraft. Admins/editors pass; lower roles are trimmed. The public
        // #1389 disclosure gates (below) still apply on top of this.
        $operator = (object) ['id' => (int) ($row->user_id ?? 0)];
        $this->operatorCanViewDraft = \AhgCore\Services\AclService::check(null, 'viewDraft', $operator);
        if ((int) $row->include_masters && ! \AhgCore\Services\AclService::check(null, 'readMaster', $operator)) {
            $row->include_masters = 0;
            $this->excluded['perm_masters'] = 1;
        }
        if ((int) $row->include_references && ! \AhgCore\Services\AclService::check(null, 'readReference', $operator)) {
            $row->include_references = 0;
            $this->excluded['perm_references'] = 1;
        }
        if ((int) $row->include_thumbnails && ! \AhgCore\Services\AclService::check(null, 'readThumbnail', $operator)) {
            $row->include_thumbnails = 0;
            $this->excluded['perm_thumbnails'] = 1;
        }

        $permTrimmed = array_keys(array_filter([
            'masters' => $this->excluded['perm_masters'],
            'references' => $this->excluded['perm_references'],
            'thumbnails' => $this->excluded['perm_thumbnails'],
        ]));
        if ($permTrimmed) {
            $this->line('  role-based: excluded '.implode(', ', $permTrimmed).' (operator lacks the read grant)');
        }

        $rawIds = $this->resolveScopeIoIds($row);
        $ioIds = $this->applyDisclosureGates($rawIds, $row);
        $withheld = $this->excluded['unpublished'] + $this->excluded['icip'] + $this->excluded['odrl'];
        $this->line('  '.count($ioIds).' IOs in scope'
            .($withheld ? ' ('.$withheld.' withheld: '.$this->excluded['unpublished'].' unpublished, '
                .$this->excluded['icip'].' ICIP-restricted, '.$this->excluded['odrl'].' ODRL-gated)' : ''));

        // Destination: for a 'folder' export, build the bundle DIRECTLY on the
        // target drive (no temp staging → large dumps needn't fit in /tmp, and we
        // never zip). 'zip' builds in a temp workDir that is compressed + removed.
        $destination = (string) ($row->destination ?? 'zip');
        $folderName = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $row->title).'-'.$row->id;
        if ($destination === 'folder' && ! empty($row->destination_path)) {
            // Operator-chosen directory / mounted drive.
            $workDir = rtrim((string) $row->destination_path, '/').'/'.$folderName;
        } elseif ($destination === 'download') {
            // Managed staging area (on the large uploads volume) — the bundle is
            // left uncompressed and streamed as a ZIP64 on download (no second copy).
            $stagingBase = rtrim((string) config('heratio.uploads_path', sys_get_temp_dir()), '/').'/portable-export-staging';
            @mkdir($stagingBase, 0775, true);
            $workDir = $stagingBase.'/'.$folderName;
        } else {
            $destination = 'zip';
            $workDir = sys_get_temp_dir().'/heratio-portable-'.$row->id.'-'.substr(md5(microtime()), 0, 6);
        }
        @mkdir($workDir.'/data', 0775, true);
        @mkdir($workDir.'/assets', 0775, true);

        $stats = $this->dumpData($workDir, $row, $ioIds);
        DB::table('portable_export')->where('id', $row->id)->update(['progress' => 40,
            'total_descriptions' => $stats['descriptions'] ?? 0,
            'total_objects' => $stats['digital_objects'] ?? 0,
        ]);

        $copied = $this->copyAssets($workDir, $row, $ioIds);
        DB::table('portable_export')->where('id', $row->id)->update(['progress' => 70]);
        $this->line('  copied '.$copied.' asset file(s)'
            .($this->excluded['redacted_objects'] ? ' ('.$this->excluded['redacted_objects'].' redacted object(s) withheld)' : ''));

        // #1389 — write the disclosure summary into the package so the recipient
        // (and the operator, via the stamped column) can see what was withheld.
        $disclosure = [
            'generated_at'     => now()->toIso8601String(),
            'records_in_scope' => count($rawIds),
            'records_included' => count($ioIds),
            'withheld'         => $this->excluded,
            'exported_by'      => (int) ($row->user_id ?? 0),
            'note'             => 'Content was excluded to honour (1) the exporting operator\'s role/ACL — perm_masters/perm_references/perm_thumbnails=1 mean that derivative tier was dropped because the operator lacks the read grant, and drafts are withheld unless the operator has viewDraft; and (2) the public disclosure gates: publication status, ICIP/TK cultural protocols, ODRL access policies, and PII redaction. Counts reflect what was NOT exported.',
        ];
        @file_put_contents($workDir.'/data/disclosure-summary.json',
            json_encode($disclosure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Human-readable guide shipped in the bundle root so a recipient who
        // opens the folder/zip knows what it is, how to view it, and what was
        // (and was not) included. Carries the AHG attribution + copyright.
        $this->writeReadme($workDir, $row, $stats, $disclosure);

        if (in_array($row->mode, ['read_only', 'editable'], true)) {
            $this->emitViewer($workDir, $row, $stats);
        }
        DB::table('portable_export')->where('id', $row->id)->update(['progress' => 85]);

        // Finalise. Folder destination leaves the uncompressed bundle in place on
        // the target drive; zip destination compresses the temp workDir + cleans up.
        if ($destination === 'folder' || $destination === 'download') {
            $outPath = $workDir;
            $size = $this->dirSize($workDir);
            $this->line('  bundle at: '.$outPath.($destination === 'download' ? ' (streamed as ZIP on download)' : ''));
        } else {
            $outDir = dirname((string) $row->output_path) ?: sys_get_temp_dir();
            @mkdir($outDir, 0775, true);
            $outPath = $row->output_path
                ?: ($outDir.'/'.preg_replace('/[^A-Za-z0-9_-]/', '-', $row->title).'-'.$row->id.'.zip');
            $this->zipDir($workDir, $outPath);
            $size = filesize($outPath) ?: 0;
            $this->rrmdir($workDir);
        }

        DB::table('portable_export')->where('id', $row->id)->update([
            'status' => 'complete',
            'progress' => 100,
            'output_path' => $outPath,
            'output_size' => $size,
            'disclosure_summary' => json_encode($disclosure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'completed_at' => now(),
            'expires_at' => now()->addDays((int) (DB::table('ahg_settings')->where('setting_key', 'portable_export_retention_days')->value('setting_value') ?: 30)),
        ]);
        $this->info('  -> '.$outPath.' ('.round($size / 1048576, 1).' MB)');
    }

    /**
     * Resolve scope_type + scope_repository_id + scope_slug + scope_items
     * into the list of IO ids to include in the bundle.
     *
     * scope_type:
     *   all        - every IO except root (id=1)
     *   repository - all IOs with the given repository_id
     *   fonds      - the fonds (lft/rgt subtree of) scope_slug
     *   clipboard  - explicit list of slugs from scope_items.items
     */
    /**
     * #1389 — apply disclosure gates to the resolved IO id set before export.
     * Removes records that must not enter an ungated offline package:
     *   - unpublished (status type_id 158 / status_id 160), unless the
     *     portable_export_include_unpublished setting is explicitly on;
     *   - ICIP/TK protocol-restricted (icip_access_restriction), including whole
     *     subtrees flagged applies_to_descendants;
     *   - ODRL use-prohibited (research_rights_policy prohibition on 'use').
     * Each excluded id is counted once, in that precedence order, into $excluded.
     */
    private function applyDisclosureGates(array $ioIds, object $row): array
    {
        $ioIds = array_values(array_unique(array_map('intval', $ioIds)));
        if (empty($ioIds)) {
            return [];
        }

        // Central, fail-closed gate is the single source of truth for the
        // confidentiality rules (ICIP/TK + ODRL). Publication is handled here
        // too, but with the export-only `include_unpublished` operator override
        // that the shared gate deliberately doesn't offer.
        $gate = app(\AhgCore\Services\DisclosureGate::class);
        $icip = array_flip($gate->icipRestrictedIds());
        $odrl = array_flip($gate->odrlRestrictedIds());

        // #role-based — unpublished may be exported only when the setting allows it
        // AND the operator actually has the viewDraft grant.
        $includeUnpublished = $this->operatorCanViewDraft
            && (string) (DB::table('ahg_settings')
                ->where('setting_key', 'portable_export_include_unpublished')->value('setting_value')) === '1';
        $published = [];
        if (! $includeUnpublished && Schema::hasTable('status')) {
            $published = array_flip(DB::table('status')
                ->whereIn('object_id', $ioIds)
                ->where('type_id', \AhgCore\Services\DisclosureGate::STATUS_TYPE_PUBLICATION)
                ->where('status_id', \AhgCore\Services\DisclosureGate::STATUS_PUBLISHED)
                ->pluck('object_id')->map('intval')->all());
        }

        $kept = [];
        foreach ($ioIds as $id) {
            if (! $includeUnpublished && ! isset($published[$id])) { $this->excluded['unpublished']++; continue; }
            if (isset($icip[$id])) { $this->excluded['icip']++; continue; }
            if (isset($odrl[$id])) { $this->excluded['odrl']++; continue; }
            $kept[] = $id;
        }

        return $kept;
    }

    private function resolveScopeIoIds(object $row): array
    {
        $ioQ = DB::table('information_object')->where('id', '!=', 1);
        switch ((string) $row->scope_type) {
            case 'repository':
                if ($row->scope_repository_id) {
                    $ioQ->where('repository_id', $row->scope_repository_id);
                }
                break;
            case 'fonds':
                if ($row->scope_slug) {
                    $anc = DB::table('information_object as io')
                        ->join('slug', 'io.id', '=', 'slug.object_id')
                        ->where('slug.slug', $row->scope_slug)
                        ->select('io.lft', 'io.rgt')
                        ->first();
                    if ($anc) {
                        $ioQ->whereBetween('lft', [$anc->lft, $anc->rgt]);
                    } else {
                        return [];
                    }
                }
                break;
            case 'clipboard':
                $items = json_decode((string) ($row->scope_items ?? ''), true);
                $slugs = is_array($items['items'] ?? null) ? $items['items'] : [];
                if (empty($slugs)) {
                    return [];
                }

                return DB::table('slug')->whereIn('slug', $slugs)->pluck('object_id')->all();
            case 'all':
            default:
                break;
        }

        return $ioQ->pluck('id')->all();
    }

    /**
     * Write a plain-text README/user guide into the bundle root. Explains the
     * layout, how to open it, and what the disclosure gates withheld. Carries
     * the AHG attribution (https://theahg.co.za) + copyright line.
     */
    private function writeReadme(string $workDir, object $row, array $stats, array $disclosure): void
    {
        $year = now()->year;
        $title = trim((string) ($row->title ?? '')) ?: 'Untitled export';
        $mode = (string) ($row->mode ?? 'read_only');
        $hasViewer = in_array($mode, ['read_only', 'editable'], true);
        $gen = now()->toDayDateTimeString();

        $descriptions = (int) ($stats['descriptions'] ?? 0);
        $objects = (int) ($stats['digital_objects'] ?? 0);
        $actors = (int) ($stats['actors'] ?? 0);
        $repos = (int) ($stats['repositories'] ?? 0);

        $w = $disclosure['withheld'] ?? [];
        $withheldLines = [];
        foreach ([
            'redacted_objects'   => 'digital objects withheld for PII redaction',
            'unpublished'        => 'unpublished / draft records withheld',
            'icip'               => 'records withheld under ICIP / cultural (TK) protocols',
            'odrl'               => 'records withheld under ODRL access policies',
            'perm_masters'       => 'master (full-resolution) files withheld — exporter lacks the read grant',
            'perm_references'    => 'reference derivatives withheld — exporter lacks the read grant',
            'perm_thumbnails'    => 'thumbnails withheld — exporter lacks the read grant',
        ] as $k => $label) {
            $n = (int) ($w[$k] ?? 0);
            if ($n > 0) {
                $withheldLines[] = sprintf('  - %d %s', $n, $label);
            }
        }
        $withheldBlock = $withheldLines
            ? "The following were deliberately excluded (see data/disclosure-summary.json):\n".implode("\n", $withheldLines)
            : "Nothing was withheld by the disclosure gates for this export.";

        $viewerBlock = $hasViewer
            ? "1. Open  index.html  in any modern web browser (double-click it). No\n"
              ."   internet connection, server, or install is required — the viewer and\n"
              ."   all data travel inside this bundle.\n"
              ."2. Browse the archival descriptions, authority records and digital\n"
              ."   objects offline.\n"
            : "This is a DATA-ONLY export (no offline viewer was generated for this\n"
              ."mode). Open the JSON files under  data/  with any text editor or import\n"
              ."them into your own system.\n";

        $editableBlock = ($mode === 'editable')
            ? "\nThis is an EDITABLE research package. You can add notes, sources,\n"
              ."metadata suggestions and files to any record while offline, then use\n"
              ."\"Save for sync\" to bring your work back into Heratio. Full step-by-step\n"
              ."instructions are in  USER-MANUAL.txt  in this folder.\n"
            : "";

        $readme = <<<TXT
================================================================================
 {$title}
 Portable archival export
================================================================================

Generated : {$gen}
Contents  : {$descriptions} archival description(s), {$objects} digital object(s),
            {$actors} authority record(s), {$repos} repository record(s).

--------------------------------------------------------------------------------
 HOW TO OPEN THIS PACKAGE
--------------------------------------------------------------------------------
{$viewerBlock}{$editableBlock}
--------------------------------------------------------------------------------
 WHAT IS INSIDE
--------------------------------------------------------------------------------
  index.html ............. Self-contained offline viewer (when present).
  data/ .................. Machine-readable exports:
      ios.json ........... Archival descriptions.
      actors.json ........ Authority records (people, organisations, families).
      repositories.json .. Holding repositories.
      digital_objects.json Digital object metadata.
      manifest.json ...... Export manifest (scope, counts, versions).
      disclosure-summary.json  What was included / withheld and why.
  assets/ ................ Exported image / media files (where permitted).

--------------------------------------------------------------------------------
 SECURITY & PERMISSIONS
--------------------------------------------------------------------------------
This package contains ONLY what the exporting user was permitted to see. Access
is enforced two ways: (1) the exporter's own role / ACL — derivative tiers and
draft records they cannot read are dropped; and (2) the public disclosure gates
— publication status, ICIP/TK cultural protocols, ODRL access policies, and PII
redaction.

{$withheldBlock}

Handle this package in line with the access conditions of the originating
repository. Redistribution may be restricted by cultural protocols, donor
agreements, or copyright.

--------------------------------------------------------------------------------
 ABOUT
--------------------------------------------------------------------------------
Generated by Heratio, an archival & heritage management platform by
The Archive & Heritage Group.

  Website : https://theahg.co.za

Copyright (C) {$year} The Archive & Heritage Group. All rights reserved.
Exported metadata and digital objects remain the property of their respective
rights holders and originating repositories.
================================================================================
TXT;

        @file_put_contents($workDir.'/README.txt', $readme);

        if ($mode === 'editable') {
            $this->writeUserManual($workDir, $row);
        }
    }

    /**
     * Write the researcher user manual into an editable package. Explains the
     * full offline-work → Save for sync round trip in plain language.
     */
    private function writeUserManual(string $workDir, object $row): void
    {
        $title = trim((string) ($row->title ?? '')) ?: 'Research package';
        $year = now()->year;

        $manual = <<<TXT
================================================================================
 {$title}
 OFFLINE RESEARCH — USER MANUAL
================================================================================

This package is a complete, self-contained copy of your selected records that
runs in a web browser with NO internet, server or install. You can read and
ANNOTATE it anywhere — on a laptop, a USB stick, or in the field — and then
bring your work back into Heratio.

--------------------------------------------------------------------------------
 1. OPENING THE PACKAGE
--------------------------------------------------------------------------------
  * Double-click  index.html . It opens in your default browser (Chrome, Edge,
    Firefox or Safari — a recent version).
  * To use it from a USB stick or CD, copy this WHOLE folder and keep every file
    and sub-folder together.
  * Pick a record from the list on the left to see its full details and images.

--------------------------------------------------------------------------------
 2. WORKING OFFLINE — WHAT YOU CAN ADD
--------------------------------------------------------------------------------
Open any record. Below its details you will find "Your offline work on this
record" with four tabs:

  NOTES        Free-text research notes about the record. Click "Save note".

  SOURCES      References and citations you want to attach — title, author,
               year and a URL or shelf reference. Click "Add source".

  SUGGESTIONS  Proposed corrections or additions to the record's metadata.
               Give the field name (e.g. Title, Dates, Scope and content) and
               your suggested text, then "Add suggestion". These are proposals —
               a curator reviews them before anything changes in the catalogue.

  FILES        Photos or documents you gather offline (e.g. field-work images).
               Choose files to attach them to the record. Keep them small — they
               are stored inside your browser and embedded in the sync file.

Everything you add is saved automatically in THIS browser on THIS computer. The
counter in the bar at the bottom shows how many changes you have.

--------------------------------------------------------------------------------
 3. SAVING YOUR WORK FOR SYNC
--------------------------------------------------------------------------------
When you are finished (and back where you can reach Heratio):

  1. Click  "Save for sync"  in the bar at the bottom of the viewer.
  2. Your browser downloads a file named  researcher-sync-<number>.json .
     This holds all your notes, sources, suggestions and files.
  3. Keep that file — you will upload it in the next step.

  * "Clear" wipes all your offline changes in this package. Use with care.
  * Work in ONE browser on ONE computer per package, so all your changes end up
    in the same sync file.

--------------------------------------------------------------------------------
 4. BRINGING YOUR WORK BACK INTO HERATIO
--------------------------------------------------------------------------------
  1. Log in to Heratio and go to  Research  >  Work Offline .
  2. Choose your package and upload the  researcher-sync-<number>.json  file.
  3. Heratio checks the file belongs to you and this package, then applies your
     work: notes and sources are added to your research, files go to your
     workspace, and metadata suggestions are queued for a curator to review.

--------------------------------------------------------------------------------
 GOOD TO KNOW
--------------------------------------------------------------------------------
  * This package only contains records you are permitted to see. Restricted,
    embargoed or unpublished records are automatically left out.
  * Your offline changes never alter the live catalogue directly — they come
    back as YOUR contributions and (for metadata) as suggestions for review.
  * Handle the package in line with the access conditions of the originating
    repository; redistribution may be restricted.

================================================================================
 Heratio — archival & heritage management by The Archive & Heritage Group
 https://theahg.co.za
 Copyright (C) {$year} The Archive & Heritage Group. All rights reserved.
================================================================================
TXT;

        @file_put_contents($workDir.'/USER-MANUAL.txt', $manual);
    }

    /**
     * Dump entities to bundle/data/*.json. Honours the row's culture for
     * i18n joins. Returns counts the worker stamps onto the row.
     */
    private function dumpData(string $workDir, object $row, array $ioIds): array
    {
        $culture = (string) ($row->culture ?: 'en');
        $stats = ['descriptions' => 0, 'actors' => 0, 'repositories' => 0, 'digital_objects' => 0, 'terms' => 0];

        // Information objects (with i18n fields and slug)
        $ios = empty($ioIds) ? collect() : DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->whereIn('io.id', $ioIds)
            ->select(
                'io.id', 'io.identifier', 'io.parent_id', 'io.repository_id',
                'io.level_of_description_id', 'io.lft', 'io.rgt', 'io.source_standard',
                'slug.slug',
                'i18n.title', 'i18n.alternate_title', 'i18n.edition',
                'i18n.scope_and_content', 'i18n.access_conditions',
                'i18n.physical_characteristics', 'i18n.extent_and_medium',
                'i18n.archival_history', 'i18n.acquisition',
                'i18n.appraisal', 'i18n.accruals', 'i18n.arrangement',
                'i18n.reproduction_conditions', 'i18n.finding_aids',
                'i18n.location_of_originals', 'i18n.location_of_copies',
                'i18n.related_units_of_description'
            )
            ->orderBy('io.lft')
            ->get();
        file_put_contents($workDir.'/data/ios.json', json_encode($ios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stats['descriptions'] = $ios->count();

        // Repositories referenced by these IOs
        $repoIds = $ios->pluck('repository_id')->filter()->unique()->values();
        $repos = $repoIds->isEmpty() ? collect() : DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('ai.id', '=', 'r.id')->where('ai.culture', '=', $culture);
            })
            ->whereIn('r.id', $repoIds)
            ->select('r.id', 'ai.authorized_form_of_name as name', 'ai.history')
            ->get();
        file_put_contents($workDir.'/data/repositories.json', json_encode($repos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stats['repositories'] = $repos->count();

        // Actors related via the relation table (creators, name access points)
        $actorIds = empty($ioIds) ? collect() : DB::table('relation')
            ->whereIn('object_id', $ioIds)
            ->whereIn('subject_id', function ($q) {
                $q->select('id')->from('actor');
            })
            ->pluck('subject_id')->unique()->values();
        $actors = $actorIds->isEmpty() ? collect() : DB::table('actor as a')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('ai.id', '=', 'a.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'a.id')
            ->whereIn('a.id', $actorIds)
            ->select('a.id', 'slug.slug', 'ai.authorized_form_of_name as name', 'ai.dates_of_existence', 'ai.history')
            ->get();
        file_put_contents($workDir.'/data/actors.json', json_encode($actors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stats['actors'] = $actors->count();

        // Digital objects (metadata only, regardless of include_objects -
        // the JSON file lists every DO but the assets/ directory only
        // contains files for the usages flagged include_*).
        $dos = empty($ioIds) ? collect() : DB::table('digital_object')
            ->whereIn('object_id', $ioIds)
            ->select('id', 'object_id', 'usage_id', 'mime_type', 'media_type_id', 'name', 'path', 'byte_size', 'parent_id')
            ->orderBy('object_id')->orderBy('usage_id')
            ->get();
        file_put_contents($workDir.'/data/digital_objects.json', json_encode($dos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stats['digital_objects'] = $dos->count();

        // Manifest (also includes the row's own metadata so the viewer can
        // render the title/branding without a second source).
        $branding = json_decode((string) ($row->branding ?? ''), true) ?: [];
        $manifest = [
            'export_id' => (int) $row->id,
            'title' => (string) $row->title,
            'mode' => (string) $row->mode,
            'culture' => $culture,
            'scope' => (string) $row->scope_type,
            'created' => (string) ($row->created_at ?? now()),
            'branding' => $branding,
            'counts' => $stats,
        ];
        file_put_contents($workDir.'/data/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $stats;
    }

    /**
     * Copy digital-object derivative files into bundle/assets/<usage>/<id>/.
     * usage_id 140=master, 141=reference, 142=thumbnail. Each include_*
     * flag gates the corresponding usage.
     */
    private function copyAssets(string $workDir, object $row, array $ioIds): int
    {
        if (empty($ioIds)) {
            return 0;
        }
        if (! (int) ($row->include_objects ?? 1)) {
            return 0;
        } // master switch on the include set

        $usagesToCopy = [];
        if ((int) $row->include_thumbnails) {
            $usagesToCopy[142] = 'thumb';
        }
        if ((int) $row->include_references) {
            $usagesToCopy[141] = 'ref';
        }
        if ((int) $row->include_masters) {
            $usagesToCopy[140] = 'master';
        }
        if (empty($usagesToCopy)) {
            return 0;
        }

        // #1389 — records carrying PII visual-redaction regions must not ship
        // their ORIGINAL derivatives (the exporter has no redacted rendition).
        // The central DisclosureGate decides; withhold every derivative of a
        // redacted record and tally it.
        $gate = app(\AhgCore\Services\DisclosureGate::class);
        $skippedRedacted = [];

        $uploadsBase = rtrim((string) config('heratio.uploads_path', '/tmp'), '/');
        $copied = 0;
        $dos = DB::table('digital_object')->whereIn('object_id', $ioIds)
            ->whereIn('usage_id', array_keys($usagesToCopy))
            ->select('id', 'object_id', 'usage_id', 'name', 'path')->get();
        foreach ($dos as $do) {
            if ($gate->hasRedactions((int) $do->object_id)) {
                $skippedRedacted[(int) $do->object_id] = true;
                continue;
            }
            $usageDir = $usagesToCopy[$do->usage_id] ?? null;
            if (! $usageDir) {
                continue;
            }
            $src = $uploadsBase.'/'.ltrim((string) $do->path, '/').$do->name;
            // Same path resolution as DigitalObjectController::upload (handle dir-with-name shape)
            if (! file_exists($src)) {
                $alt = $uploadsBase.'/'.ltrim((string) $do->path, '/');
                if (is_dir($alt)) {
                    $src = rtrim($alt, '/').'/'.$do->name;
                }
            }
            if (! file_exists($src)) {
                continue;
            }
            $destDir = $workDir.'/assets/'.$usageDir.'/'.$do->object_id;
            if (! is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            $destName = $do->name ?: ('file-'.$do->id);
            if (@copy($src, $destDir.'/'.$destName)) {
                $copied++;
            }
        }

        $this->excluded['redacted_objects'] += count($skippedRedacted);

        return $copied;
    }

    /**
     * Emit a self-contained vanilla-JS viewer at bundle/index.html. The
     * viewer fetches data/ios.json and renders a list+detail UI. No build
     * step, no external CDN - everything inlined so the bundle works
     * offline. Editable mode adds local annotation save (localStorage).
     */
    /** @noinspection PhpUnused */
    private function emitViewer(string $workDir, object $row, array $stats): void
    {
        $title = htmlspecialchars((string) $row->title, ENT_QUOTES, 'UTF-8');
        $editable = $row->mode === 'editable' ? 'true' : 'false';
        $branding = json_decode((string) ($row->branding ?? ''), true) ?: [];
        $bTitle = htmlspecialchars((string) ($branding['title'] ?? $row->title), ENT_QUOTES, 'UTF-8');
        $bSubtitle = htmlspecialchars((string) ($branding['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
        $bFooter = htmlspecialchars((string) ($branding['footer'] ?? 'Generated by Heratio.'), ENT_QUOTES, 'UTF-8');
        $countLine = sprintf('%d descriptions, %d actors, %d repositories, %d digital objects',
            $stats['descriptions'], $stats['actors'], $stats['repositories'], $stats['digital_objects']);

        // Sync identity — lets the offline editable viewer produce a
        // researcher-sync.json the online app can verify + consume (Phase 2/3).
        $syncCfg = json_encode([
            'package_id' => (int) $row->id,
            'sync_token' => (string) ($row->sync_token ?? ''),
            'group_source' => (string) ($row->group_source ?? ''),
            'group_ref' => (int) ($row->group_ref ?? 0),
            'title' => (string) $row->title,
        ], JSON_UNESCAPED_SLASHES);

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f7f7f7;color:#222}
header{background:#234;color:#fff;padding:1rem 1.25rem;border-bottom:3px solid #58a}
header h1{margin:0;font-size:1.4rem}
header .subtitle{opacity:.8;font-size:.9rem}
header .meta{opacity:.7;font-size:.78rem;margin-top:.3rem}
main{display:grid;grid-template-columns:340px 1fr;min-height:calc(100vh - 8rem)}
.list{background:#fff;border-right:1px solid #ddd;overflow-y:auto;max-height:calc(100vh - 4rem)}
.list .search{position:sticky;top:0;background:#fff;padding:.6rem;border-bottom:1px solid #eee}
.list .search input{width:100%;padding:.4rem .55rem;border:1px solid #ccc;border-radius:.25rem;font-size:.9rem}
.list ul{list-style:none;margin:0;padding:0}
.list li{padding:.55rem .8rem;border-bottom:1px solid #f1f1f1;cursor:pointer;font-size:.92rem;line-height:1.3}
.list li:hover{background:#fafafa}
.list li.active{background:#eaf2f8;border-left:3px solid #58a;padding-left:calc(.8rem - 3px)}
.list li .ident{display:block;font-size:.78rem;color:#888}
.detail{padding:1.5rem 2rem;overflow-y:auto;max-height:calc(100vh - 4rem)}
.detail h2{margin-top:0;font-size:1.5rem;color:#234}
.detail .ident{color:#888;font-family:monospace;font-size:.9rem}
.detail dl{display:grid;grid-template-columns:170px 1fr;gap:.4rem 1rem;margin-top:1.2rem}
.detail dt{font-weight:600;color:#456}
.detail dd{margin:0;white-space:pre-wrap}
.detail .images{margin-top:1.2rem;display:flex;flex-wrap:wrap;gap:.6rem}
.detail .images img{max-width:240px;max-height:240px;border:1px solid #ddd;border-radius:.25rem;background:#fff;padding:.3rem}
.detail .doi{margin-top:1rem;padding:.7rem 1rem;background:#fff8e6;border:1px solid #f0d570;border-radius:.25rem;font-size:.86rem;color:#73510e}
.notes{margin-top:1.5rem}
.notes textarea{width:100%;min-height:120px;padding:.5rem;border:1px solid #ccc;border-radius:.25rem;font-family:inherit}
.notes .save{margin-top:.4rem;padding:.4rem 1rem;background:#58a;color:#fff;border:0;border-radius:.25rem;cursor:pointer}
footer{background:#234;color:#fff;padding:.6rem 1.25rem;font-size:.8rem;opacity:.85}
.empty{padding:2rem;color:#888;font-style:italic}
/* Editable capture panel + sync */
.cap{margin-top:1.5rem;border-top:2px solid #e3e3e3;padding-top:1rem}
.cap h3{font-size:1rem;color:#234;margin:.2rem 0 .5rem}
.cap .tabs{display:flex;gap:.3rem;flex-wrap:wrap;margin-bottom:.6rem}
.cap .tabs button{border:1px solid #ccd;background:#fff;padding:.3rem .7rem;border-radius:1rem;cursor:pointer;font-size:.82rem;color:#456}
.cap .tabs button.on{background:#58a;color:#fff;border-color:#58a}
.cap .pane{display:none}
.cap .pane.on{display:block}
.cap textarea,.cap input,.cap select{width:100%;padding:.4rem .5rem;border:1px solid #ccc;border-radius:.25rem;font-family:inherit;font-size:.88rem;margin-bottom:.4rem}
.cap textarea{min-height:110px}
.cap .row2{display:grid;grid-template-columns:1fr 1fr;gap:.4rem}
.cap .btn{padding:.35rem .8rem;background:#58a;color:#fff;border:0;border-radius:.25rem;cursor:pointer;font-size:.85rem}
.cap .btn.ghost{background:#eef;color:#345}
.cap .entry{background:#f6f8fa;border:1px solid #e2e6ea;border-radius:.25rem;padding:.45rem .6rem;margin-bottom:.35rem;font-size:.84rem;display:flex;justify-content:space-between;gap:.5rem}
.cap .entry .x{cursor:pointer;color:#a33;font-weight:700}
.savedflag{color:#2a7;font-size:.8rem;margin-left:.5rem}
.syncbar{position:sticky;bottom:0;background:#1f2a37;color:#fff;display:flex;align-items:center;gap:1rem;padding:.6rem 1.25rem;border-top:2px solid #58a;flex-wrap:wrap}
.syncbar .grow{flex:1;font-size:.85rem;opacity:.9}
.syncbar button{padding:.45rem 1rem;border:0;border-radius:.25rem;cursor:pointer;font-weight:600}
.syncbar .save{background:#3a8;color:#fff}
.syncbar .reset{background:#556;color:#fff}
.syncbar .count{background:#2c3a4a;padding:.2rem .55rem;border-radius:1rem;font-size:.78rem}
</style>
</head>
<body>
<header>
  <h1>{$bTitle}</h1>
  <div class="subtitle">{$bSubtitle}</div>
  <div class="meta">{$countLine}</div>
</header>
<main>
  <aside class="list">
    <div class="search"><input type="search" id="q" placeholder="Search title or identifier"></div>
    <ul id="ios"></ul>
  </aside>
  <section class="detail" id="detail">
    <div class="empty">Select a description from the list to view it here.</div>
  </section>
</main>
<div class="syncbar" id="syncbar" style="display:none">
  <span class="grow"><strong>Working offline.</strong> Your notes, sources, suggestions and files are saved in this browser. When back online, click <em>Save for sync</em> and upload the file in Heratio under Research &rsaquo; Work Offline.</span>
  <span class="count" id="synccount">0 changes</span>
  <button class="reset" id="syncreset" type="button">Clear</button>
  <button class="save" id="syncsave" type="button">Save for sync</button>
</div>
<footer>{$bFooter}</footer>
<script>
(function(){
  var EDITABLE = {$editable};
  var SYNC = {$syncCfg};
  var ios = [], dos = [], actors = [], repos = [];
  var byId = {};
  var listEl = document.getElementById('ios');
  var detailEl = document.getElementById('detail');
  var qEl = document.getElementById('q');
  var current = null;
  Promise.all([
    fetch('data/ios.json').then(function(r){return r.json()}),
    fetch('data/digital_objects.json').then(function(r){return r.json()}),
    fetch('data/actors.json').then(function(r){return r.json()}),
    fetch('data/repositories.json').then(function(r){return r.json()}),
  ]).then(function(d){
    ios = d[0] || []; dos = d[1] || []; actors = d[2] || []; repos = d[3] || [];
    ios.forEach(function(io){ byId[io.id] = io; });
    render('');
  }).catch(function(e){
    detailEl.innerHTML = '<div class="empty">Failed to load bundle data: ' + e.message + '</div>';
  });
  function render(filter){
    var f = (filter || '').toLowerCase();
    var html = '';
    var shown = ios.filter(function(io){
      if (!f) return true;
      return ((io.title||'').toLowerCase().indexOf(f) !== -1)
        || ((io.identifier||'').toLowerCase().indexOf(f) !== -1)
        || ((io.slug||'').toLowerCase().indexOf(f) !== -1);
    });
    shown.forEach(function(io){
      html += '<li data-id="' + io.id + '"' + (current === io.id ? ' class="active"' : '') + '>'
        + esc(io.title || '(untitled)')
        + (io.identifier ? '<span class="ident">' + esc(io.identifier) + '</span>' : '')
        + '</li>';
    });
    listEl.innerHTML = html || '<li class="empty">No matches</li>';
  }
  function esc(s){ return String(s||'').replace(/[<>&"]/g, function(c){return ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'})[c]}); }
  qEl.addEventListener('input', function(){ render(this.value); });
  listEl.addEventListener('click', function(e){
    var li = e.target.closest('li[data-id]');
    if (!li) return;
    current = parseInt(li.dataset.id, 10);
    render(qEl.value);
    show(current);
  });
  function show(id){
    var io = byId[id];
    if (!io) { detailEl.innerHTML = '<div class="empty">Not found.</div>'; return; }
    var ioDos = dos.filter(function(d){return d.object_id === id});
    var refs = ioDos.filter(function(d){return d.usage_id == 141});
    var thumbs = ioDos.filter(function(d){return d.usage_id == 142});
    var images = refs.length ? refs : thumbs;
    var imgHtml = images.map(function(d){
      var sub = d.usage_id == 141 ? 'ref' : (d.usage_id == 142 ? 'thumb' : 'master');
      return '<img src="assets/' + sub + '/' + d.object_id + '/' + esc(d.name||'') + '" alt="">';
    }).join('');
    var fields = [
      ['Identifier', io.identifier],
      ['Slug', io.slug],
      ['Source standard', io.source_standard],
      ['Scope and content', io.scope_and_content],
      ['Extent and medium', io.extent_and_medium],
      ['Administrative / biographical history', io.administrative_biographical_history],
      ['Archival history', io.archival_history],
      ['Acquisition', io.acquisition],
      ['Access conditions', io.access_conditions],
      ['Physical characteristics', io.physical_characteristics],
    ].filter(function(p){return p[1]});
    var dl = '<dl>' + fields.map(function(p){return '<dt>' + esc(p[0]) + '</dt><dd>' + esc(p[1]) + '</dd>';}).join('') + '</dl>';
    var cap = EDITABLE ? capturePanel(io) : '';
    detailEl.innerHTML = '<h2>' + esc(io.title || '(untitled)') + '</h2>'
      + (io.identifier ? '<div class="ident">' + esc(io.identifier) + '</div>' : '')
      + (imgHtml ? '<div class="images">' + imgHtml + '</div>' : '')
      + dl
      + cap;
    if (EDITABLE) wireCapture(io);
  }

  // ---------- Offline capture (editable mode) ----------
  var PFX = 'hsync:' + (SYNC.package_id || 0) + ':';
  function kNote(id){ return PFX + 'note:' + id; }
  function kSrc(id){ return PFX + 'src:' + id; }
  function kSug(id){ return PFX + 'sug:' + id; }
  function kFile(id){ return PFX + 'file:' + id; }
  function getArr(k){ try { return JSON.parse(localStorage.getItem(k) || '[]'); } catch(e){ return []; } }
  function setArr(k,v){ if(v.length){ localStorage.setItem(k, JSON.stringify(v)); } else { localStorage.removeItem(k); } updateCount(); }

  function capturePanel(io){
    var id = io.id;
    var note = localStorage.getItem(kNote(id)) || '';
    var srcs = getArr(kSrc(id)), sugs = getArr(kSug(id)), files = getArr(kFile(id));
    return '<div class="cap">'
      + '<h3>Your offline work on this record</h3>'
      + '<div class="tabs">'
        + '<button data-t="notes" class="on" type="button">Notes</button>'
        + '<button data-t="src" type="button">Sources (' + srcs.length + ')</button>'
        + '<button data-t="sug" type="button">Suggestions (' + sugs.length + ')</button>'
        + '<button data-t="file" type="button">Files (' + files.length + ')</button>'
      + '</div>'
      + '<div class="pane on" data-p="notes">'
        + '<textarea id="c-note" placeholder="Your research notes on this record">' + esc(note) + '</textarea>'
        + '<button class="btn" id="c-note-save" type="button">Save note</button><span class="savedflag" id="c-note-flag"></span>'
      + '</div>'
      + '<div class="pane" data-p="src">'
        + '<div class="row2"><input id="c-src-title" placeholder="Source title"><input id="c-src-author" placeholder="Author"></div>'
        + '<div class="row2"><input id="c-src-year" placeholder="Year"><input id="c-src-url" placeholder="URL / reference"></div>'
        + '<button class="btn" id="c-src-add" type="button">Add source</button>'
        + '<div id="c-src-list" style="margin-top:.5rem">' + renderEntries(srcs, srcLabel, 'src') + '</div>'
      + '</div>'
      + '<div class="pane" data-p="sug">'
        + '<input id="c-sug-field" placeholder="Field (e.g. Title, Dates, Scope and content)">'
        + '<textarea id="c-sug-text" style="min-height:70px" placeholder="Your suggested correction or addition"></textarea>'
        + '<button class="btn" id="c-sug-add" type="button">Add suggestion</button>'
        + '<div id="c-sug-list" style="margin-top:.5rem">' + renderEntries(sugs, sugLabel, 'sug') + '</div>'
      + '</div>'
      + '<div class="pane" data-p="file">'
        + '<input type="file" id="c-file-input" multiple>'
        + '<div style="font-size:.78rem;color:#888;margin:.3rem 0 .4rem">Attached files are stored in this browser and embedded when you Save for sync. Keep them small.</div>'
        + '<div id="c-file-list">' + renderEntries(files, fileLabel, 'file') + '</div>'
      + '</div>'
      + '</div>';
  }
  function srcLabel(s){ return esc((s.title || '(untitled source)') + (s.author ? ' — ' + s.author : '') + (s.year ? ' (' + s.year + ')' : '')); }
  function sugLabel(s){ return esc((s.field || '?') + ': ' + (s.text || '')); }
  function fileLabel(f){ return esc(f.name + ' (' + Math.round((f.size || 0) / 1024) + ' KB)'); }
  function renderEntries(arr, labeller, kind){
    if (!arr.length) return '<div style="font-size:.8rem;color:#999">None yet.</div>';
    return arr.map(function(e,i){ return '<div class="entry"><span>' + labeller(e) + '</span><span class="x" data-kind="' + kind + '" data-i="' + i + '">&times;</span></div>'; }).join('');
  }

  function wireCapture(io){
    var id = io.id;
    detailEl.querySelectorAll('.cap .tabs button').forEach(function(b){
      b.addEventListener('click', function(){
        detailEl.querySelectorAll('.cap .tabs button').forEach(function(x){ x.classList.remove('on'); });
        detailEl.querySelectorAll('.cap .pane').forEach(function(x){ x.classList.remove('on'); });
        b.classList.add('on');
        var p = detailEl.querySelector('.cap .pane[data-p="' + b.dataset.t + '"]'); if (p) p.classList.add('on');
      });
    });
    detailEl.querySelector('#c-note-save').addEventListener('click', function(){
      var v = detailEl.querySelector('#c-note').value;
      if (v.trim()) { localStorage.setItem(kNote(id), v); } else { localStorage.removeItem(kNote(id)); }
      updateCount();
      detailEl.querySelector('#c-note-flag').textContent = 'Saved';
      setTimeout(function(){ var f = detailEl.querySelector('#c-note-flag'); if (f) f.textContent = ''; }, 1500);
    });
    detailEl.querySelector('#c-src-add').addEventListener('click', function(){
      var t = detailEl.querySelector('#c-src-title').value.trim();
      if (!t) return;
      var arr = getArr(kSrc(id));
      arr.push({ title: t, author: detailEl.querySelector('#c-src-author').value.trim(), year: detailEl.querySelector('#c-src-year').value.trim(), url: detailEl.querySelector('#c-src-url').value.trim() });
      setArr(kSrc(id), arr); show(id);
    });
    detailEl.querySelector('#c-sug-add').addEventListener('click', function(){
      var field = detailEl.querySelector('#c-sug-field').value.trim();
      var text = detailEl.querySelector('#c-sug-text').value.trim();
      if (!field || !text) return;
      var arr = getArr(kSug(id)); arr.push({ field: field, text: text }); setArr(kSug(id), arr); show(id);
    });
    detailEl.querySelector('#c-file-input').addEventListener('change', function(){
      var fl = this.files; if (!fl || !fl.length) return;
      var arr = getArr(kFile(id)); var pending = fl.length;
      Array.prototype.forEach.call(fl, function(file){
        var reader = new FileReader();
        reader.onload = function(){
          arr.push({ name: file.name, type: file.type, size: file.size, data: reader.result });
          pending--; if (pending === 0) { setArr(kFile(id), arr); show(id); }
        };
        reader.readAsDataURL(file);
      });
    });
    detailEl.querySelectorAll('.cap .entry .x').forEach(function(x){
      x.addEventListener('click', function(){
        var kind = x.dataset.kind, i = parseInt(x.dataset.i, 10);
        var key = kind === 'src' ? kSrc(id) : (kind === 'sug' ? kSug(id) : kFile(id));
        var arr = getArr(key); arr.splice(i, 1); setArr(key, arr); show(id);
      });
    });
  }

  function collectChanges(){
    var out = { notes: [], sources: [], metadata_suggestions: [], files: [] };
    for (var i = 0; i < localStorage.length; i++){
      var k = localStorage.key(i);
      if (k.indexOf(PFX) !== 0) continue;
      var parts = k.substring(PFX.length).split(':');
      var kind = parts[0], ioId = parseInt(parts[1], 10);
      var io = byId[ioId] || {}, slug = io.slug || '';
      if (kind === 'note') { var t = localStorage.getItem(k) || ''; if (t.trim()) out.notes.push({ io_id: ioId, slug: slug, text: t }); }
      else if (kind === 'src') { getArr(k).forEach(function(s){ out.sources.push(Object.assign({ io_id: ioId, slug: slug }, s)); }); }
      else if (kind === 'sug') { getArr(k).forEach(function(s){ out.metadata_suggestions.push(Object.assign({ io_id: ioId, slug: slug }, s)); }); }
      else if (kind === 'file') { getArr(k).forEach(function(f){ out.files.push(Object.assign({ io_id: ioId, slug: slug }, f)); }); }
    }
    return out;
  }
  function countChanges(c){ return c.notes.length + c.sources.length + c.metadata_suggestions.length + c.files.length; }
  function updateCount(){
    var el = document.getElementById('synccount'); if (!el) return;
    var n = countChanges(collectChanges()); el.textContent = n + (n === 1 ? ' change' : ' changes');
  }

  function initSync(){
    if (!EDITABLE) return;
    var bar = document.getElementById('syncbar'); if (bar) bar.style.display = 'flex';
    updateCount();
    var save = document.getElementById('syncsave');
    if (save) save.addEventListener('click', function(){
      var changes = collectChanges();
      if (countChanges(changes) === 0) { alert('You have no offline changes to save yet.'); return; }
      var payload = {
        heratio_sync: 1,
        package_id: SYNC.package_id, sync_token: SYNC.sync_token,
        group_source: SYNC.group_source, group_ref: SYNC.group_ref,
        title: SYNC.title, generated_at: new Date().toISOString(),
        changes: changes
      };
      var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'researcher-sync-' + (SYNC.package_id || 0) + '.json';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
    var reset = document.getElementById('syncreset');
    if (reset) reset.addEventListener('click', function(){
      if (!confirm('Clear all your offline changes in this package? This cannot be undone.')) return;
      var keys = [];
      for (var i = 0; i < localStorage.length; i++){ var k = localStorage.key(i); if (k.indexOf(PFX) === 0) keys.push(k); }
      keys.forEach(function(k){ localStorage.removeItem(k); });
      updateCount(); if (current) show(current);
    });
  }
  initSync();
})();
</script>
</body>
</html>
HTML;
        file_put_contents($workDir.'/index.html', $html);
    }

    private function zipDir(string $srcDir, string $outPath): void
    {
        if (file_exists($outPath)) {
            @unlink($outPath);
        }
        $zip = new \ZipArchive;
        if ($zip->open($outPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open zip for writing: '.$outPath);
        }
        $base = realpath($srcDir);
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            if ($f->isDir()) {
                continue;
            }
            $abs = $f->getRealPath();
            $rel = ltrim(substr($abs, strlen($base)), '/\\');
            $zip->addFile($abs, $rel);
        }
        $zip->close();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $f) {
            if ($f->isDir()) {
                @rmdir($f->getRealPath());
            } else {
                @unlink($f->getRealPath());
            }
        }
        @rmdir($dir);
    }

    /** Recursive byte size of a directory (for folder-destination exports). */
    private function dirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $total = 0;
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($rii as $f) {
            if ($f->isFile()) {
                $total += (int) $f->getSize();
            }
        }

        return $total;
    }
}
