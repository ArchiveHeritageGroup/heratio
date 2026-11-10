<?php

/**
 * PageClassifier - document type and privacy screening per page (or per
 * window of N words), rolled up to the record, so one label does not hide a
 * mixed file (heratio#1584).
 *
 * Each page result is an ahg_ai_inference row (signed, with model and input
 * digest) whose target_field is "page:{from}-{to}:privacy" or
 * "page:{from}-{to}:doctype". A run starts with a "pages:run" row; the
 * segments of a record are the rows after its latest run row. A reviewer's
 * correction is an ahg_ai_override event on the page row.
 *
 * Privacy is the deterministic PII scan of the page text; the roll-up is the
 * maximum, not the mean: one flagged page flags the record, and the record's
 * PII scan report (ahg_pii_scan_report, pending review) is raised from the
 * flagged pages. The document type comes from the LLM through the AI gateway,
 * chosen from the Dropdown Manager taxonomy page_document_type, and only by
 * day (GPUs are off 18:00-06:00 SAST); at night the privacy screen still runs.
 *
 * Page text is never stored in the provenance rows (it holds the PII being
 * screened); the excerpt names the pages instead.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgAiServices\Services;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Support\DropdownOptions;
use AhgPrivacy\Services\PiiRedactionSuggester;
use AhgPrivacy\Services\PiiScanService;
use AhgProvenanceAi\DTO\InferenceRecord;
use AhgProvenanceAi\Services\InferenceService;
use Illuminate\Support\Facades\DB;

class PageClassifier
{
    public const TAXONOMY = 'page_document_type';

    /** Used only when the taxonomy has no rows yet (database/seed_page_document_type.sql). */
    private const FALLBACK_TYPES = [
        'letter' => 'Letter', 'form' => 'Form', 'invoice' => 'Invoice', 'purchase_order' => 'Purchase order',
        'delivery_note' => 'Delivery note', 'medical_record' => 'Medical record', 'identity_document' => 'Identity document',
        'report' => 'Report', 'minutes' => 'Minutes', 'newspaper_cutting' => 'Newspaper cutting',
        'photograph' => 'Photograph', 'other' => 'Other',
    ];

    public function __construct(
        private PiiRedactionSuggester $pages,
        private PiiScanService $pii,
        private LlmService $llm,
        private InferenceService $inference,
    ) {
    }

    /** @return array<string,string> code => label */
    public static function types(): array
    {
        return DropdownOptions::resolve(self::TAXONOMY, self::FALLBACK_TYPES);
    }

    /**
     * Should an ingested record be read page by page? ai_page_classify_on_ingest
     * (default off), limited to the collections in ai_page_classify_collections
     * (comma-separated record ids; empty = every collection).
     */
    public static function wantsOnIngest(int $ioId): bool
    {
        if (! AhgSettingsService::getBool('ai_page_classify_on_ingest', false)) {
            return false;
        }
        $roots = array_filter(array_map('intval', explode(',', (string) AhgSettingsService::get('ai_page_classify_collections', ''))));
        if (! $roots) {
            return true;
        }
        // Closure table first: a new record may have no lft/rgt bounds.
        $ancestors = app(\AhgCore\Services\HierarchyQueryService::class)->ancestorIds('information_object', $ioId, true);

        return (bool) array_intersect($roots, $ancestors);
    }

    /** May the GPU-backed step run now? 06:00-18:00 SAST only. */
    public static function gpuHours(?\DateTimeInterface $at = null): bool
    {
        $h = (int) (\Carbon\Carbon::instance($at ?? now())->setTimezone('Africa/Johannesburg')->format('G'));

        return $h >= 6 && $h < 18;
    }

    /**
     * Classify and screen every page (or window) of a record's PDF.
     *
     * @return array{units:int, flagged:list<string>, doctype:bool, report_id:?int, error?:string}
     */
    public function run(int $ioId, ?int $userId = null, ?bool $withDoctype = null): array
    {
        $pages = $this->pages->pageTexts($ioId);
        if ($pages === null) {
            return ['units' => 0, 'flagged' => [], 'doctype' => false, 'report_id' => null, 'error' => 'This record has no PDF master file.'];
        }
        $window = max(0, (int) AhgSettingsService::get('ai_page_window_words', '0'));
        $units = self::units($pages, $window);
        $withDoctype ??= self::gpuHours();
        // The type list on first use, not on every boot.
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown') && ! DB::table('ahg_dropdown')->where('taxonomy', self::TAXONOMY)->exists()) {
            DB::unprepared((string) file_get_contents(__DIR__.'/../../database/seed_page_document_type.sql'));
        }
        $types = self::types();

        $this->record($ioId, 'pages:run', 'PAGES', 'heratio-page-classifier', '1', json_encode(['window' => $window, 'units' => count($units), 'doctype' => $withDoctype]), 'pages 1-'.max(1, count($pages)), null, $userId);

        $flagged = [];
        $flaggedText = [];
        $model = null;
        foreach ($units as $u) {
            $range = $u['from'].'-'.$u['to'];
            if (trim($u['text']) === '') {
                // No text even after OCR: not "clear" - nobody has read it.
                $this->record($ioId, "page:{$range}:privacy", 'PII', 'heratio-pii-scan', $this->pii->jurisdiction(), json_encode(['label' => 'unread', 'types' => []]), "pages {$range}", null, $userId, '');
                continue;
            }
            // The redaction suggester's rule: a weak or unvalidated finding (a
            // date with no "born" near it is an invoice date) may not flag.
            $findings = array_values(array_filter($this->pii->scan($u['text']), fn ($f) => ($f['confidence'] ?? 0) >= PiiRedactionSuggester::MIN_CONFIDENCE && ($f['validated'] ?? null) !== false));
            $label = $findings ? 'flagged' : 'clear';
            $kinds = array_values(array_unique(array_column($findings, 'type')));
            $this->record($ioId, "page:{$range}:privacy", 'PII', 'heratio-pii-scan', $this->pii->jurisdiction(),
                json_encode(['label' => $label, 'types' => $kinds]), "pages {$range}",
                $findings ? max(array_column($findings, 'confidence')) : 1.0, $userId, $u['text']);
            if ($findings) {
                $flagged[] = $range;
                $flaggedText[] = $u['text'];
            }

            if ($withDoctype) {
                $code = $this->doctype($u['text'], $types, $model);
                if ($code !== null) {
                    $this->record($ioId, "page:{$range}:doctype", 'LLM', $model ?: 'unknown', 'unknown', json_encode(['label' => $code]), "pages {$range}", null, $userId, $u['text']);
                }
            }
        }

        // The record-level flag: the existing PII scan report, raised from the
        // flagged pages only, pending an archivist's review.
        $reportId = $flaggedText ? $this->pii->scanAndPersist(implode("\n\n", $flaggedText), $ioId, $userId) : null;

        return ['units' => count($units), 'flagged' => $flagged, 'doctype' => $withDoctype, 'report_id' => $reportId];
    }

    /**
     * Pages as units, or windows of $window words across pages.
     *
     * @param list<array{page:int,text:string}> $pages
     * @return list<array{from:int,to:int,text:string}>
     */
    public static function units(array $pages, int $window): array
    {
        if ($window <= 0) {
            return array_map(fn ($p) => ['from' => (int) $p['page'], 'to' => (int) $p['page'], 'text' => (string) $p['text']], $pages);
        }
        $out = [];
        $buf = [];
        $from = null;
        $to = null;
        foreach ($pages as $p) {
            $words = preg_split('/\s+/u', trim((string) $p['text']), -1, PREG_SPLIT_NO_EMPTY);
            if (! $words) {
                // An empty page stays its own unit, so it shows as unread.
                if ($buf) {
                    $out[] = ['from' => $from, 'to' => $to, 'text' => implode(' ', $buf)];
                    $buf = [];
                    $from = null;
                }
                $out[] = ['from' => (int) $p['page'], 'to' => (int) $p['page'], 'text' => ''];
                continue;
            }
            foreach ($words as $w) {
                $from ??= (int) $p['page'];
                $to = (int) $p['page'];
                $buf[] = $w;
                if (count($buf) >= $window) {
                    $out[] = ['from' => $from, 'to' => $to, 'text' => implode(' ', $buf)];
                    $buf = [];
                    $from = null;
                }
            }
        }
        if ($buf) {
            $out[] = ['from' => $from, 'to' => $to, 'text' => implode(' ', $buf)];
        }

        return $out;
    }

    /**
     * The segments of the latest run, each with its effective (reviewed) value.
     *
     * @return list<array{from:int,to:int,privacy:?array,doctype:?array}>
     *   privacy/doctype: {id, label, types?, model, confidence, original, overridden}
     */
    public function segments(int $ioId): array
    {
        $runId = (int) DB::table('ahg_ai_inference')->where('target_entity_type', 'information_object')
            ->where('target_entity_id', $ioId)->where('target_field', 'pages:run')->max('id');
        if ($runId === 0) {
            return [];
        }
        $rows = DB::table('ahg_ai_inference')->where('target_entity_type', 'information_object')
            ->where('target_entity_id', $ioId)->where('id', '>', $runId)->where('target_field', 'like', 'page:%')
            ->orderBy('id')->get(['id', 'target_field', 'output_excerpt', 'model_name', 'confidence']);
        $overrides = $rows->isEmpty() ? collect() : DB::table('ahg_ai_override')->whereIn('inference_id', $rows->pluck('id'))
            ->where('status', 'applied')->orderBy('id')->get(['inference_id', 'override_value'])->keyBy('inference_id');

        $seg = [];
        foreach ($rows as $r) {
            if (! preg_match('/^page:(\d+)-(\d+):(privacy|doctype)$/', $r->target_field, $m)) {
                continue;
            }
            $out = json_decode((string) $r->output_excerpt, true) ?: [];
            $ov = $overrides[$r->id]->override_value ?? null;
            $seg[$m[1].'-'.$m[2]]['from'] = (int) $m[1];
            $seg[$m[1].'-'.$m[2]]['to'] = (int) $m[2];
            $seg[$m[1].'-'.$m[2]][$m[3]] = [
                'id' => (int) $r->id, 'label' => $ov ?? ($out['label'] ?? null), 'types' => $out['types'] ?? [],
                'model' => $r->model_name, 'confidence' => $r->confidence, 'original' => $out['label'] ?? null, 'overridden' => $ov !== null,
            ];
        }

        return array_values(array_map(fn ($s) => $s + ['privacy' => null, 'doctype' => null], $seg));
    }

    /**
     * The record-level result derived from its segments: privacy is the
     * maximum (any flagged page flags the record), the type is a spread.
     *
     * @param list<array> $segments
     * @return array{privacy:string, flagged:list<string>, unread:list<string>, pages:int, spread:array<string,int>}
     */
    public static function rollup(array $segments): array
    {
        $pages = fn ($s) => $s['to'] - $s['from'] + 1;
        $flagged = $unread = [];
        $spread = [];
        $total = 0;
        foreach ($segments as $s) {
            $total += $pages($s);
            $p = $s['privacy']['label'] ?? null;
            if ($p === 'flagged') {
                $flagged[] = $s['from'] === $s['to'] ? (string) $s['from'] : $s['from'].'-'.$s['to'];
            } elseif ($p === 'unread' || $p === null) {
                $unread[] = $s['from'] === $s['to'] ? (string) $s['from'] : $s['from'].'-'.$s['to'];
            }
            if ($t = $s['doctype']['label'] ?? null) {
                $spread[$t] = ($spread[$t] ?? 0) + $pages($s);
            }
        }
        arsort($spread);

        return ['privacy' => $flagged ? 'flagged' : ($unread ? 'incomplete' : 'clear'), 'flagged' => $flagged, 'unread' => $unread, 'pages' => $total, 'spread' => $spread];
    }

    /** A reviewer accepts or corrects one page result; recorded as an override event. */
    public function review(int $inferenceId, string $value, int $userId, ?string $reason = null): void
    {
        $row = DB::table('ahg_ai_inference')->where('id', $inferenceId)->first(['target_field', 'output_excerpt']);
        if (! $row || ! preg_match('/^page:\d+-\d+:(privacy|doctype)$/', $row->target_field, $m)) {
            throw new \InvalidArgumentException('Not a page result.');
        }
        $allowed = $m[1] === 'privacy' ? ['flagged', 'clear'] : array_keys(self::types());
        if (! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('Not a valid value for this page result.');
        }
        $original = (string) ((json_decode((string) $row->output_excerpt, true) ?: [])['label'] ?? '');
        app(\AhgProvenanceAi\Services\OverrideService::class)->record($inferenceId, $original, $value, $userId, $reason);
    }

    private function doctype(string $text, array $types, ?string &$model): ?string
    {
        $model = null;
        $list = implode("\n", array_map(fn ($c, $l) => "{$c} - {$l}", array_keys($types), $types));
        $res = $this->llm->completeFull(
            "You classify one page of an archival file. Answer with exactly one code from this list and nothing else:\n{$list}",
            mb_substr($text, 0, 6000), null, ['temperature' => 0, 'max_tokens' => 20],
        );
        if (empty($res['success'])) {
            return null;
        }
        $model = $res['model'] ?? null;
        $answer = mb_strtolower((string) $res['text']);
        foreach (array_keys($types) as $code) {
            if (preg_match('/\b'.preg_quote((string) $code, '/').'\b/', $answer)) {
                return (string) $code;
            }
        }

        return isset($types['other']) ? 'other' : null;
    }

    private function record(int $ioId, string $field, string $service, string $model, string $version, string $output, string $excerpt, ?float $confidence, ?int $userId, ?string $input = null): void
    {
        $this->inference->record(new InferenceRecord(
            serviceName: $service,
            modelName: $model,
            modelVersion: $version,
            inputHash: hash('sha256', $input ?? $excerpt),
            outputHash: hash('sha256', $output),
            targetEntityType: 'information_object',
            targetEntityId: $ioId,
            targetField: $field,
            confidence: $confidence,
            inputExcerpt: $excerpt,
            outputExcerpt: $output,
            userId: $userId,
        ));
    }
}
