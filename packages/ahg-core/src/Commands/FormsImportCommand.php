<?php

namespace AhgCore\Commands;

use AhgForms\Services\FormService;
use Illuminate\Console\Command;

class FormsImportCommand extends Command
{
    protected $signature = 'ahg:forms-import
        {--file= : Input JSON file (from ahg:forms-export)}
        {--rename : Rename to "<original> (imported)" to avoid name collisions}';

    protected $description = 'Import form configurations from a JSON bundle';

    public function handle(FormService $svc): int
    {
        $file = $this->option('file');
        if (! $file || ! is_readable($file)) {
            $this->error("--file= is required and must be readable");
            return self::FAILURE;
        }
        $bundle = json_decode((string) file_get_contents($file), true);
        if (! is_array($bundle) || empty($bundle['templates'])) {
            $this->error('bundle has no templates');
            return self::FAILURE;
        }
        $imported = 0;
        foreach ($bundle['templates'] as $t) {
            $name = $this->option('rename') ? (($t['name'] ?? 'Untitled') . ' (imported)') : null;
            $svc->importTemplate($t, $name);
            $imported++;
        }
        $this->info("imported {$imported} templates");
        return self::SUCCESS;
    }
}
