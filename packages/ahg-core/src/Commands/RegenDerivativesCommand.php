<?php

/**
 * RegenDerivativesCommand — regenerate thumbnails / reference copies for masters.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgMediaProcessing\Services\DerivativeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenDerivativesCommand extends Command
{
    protected $signature = 'ahg:regen-derivatives
        {--slug= : Information object slug}
        {--type=all : Derivative type (all, thumbnail, reference)}
        {--force : Regenerate even if derivatives exist}
        {--only-externals : Only process external digital objects}
        {--json : Output results as JSON}';

    protected $description = 'Regenerate image derivatives';

    public function handle(DerivativeService $derivatives): int
    {
        $masters = $this->collectMasters();
        $this->info("regenerating derivatives for {$masters->count()} masters");

        $results = [];
        $ok = 0; $failed = 0;
        foreach ($masters as $m) {
            try {
                $r = $derivatives->regenerateDerivatives((int) $m->id);
                $results[] = ['id' => $m->id, 'status' => 'ok', 'detail' => $r];
                $ok++;
            } catch (\Throwable $e) {
                $results[] = ['id' => $m->id, 'status' => 'fail', 'error' => $e->getMessage()];
                $failed++;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $ok, 'failed' => $failed, 'results' => $results], JSON_PRETTY_PRINT));
        } else {
            $this->info("ok={$ok} failed={$failed}");
        }
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function collectMasters(): \Illuminate\Support\Collection
    {
        $q = DB::table('digital_object as do')->where('do.usage_id', 1); // 1 = master
        if ($slug = $this->option('slug')) {
            $ioId = DB::table('slug')->where('slug', $slug)->value('object_id');
            if (! $ioId) {
                $this->error("slug not found: {$slug}");
                return collect();
            }
            $q->where('do.object_id', $ioId);
        }
        if ($this->option('only-externals')) {
            $q->whereNotNull('do.path')->where('do.path', 'like', 'http%');
        }
        return $q->select('do.id', 'do.object_id', 'do.path', 'do.name')->get();
    }
}
