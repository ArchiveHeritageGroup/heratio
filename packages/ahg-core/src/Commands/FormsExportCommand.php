<?php

namespace AhgCore\Commands;

use AhgForms\Services\FormService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FormsExportCommand extends Command
{
    protected $signature = 'ahg:forms-export
        {--template-id= : Export a single template by id}
        {--type= : Filter by template type}
        {--output=storage/forms-export.json : Output JSON file path}';

    protected $description = 'Export form configurations (templates + fields) as JSON';

    public function handle(FormService $svc): int
    {
        $out = $this->option('output');
        $bundle = ['exported_at' => now()->toIso8601String(), 'templates' => []];

        if ($id = $this->option('template-id')) {
            $bundle['templates'][] = $svc->exportTemplate((int) $id);
        } else {
            $templates = $svc->getTemplates($this->option('type'));
            foreach ($templates as $t) $bundle['templates'][] = $svc->exportTemplate((int) $t->id);
        }

        $path = base_path((string) $out);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("wrote " . count($bundle['templates']) . " templates → {$path}");
        return self::SUCCESS;
    }
}
