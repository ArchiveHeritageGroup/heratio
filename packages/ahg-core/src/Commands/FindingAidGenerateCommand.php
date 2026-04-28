<?php

/**
 * FindingAidGenerateCommand — generate HTML/PDF finding aid for an IO subtree.
 *
 * Walks the IO + lft/rgt descendants, renders an HTML finding aid, and
 * writes it to {uploads_path}/findingaid/{id}.{ext}. Optional pdf output
 * shells to wkhtmltopdf if available, else writes HTML.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FindingAidGenerateCommand extends Command
{
    protected $signature = 'ahg:finding-aid-generate
        {--slug= : Information object slug}
        {--all : Generate for all top-level descriptions}
        {--format=pdf : Output format (pdf, html, rtf)}';

    protected $description = 'Generate finding aids';

    public function handle(): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['pdf', 'html', 'rtf'], true)) {
            $this->error("invalid --format {$format}");
            return self::FAILURE;
        }

        $targets = collect();
        if ($slug = $this->option('slug')) {
            $row = DB::table('information_object as i')
                ->join('slug as s', 's.object_id', '=', 'i.id')
                ->where('s.slug', $slug)->select('i.id', 'i.lft', 'i.rgt')->first();
            if (! $row) { $this->error("slug not found: {$slug}"); return self::FAILURE; }
            $targets = collect([$row]);
        } elseif ($this->option('all')) {
            $targets = DB::table('information_object')->whereNull('parent_id')->select('id', 'lft', 'rgt')->get();
        } else {
            $this->error('Specify --slug or --all');
            return self::FAILURE;
        }

        $base = rtrim((string) config('heratio.uploads_path', storage_path('app/uploads')), '/') . '/findingaid';
        if (! is_dir($base)) @mkdir($base, 0775, true);

        $generated = 0;
        foreach ($targets as $t) {
            $html = $this->renderHtml((int) $t->id, (int) $t->lft, (int) $t->rgt);
            $out = $base . '/' . $t->id . '.' . ($format === 'pdf' ? 'pdf' : ($format === 'rtf' ? 'rtf' : 'html'));
            if ($format === 'pdf') {
                $tmp = tempnam(sys_get_temp_dir(), 'fa') . '.html';
                file_put_contents($tmp, $html);
                $bin = trim((string) shell_exec('which wkhtmltopdf'));
                if ($bin) {
                    @shell_exec(escapeshellcmd($bin) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($out) . ' 2>&1');
                } else {
                    file_put_contents($out, $html);
                    $out .= ' (html fallback — wkhtmltopdf not installed)';
                }
                @unlink($tmp);
            } else {
                file_put_contents($out, $html);
            }
            if (Schema::hasTable('finding_aid')) {
                DB::table('finding_aid')->updateOrInsert(
                    ['information_object_id' => $t->id],
                    ['name' => basename($out), 'description' => 'auto-generated', 'updated_at' => now()],
                );
            }
            $generated++;
            $this->line("  -> {$out}");
        }
        $this->info("generated={$generated}");
        return self::SUCCESS;
    }

    protected function renderHtml(int $rootId, int $lft, int $rgt): string
    {
        $rows = DB::table('information_object as i')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'i.id')->where('i18n.culture', '=', 'en');
            })
            ->whereBetween('i.lft', [$lft, $rgt])
            ->orderBy('i.lft')
            ->select('i.id', 'i.parent_id', 'i.identifier', 'i.lft', 'i.rgt',
                'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium')
            ->get();

        $rootTitle = optional($rows->firstWhere('id', $rootId))->title ?? '(untitled)';
        $html = "<!doctype html><html><head><meta charset='utf-8'><title>" . e($rootTitle) . "</title>";
        $html .= "<style>body{font:12pt serif;margin:2cm} h1{font-size:18pt} .item{margin:.4em 0 .4em 1em;border-left:1px solid #ccc;padding-left:.5em} .id{color:#666;font-size:.85em}</style></head><body>";
        $html .= "<h1>" . e($rootTitle) . "</h1>";
        foreach ($rows as $r) {
            $depth = max(0, ($r->lft - $lft) / 2);
            $html .= "<div class='item' style='margin-left:" . ($depth * 0.8) . "em'>";
            $html .= "<strong>" . e($r->title ?: '(untitled)') . "</strong>";
            if ($r->identifier) $html .= " <span class='id'>[" . e($r->identifier) . "]</span>";
            if ($r->extent_and_medium) $html .= "<div><em>extent:</em> " . e($r->extent_and_medium) . "</div>";
            if ($r->scope_and_content) $html .= "<p>" . e($r->scope_and_content) . "</p>";
            $html .= "</div>";
        }
        return $html . "</body></html>";
    }
}
