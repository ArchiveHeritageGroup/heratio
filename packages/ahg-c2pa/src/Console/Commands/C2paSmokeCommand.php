<?php
/**
 * Heratio - bench command: build + sign a C2PA manifest for a fake AI
 * suggestion against an IO, dump it, write a sidecar.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Console\Commands;

use AhgC2pa\Services\C2paService;
use Illuminate\Console\Command;
use Throwable;

final class C2paSmokeCommand extends Command
{
    protected $signature = 'c2pa:smoke
        {ioId : Archival description (information_object) id this fake AI output is attached to}
        {output-text? : Body of the simulated AI output (default: a canned suggestion)}
        {--action=ai-generated : One of ai-generated, ai-assisted}
        {--model=qwen3:14b : Model id to embed in the manifest}
        {--model-version= : Model version string}
        {--no-write : Skip sidecar write; print to stdout only}
        {--sidecar-dir= : Directory for the sidecar file (default: storage/app/c2pa-smoke)}';

    protected $description = 'Build, sign and dump a C2PA manifest for a hypothetical AI suggestion (manual deployment check).';

    public function handle(C2paService $c2pa): int
    {
        $ioId = (int) $this->argument('ioId');
        $action = (string) $this->option('action');
        $modelId = (string) $this->option('model');
        $modelVersion = $this->option('model-version') !== null ? (string) $this->option('model-version') : null;
        $output = (string) ($this->argument('output-text') ?? 'Smoke-test AI suggestion: this archival description appears to describe a 1920s mining permit.');

        try {
            $manifest = $c2pa->manifestForAiSuggestion(
                informationObjectId: $ioId,
                action: $action,
                modelId: $modelId,
                modelVersion: $modelVersion,
                output: $output,
            );
            $signed = $c2pa->signManifest($manifest);
        } catch (Throwable $e) {
            $this->error('c2pa:smoke: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('--- Signed C2PA manifest ---');
        $this->line(json_encode($signed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($this->option('no-write')) {
            return self::SUCCESS;
        }

        $dir = $this->option('sidecar-dir') ?? (function_exists('storage_path') ? storage_path('app/c2pa-smoke') : sys_get_temp_dir() . '/c2pa-smoke');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error("c2pa:smoke: cannot create sidecar dir {$dir}");
            return self::FAILURE;
        }

        $artefact = $dir . '/io-' . $ioId . '-' . time() . '.txt';
        if (@file_put_contents($artefact, $output) === false) {
            $this->error("c2pa:smoke: cannot write artefact {$artefact}");
            return self::FAILURE;
        }

        try {
            $sidecar = $c2pa->sidecar($signed, $artefact);
        } catch (Throwable $e) {
            $this->error('c2pa:smoke: sidecar write failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $rowId = $c2pa->persist(
            signedManifest: $signed,
            informationObjectId: $ioId,
            action: $action,
            modelId: $modelId,
            modelVersion: $modelVersion,
            sidecarPath: $sidecar,
        );

        $this->info('--- Artefacts written ---');
        $this->line('artefact : ' . $artefact);
        $this->line('sidecar  : ' . $sidecar);
        $this->line('row id   : ' . ($rowId ?? '(not persisted - table missing or DB unavailable)'));

        return self::SUCCESS;
    }
}
