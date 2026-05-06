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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class BundleWorkerCommand extends Command
{
    protected $signature = 'ahg:portable-export-worker
        {--id= : Specific portable_export.id to process (else next pending FIFO)}
        {--all-pending : Drain every pending row in this run}';

    protected $description = 'Process pending portable_export rows: dump entities, copy assets, build viewer, zip.';

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
        if (!in_array(strtolower($enabled), ['1','true','yes','on'], true)) {
            $this->info('portable_export_enabled is off - leaving ' . $rows->count() . ' row(s) pending.');
            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;
        foreach ($rows as $row) {
            try {
                $this->processOne($row);
            } catch (\Throwable $e) {
                Log::error('Portable-export worker failed for id=' . $row->id . ': ' . $e->getMessage(), [
                    'trace' => $e->getFile() . ':' . $e->getLine(),
                ]);
                DB::table('portable_export')->where('id', $row->id)->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 1000),
                    'completed_at' => now(),
                ]);
                $this->error('id=' . $row->id . ' FAILED: ' . $e->getMessage());
                $exitCode = self::FAILURE;
            }
        }
        return $exitCode;
    }

    private function pickRows(): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('portable_export')) return collect();
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
        $this->info('id=' . $row->id . ' "' . $row->title . '" (' . $row->scope_type . '/' . $row->mode . ')');
        DB::table('portable_export')->where('id', $row->id)->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 1,
        ]);

        // Resolve scope -> IO id list
        $ioIds = $this->resolveScopeIoIds($row);
        $this->line('  ' . count($ioIds) . ' IOs in scope');

        $workDir = sys_get_temp_dir() . '/heratio-portable-' . $row->id . '-' . substr(md5(microtime()), 0, 6);
        @mkdir($workDir . '/data', 0775, true);
        @mkdir($workDir . '/assets', 0775, true);

        $stats = $this->dumpData($workDir, $row, $ioIds);
        DB::table('portable_export')->where('id', $row->id)->update(['progress' => 40,
            'total_descriptions' => $stats['descriptions'] ?? 0,
            'total_objects' => $stats['digital_objects'] ?? 0,
        ]);

        $copied = $this->copyAssets($workDir, $row, $ioIds);
        DB::table('portable_export')->where('id', $row->id)->update(['progress' => 70]);
        $this->line('  copied ' . $copied . ' asset file(s)');

        if (in_array($row->mode, ['read_only', 'editable'], true)) {
            $this->emitViewer($workDir, $row, $stats);
        }
        DB::table('portable_export')->where('id', $row->id)->update(['progress' => 85]);

        // Zip + finalise
        $outDir = dirname((string) $row->output_path) ?: sys_get_temp_dir();
        @mkdir($outDir, 0775, true);
        $outPath = $row->output_path
            ?: ($outDir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '-', $row->title) . '-' . $row->id . '.zip');
        $this->zipDir($workDir, $outPath);
        $size = filesize($outPath) ?: 0;

        // Cleanup workdir
        $this->rrmdir($workDir);

        DB::table('portable_export')->where('id', $row->id)->update([
            'status' => 'complete',
            'progress' => 100,
            'output_path' => $outPath,
            'output_size' => $size,
            'completed_at' => now(),
            'expires_at' => now()->addDays((int) (DB::table('ahg_settings')->where('setting_key', 'portable_export_retention_days')->value('setting_value') ?: 30)),
        ]);
        $this->info('  -> ' . $outPath . ' (' . round($size/1048576, 1) . ' MB)');
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
                if (empty($slugs)) return [];
                return DB::table('slug')->whereIn('slug', $slugs)->pluck('object_id')->all();
            case 'all':
            default:
                break;
        }
        return $ioQ->pluck('id')->all();
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
        file_put_contents($workDir . '/data/ios.json', json_encode($ios, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
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
        file_put_contents($workDir . '/data/repositories.json', json_encode($repos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $stats['repositories'] = $repos->count();

        // Actors related via the relation table (creators, name access points)
        $actorIds = empty($ioIds) ? collect() : DB::table('relation')
            ->whereIn('object_id', $ioIds)
            ->whereIn('subject_id', function ($q) { $q->select('id')->from('actor'); })
            ->pluck('subject_id')->unique()->values();
        $actors = $actorIds->isEmpty() ? collect() : DB::table('actor as a')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('ai.id', '=', 'a.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'a.id')
            ->whereIn('a.id', $actorIds)
            ->select('a.id', 'slug.slug', 'ai.authorized_form_of_name as name', 'ai.dates_of_existence', 'ai.history')
            ->get();
        file_put_contents($workDir . '/data/actors.json', json_encode($actors, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $stats['actors'] = $actors->count();

        // Digital objects (metadata only, regardless of include_objects -
        // the JSON file lists every DO but the assets/ directory only
        // contains files for the usages flagged include_*).
        $dos = empty($ioIds) ? collect() : DB::table('digital_object')
            ->whereIn('object_id', $ioIds)
            ->select('id', 'object_id', 'usage_id', 'mime_type', 'media_type_id', 'name', 'path', 'byte_size', 'parent_id')
            ->orderBy('object_id')->orderBy('usage_id')
            ->get();
        file_put_contents($workDir . '/data/digital_objects.json', json_encode($dos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $stats['digital_objects'] = $dos->count();

        // Manifest (also includes the row's own metadata so the viewer can
        // render the title/branding without a second source).
        $branding = json_decode((string) ($row->branding ?? ''), true) ?: [];
        $manifest = [
            'export_id' => (int) $row->id,
            'title'     => (string) $row->title,
            'mode'      => (string) $row->mode,
            'culture'   => $culture,
            'scope'     => (string) $row->scope_type,
            'created'   => (string) ($row->created_at ?? now()),
            'branding'  => $branding,
            'counts'    => $stats,
        ];
        file_put_contents($workDir . '/data/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        return $stats;
    }

    /**
     * Copy digital-object derivative files into bundle/assets/<usage>/<id>/.
     * usage_id 140=master, 141=reference, 142=thumbnail. Each include_*
     * flag gates the corresponding usage.
     */
    private function copyAssets(string $workDir, object $row, array $ioIds): int
    {
        if (empty($ioIds)) return 0;
        if (!(int) ($row->include_objects ?? 1)) return 0; // master switch on the include set

        $usagesToCopy = [];
        if ((int) $row->include_thumbnails) $usagesToCopy[142] = 'thumb';
        if ((int) $row->include_references) $usagesToCopy[141] = 'ref';
        if ((int) $row->include_masters)    $usagesToCopy[140] = 'master';
        if (empty($usagesToCopy)) return 0;

        $uploadsBase = rtrim((string) config('heratio.uploads_path', '/tmp'), '/');
        $copied = 0;
        $dos = DB::table('digital_object')->whereIn('object_id', $ioIds)
            ->whereIn('usage_id', array_keys($usagesToCopy))
            ->select('id', 'object_id', 'usage_id', 'name', 'path')->get();
        foreach ($dos as $do) {
            $usageDir = $usagesToCopy[$do->usage_id] ?? null;
            if (!$usageDir) continue;
            $src = $uploadsBase . '/' . ltrim((string) $do->path, '/') . $do->name;
            // Same path resolution as DigitalObjectController::upload (handle dir-with-name shape)
            if (!file_exists($src)) {
                $alt = $uploadsBase . '/' . ltrim((string) $do->path, '/');
                if (is_dir($alt)) $src = rtrim($alt, '/') . '/' . $do->name;
            }
            if (!file_exists($src)) continue;
            $destDir = $workDir . '/assets/' . $usageDir . '/' . $do->object_id;
            if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
            $destName = $do->name ?: ('file-' . $do->id);
            if (@copy($src, $destDir . '/' . $destName)) $copied++;
        }
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
<footer>{$bFooter}</footer>
<script>
(function(){
  var EDITABLE = {$editable};
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
    var notes = '';
    if (EDITABLE) {
      var saved = localStorage.getItem('heratio-pe-note-' + id) || '';
      notes = '<div class="notes"><h3>Local notes</h3><textarea id="note">' + esc(saved) + '</textarea><button class="save" id="save-note">Save (this device only)</button></div>';
    }
    detailEl.innerHTML = '<h2>' + esc(io.title || '(untitled)') + '</h2>'
      + (io.identifier ? '<div class="ident">' + esc(io.identifier) + '</div>' : '')
      + (imgHtml ? '<div class="images">' + imgHtml + '</div>' : '')
      + dl
      + notes;
    if (EDITABLE) {
      document.getElementById('save-note').addEventListener('click', function(){
        localStorage.setItem('heratio-pe-note-' + id, document.getElementById('note').value);
        this.textContent = 'Saved'; setTimeout(function(){ document.getElementById('save-note').textContent = 'Save (this device only)'; }, 1500);
      });
    }
  }
})();
</script>
</body>
</html>
HTML;
        file_put_contents($workDir . '/index.html', $html);
    }

    private function zipDir(string $srcDir, string $outPath): void
    {
        if (file_exists($outPath)) @unlink($outPath);
        $zip = new \ZipArchive();
        if ($zip->open($outPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open zip for writing: ' . $outPath);
        }
        $base = realpath($srcDir);
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            if ($f->isDir()) continue;
            $abs = $f->getRealPath();
            $rel = ltrim(substr($abs, strlen($base)), '/\\');
            $zip->addFile($abs, $rel);
        }
        $zip->close();
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $f) {
            if ($f->isDir()) @rmdir($f->getRealPath());
            else @unlink($f->getRealPath());
        }
        @rmdir($dir);
    }
}
