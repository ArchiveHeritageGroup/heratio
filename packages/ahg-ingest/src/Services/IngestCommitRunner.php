<?php

/**
 * IngestCommitRunner — Heratio ingest
 *
 * Walks the ingest_row rows for a given session, applies each row's
 * mapped data to create an information_object (and optionally attach a
 * digital_object), tracks progress via ingest_job, and runs the OAIS
 * packager when session flags request SIP/AIP/DIP output.
 *
 * Closes the long-standing gap where the wizard's Commit step had no
 * actual runner — clicking "Approve → Commit" previously just flipped
 * the session status to 'commit' and sat waiting for a job nobody
 * created. This is that job.
 *
 * Invocation:
 *   - Queue: IngestCommitJob::dispatch($sessionId)  (future)
 *   - CLI:   php artisan ahg:ingest-commit <session_id>
 *   - Web:   POST from commit.blade.php "Start commit" button
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgIngest\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngestCommitRunner
{
    public function __construct(
        protected IngestService $ingest,
        protected OaisPackagerService $packager,
    ) {}

    /**
     * Run the commit for one session. Idempotent on already-created rows
     * (skips rows where created_atom_id is already set).
     *
     * @return array{job_id:int,total:int,processed:int,created:int,errors:int}
     */
    public function run(int $sessionId): array
    {
        $session = DB::table('ingest_session')->where('id', $sessionId)->first();
        if (!$session) {
            throw new \RuntimeException("Ingest session {$sessionId} not found");
        }

        // Find an open job or create a fresh one.
        $job = DB::table('ingest_job')
            ->where('session_id', $sessionId)
            ->whereIn('status', ['queued', 'running'])
            ->orderByDesc('id')
            ->first();
        if (!$job) {
            $jobId = DB::table('ingest_job')->insertGetId([
                'session_id' => $sessionId,
                'status' => 'running',
                'total_rows' => 0,
                'processed_rows' => 0,
                'created_records' => 0,
                'created_dos' => 0,
                'error_count' => 0,
                'started_at' => now(),
                'created_at' => now(),
            ]);
        } else {
            $jobId = (int) $job->id;
            DB::table('ingest_job')->where('id', $jobId)->update([
                'status' => 'running',
                'started_at' => $job->started_at ?: now(),
            ]);
        }

        $rows = DB::table('ingest_row')
            ->where('session_id', $sessionId)
            ->where('is_valid', 1)
            ->where('is_excluded', 0)
            ->whereNull('created_atom_id')
            ->orderBy('row_number')
            ->get();

        $total = $rows->count();
        DB::table('ingest_job')->where('id', $jobId)->update(['total_rows' => $total]);

        $processed = 0;
        $created = 0;
        $createdDos = 0;
        $errors = [];
        $createdIoIds = [];

        foreach ($rows as $row) {
            try {
                $result = $this->commitOneRow($row, $session);
                if ($result['io_id']) {
                    $createdIoIds[] = $result['io_id'];
                    $created++;
                }
                if ($result['do_id']) {
                    $createdDos++;
                }
                DB::table('ingest_row')->where('id', $row->id)->update([
                    'created_atom_id' => $result['io_id'],
                    'created_do_id' => $result['do_id'],
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $row->row_number, 'error' => substr($e->getMessage(), 0, 500)];
                Log::warning("[ahg-ingest] commit row {$row->row_number} failed: " . $e->getMessage());
            }

            if ($processed % 10 === 0) {
                DB::table('ingest_job')->where('id', $jobId)->update([
                    'processed_rows' => $processed,
                    'created_records' => $created,
                    'created_dos' => $createdDos,
                    'error_count' => count($errors),
                    'error_log' => json_encode($errors),
                ]);
            }
        }

        // Packaging stage — run the OAIS packager for every new IO when the
        // session has any output_generate_* flag set.
        $packageBuild = [
            'sip' => !empty($session->output_generate_sip),
            'aip' => !empty($session->output_generate_aip),
            'dip' => !empty($session->output_generate_dip),
        ];
        if (array_filter($packageBuild) && !empty($createdIoIds)) {
            foreach ($createdIoIds as $ioId) {
                foreach ($packageBuild as $type => $enabled) {
                    if (!$enabled) { continue; }
                    try {
                        $this->packager->buildPackage($ioId, $type, [
                            'originator' => $session->title ?: 'heratio-ingest',
                            'export_path' => $session->{'output_' . $type . '_path'} ?: null,
                            'created_by' => $session->user_id ?? null,
                        ]);
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'row' => 'packaging',
                            'error' => "{$type} build failed for IO {$ioId}: " . substr($e->getMessage(), 0, 400),
                        ];
                    }
                }
            }
        }

        $finalStatus = count($errors) === 0 ? 'completed' : 'completed_with_errors';
        DB::table('ingest_job')->where('id', $jobId)->update([
            'status' => $finalStatus,
            'total_rows' => $total,
            'processed_rows' => $processed,
            'created_records' => $created,
            'created_dos' => $createdDos,
            'error_count' => count($errors),
            'error_log' => json_encode($errors),
            'completed_at' => now(),
        ]);
        DB::table('ingest_session')->where('id', $sessionId)->update([
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        return [
            'job_id' => $jobId,
            'total' => $total,
            'processed' => $processed,
            'created' => $created,
            'errors' => count($errors),
        ];
    }

    // ----------------------------------------------------------------
    // Per-row commit
    // ----------------------------------------------------------------

    protected function commitOneRow(object $row, object $session): array
    {
        $data = $row->enriched_data ?: $row->data;
        $fields = is_string($data) ? (json_decode($data, true) ?: []) : (array) $data;

        // Normalise common field names — the wizard exports either
        // camelCase (ingest wizard's own) or snake_case (DB style).
        $title = $fields['title'] ?? $fields['Title'] ?? $row->title ?? 'Untitled';
        $identifier = $fields['identifier'] ?? $fields['Identifier'] ?? null;
        $lodLabel = $fields['levelOfDescription'] ?? $fields['level_of_description'] ?? $row->level_of_description ?? null;
        $scope = $fields['scopeAndContent'] ?? $fields['scope_and_content'] ?? null;
        $extent = $fields['extentAndMedium'] ?? $fields['extent_and_medium'] ?? null;
        $digitalObjectPath = $row->digital_object_path ?: ($fields['digitalObjectPath'] ?? $fields['digital_object_path'] ?? null);

        $meta = array_filter([
            'title' => $title,
            'identifier' => $identifier,
            'parent_id' => $session->parent_id ?? null,
            'repository_id' => $session->repository_id ?? null,
            'level_of_description_id' => $lodLabel ? $this->resolveLod($lodLabel) : null,
            'scope_and_content' => $scope,
            'extent_and_medium' => $extent,
            'source_standard' => $session->standard ?? null,
        ], fn($v) => $v !== null && $v !== '');

        // Path supplied AND file exists → create IO + attach DO via the same
        // streaming entry point the scanner uses.
        if ($digitalObjectPath && is_file($digitalObjectPath)) {
            $result = $this->ingest->ingestFile(
                (int) $session->id,
                $digitalObjectPath,
                $meta,
                basename($digitalObjectPath)
            );
            // #109: per-row AI orchestration. The configure form persists 8
            // process_* gates onto ingest_session, but pre-this-release the
            // runner never read them. Each gate fires its corresponding AI
            // step right after IO+DO creation. Steps log + best-effort
            // fail-soft - a DOWN GPU pool / missing model must not break
            // the surrounding ingest commit (the row is already saved).
            $this->runAiSteps($session, (int) $result['io_id'], (int) ($result['do_id'] ?? 0), $digitalObjectPath);
            return ['io_id' => $result['io_id'], 'do_id' => $result['do_id']];
        }

        // No digital object — create the IO only.
        $ioId = \AhgInformationObjectManage\Services\InformationObjectService::create($meta, 'en');
        // #109: text-only AI steps still run when there's no DO (NER /
        // summarize / spellcheck / translate operate on the IO's text
        // fields, not on a binary file).
        $this->runAiSteps($session, $ioId, 0, null);
        return ['io_id' => $ioId, 'do_id' => null];
    }

    /**
     * #109 AI-step runtime orchestration. Each per-row step gates on the
     * corresponding ingest_session.process_* column + the global enable
     * setting (which the configure form already pre-fills the column
     * from). Steps that need a binary file are skipped when $digitalObjectPath
     * is null (text-only ingest); text-field steps still run.
     *
     * Failures are non-fatal + logged. The surrounding ingest commit
     * succeeds regardless - operator can re-run failed steps later via
     * the relevant AI service's own re-process command.
     */
    protected function runAiSteps(object $session, int $ioId, int $doId, ?string $filePath): void
    {
        $log = static function (string $step, string $msg, array $ctx = []) use ($ioId, $doId): void {
            \Illuminate\Support\Facades\Log::info('[ingest-ai-step] ' . $step . ': ' . $msg,
                array_merge(['io_id' => $ioId, 'do_id' => $doId], $ctx));
        };

        $tryStep = static function (string $name, callable $fn) use ($log): void {
            try {
                $fn();
            } catch (\Throwable $e) {
                $log($name, 'failed: ' . $e->getMessage());
            }
        };

        // Each gate is a tinyint column. Cast defensively to int because the
        // session row may carry boolean / string values depending on driver.
        $on = static fn ($v) => $v !== null && (int) $v === 1;

        if ($filePath && $on($session->process_virus_scan ?? null)) {
            $tryStep('virus_scan', function () use ($filePath, $log) {
                if (!class_exists(\AhgAiServices\Services\VirusScanService::class)) {
                    $log('virus_scan', 'service class not present, skipping');
                    return;
                }
                $svc = app(\AhgAiServices\Services\VirusScanService::class);
                if (method_exists($svc, 'scanFile')) {
                    $svc->scanFile($filePath);
                }
            });
        }

        if ($filePath && $on($session->process_ocr ?? null)) {
            $tryStep('ocr', function () use ($doId, $filePath, $log) {
                if (!class_exists(\AhgAiServices\Services\OcrService::class)) {
                    $log('ocr', 'service class not present, skipping'); return;
                }
                $svc = app(\AhgAiServices\Services\OcrService::class);
                if (method_exists($svc, 'enqueueForDigitalObject') && $doId > 0) {
                    $svc->enqueueForDigitalObject($doId);
                }
            });
        }

        if ($filePath && $on($session->process_format_id ?? null)) {
            $tryStep('format_id', function () use ($filePath, $log) {
                if (!class_exists(\AhgAiServices\Services\FormatIdService::class)) {
                    $log('format_id', 'service class not present, skipping'); return;
                }
                $svc = app(\AhgAiServices\Services\FormatIdService::class);
                if (method_exists($svc, 'identify')) { $svc->identify($filePath); }
            });
        }

        if ($filePath && $on($session->process_face_detect ?? null) && $doId > 0) {
            $tryStep('face_detect', function () use ($doId) {
                $svc = new \AhgCore\Services\FaceDetectionService();
                if ($svc->isEnabled()) {
                    $svc->detectAndStore($doId);
                }
            });
        }

        // Text-field steps: NER / Summarize / Spellcheck / Translate run on
        // IO text content regardless of whether a DO exists. Each consults
        // the relevant ahg-ai-services class through its existing service
        // interface - the runner's role is to fire the gate, not to know
        // the per-service implementation details.
        if ($on($session->process_ner ?? null)) {
            $tryStep('ner', function () use ($ioId, $log) {
                if (!class_exists(\AhgAiServices\Services\NerService::class)) {
                    $log('ner', 'NerService not present, skipping'); return;
                }
                $svc = app(\AhgAiServices\Services\NerService::class);
                if (method_exists($svc, 'enqueueForInformationObject')) {
                    $svc->enqueueForInformationObject($ioId);
                }
            });
        }

        if ($on($session->process_summarize ?? null)) {
            $tryStep('summarize', function () use ($ioId, $log) {
                if (!class_exists(\AhgAiServices\Services\SummarizerService::class)) {
                    $log('summarize', 'SummarizerService not present, skipping'); return;
                }
                $svc = app(\AhgAiServices\Services\SummarizerService::class);
                if (method_exists($svc, 'enqueueForInformationObject')) {
                    $svc->enqueueForInformationObject($ioId);
                }
            });
        }

        if ($on($session->process_spellcheck ?? null)) {
            $tryStep('spellcheck', function () use ($ioId, $log) {
                if (!class_exists(\AhgAiServices\Services\SpellcheckService::class)) {
                    $log('spellcheck', 'SpellcheckService not present, skipping'); return;
                }
                $svc = app(\AhgAiServices\Services\SpellcheckService::class);
                if (method_exists($svc, 'enqueueForInformationObject')) {
                    $svc->enqueueForInformationObject($ioId);
                }
            });
        }

        if ($on($session->process_translate ?? null)) {
            $tryStep('translate', function () use ($ioId, $session, $log) {
                if (!class_exists(\AhgAiServices\Services\TranslateService::class)) {
                    $log('translate', 'TranslateService not present, skipping'); return;
                }
                $svc = app(\AhgAiServices\Services\TranslateService::class);
                $targetLang = (string) ($session->process_translate_lang ?? 'af');
                if (method_exists($svc, 'enqueueForInformationObject')) {
                    $svc->enqueueForInformationObject($ioId, $targetLang);
                }
            });
        }
    }

    /**
     * Resolve a level-of-description label (e.g. "Item", "Series") to a
     * term_id. Prefers the canonical AtoM taxonomy (ids around 238–245).
     */
    protected function resolveLod(string $label): ?int
    {
        $row = DB::table('term as t')
            ->join('term_i18n as ti', 'ti.id', '=', 't.id')
            ->where('ti.name', $label)
            ->where('ti.culture', 'en')
            ->whereIn('t.id', [236, 238, 239, 240, 241, 242, 117, 249, 253, 311])
            ->orderBy('t.id')
            ->value('t.id');
        if ($row) { return (int) $row; }
        // Fallback: any term with that exact name.
        $row = DB::table('term_i18n')->where('name', $label)->where('culture', 'en')->value('id');
        return $row ? (int) $row : null;
    }
}
