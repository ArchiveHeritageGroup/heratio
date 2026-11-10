<?php

/**
 * PiiRedactionSuggester - turn PII scan findings into redaction regions on a
 * record's PDF (Eskom World Quality Day, 10 Nov 2026).
 *
 * Reads each page of the record's master PDF (OCR for a scanned page), runs
 * PiiScanService on it, finds where each confident finding sits on the page
 * and saves it as a privacy_visual_redaction region: source 'auto_pii',
 * status 'pending', label = the PII type. Pending regions are applied to what
 * visitors see straight away (RedactionRenderService::LIVE_STATUSES), so a
 * suggestion protects first; an archivist reviews, rejects or approves it in
 * the redaction editor. Re-running adds nothing already suggested.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgPrivacy\Services;

use AhgInformationObjectManage\Services\RedactionRenderService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class PiiRedactionSuggester
{
    /** Below this a finding is shown to a reviewer, not blacked out. */
    public const MIN_CONFIDENCE = 0.5;

    private const SCRIPT = __DIR__.'/../../python/pii_regions.py';

    public function __construct(private RedactionRenderService $render)
    {
    }

    /**
     * @return array{added:int, findings:int, missing:list<array>, pages:int, ocr:bool, error?:string}
     */
    public function suggest(int $ioId, ?int $userId = null): array
    {
        $master = $this->render->defaultMaster($ioId);
        $path = $master ? $this->render->resolveAbsolutePath($master) : null;
        if (! $master || ! $path || ! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return ['added' => 0, 'findings' => 0, 'missing' => [], 'pages' => 0, 'ocr' => false, 'error' => 'This record has no PDF master file.'];
        }

        $pages = $this->python($path, [])['pages'] ?? [];
        $scanner = new PiiScanService();
        $values = [];
        foreach ($pages as $p) {
            foreach ($scanner->scan((string) $p['text']) as $f) {
                if (($f['confidence'] ?? 0) < self::MIN_CONFIDENCE || ($f['validated'] ?? null) === false) {
                    continue;
                }
                $key = $p['page'].'|'.$f['value'];
                $values[$key] = ['page' => (int) $p['page'], 'value' => (string) $f['value'], 'type' => (string) $f['type']];
            }
        }
        $located = $values ? $this->python($path, array_values(array_map(fn ($v) => ['page' => $v['page'], 'value' => $v['value']], $values))) : [];

        // Already suggested: compared decoded, since MySQL rewrites stored JSON.
        $key = fn (int $page, array $c) => $page.':'.implode(',', array_map(fn ($k) => sprintf('%.4f', (float) ($c[$k] ?? 0)), ['x', 'y', 'width', 'height']));
        $seen = [];
        foreach (DB::table('privacy_visual_redaction')->where('object_id', $ioId)->where('source', 'auto_pii')->get(['page_number', 'coordinates']) as $row) {
            $seen[$key((int) $row->page_number, (array) json_decode((string) $row->coordinates, true))] = true;
        }

        $added = 0;
        foreach ($located['regions'] ?? [] as $r) {
            $coords = ['x' => round($r['x'], 5), 'y' => round($r['y'], 5), 'width' => round($r['width'], 5), 'height' => round($r['height'], 5)];
            $type = $values[$r['page'].'|'.$r['value']]['type'] ?? 'pii';
            if (isset($seen[$key((int) $r['page'], $coords)])) {
                continue;
            }
            $seen[$key((int) $r['page'], $coords)] = true;
            DB::table('privacy_visual_redaction')->insert([
                'object_id' => $ioId, 'digital_object_id' => (int) $master->id, 'page_number' => (int) $r['page'],
                'region_type' => 'rectangle', 'coordinates' => json_encode($coords), 'normalized' => 1,
                'source' => 'auto_pii', 'label' => str_replace('_', ' ', $type), 'color' => '#000000',
                'status' => 'pending', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
        }
        if ($added > 0) {
            $this->render->invalidate($ioId);
        }

        return [
            'added' => $added, 'findings' => count($values), 'missing' => $located['missing'] ?? [],
            'pages' => count($pages), 'ocr' => (bool) array_filter(array_column($pages, 'ocr')),
        ];
    }

    /**
     * The text of each page of a record's PDF master, OCR'd where a page has no
     * text layer; null when the record has no PDF. Shared with the invoice
     * field extraction, which reads the same documents.
     *
     * @return list<array{page:int, text:string, ocr:bool}>|null
     */
    public function pageTexts(int $ioId): ?array
    {
        $master = $this->render->defaultMaster($ioId);
        $path = $master ? $this->render->resolveAbsolutePath($master) : null;
        if (! $path || ! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return null;
        }

        return $this->python($path, [])['pages'] ?? [];
    }

    private function python(string $pdf, array $values): array
    {
        $proc = new Process(['python3', self::SCRIPT, $pdf]);
        $proc->setInput(json_encode(['values' => $values]));
        $proc->setTimeout(180);
        $proc->run();
        if (! $proc->isSuccessful()) {
            throw new \RuntimeException('pii_regions.py failed: '.substr($proc->getErrorOutput(), 0, 300));
        }

        return json_decode($proc->getOutput(), true) ?: [];
    }
}
