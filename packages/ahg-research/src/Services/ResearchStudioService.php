<?php

/**
 * ResearchStudioService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Services;

use AhgAiServices\Services\LlmService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;

/**
 * ResearchStudioService
 *
 * NotebookLM-Studio style artefact generator. Sits on top of a Research
 * Project, takes a typed list of sources (evidence-set items, attached PDFs,
 * finding aids, donor agreements), and produces:
 *
 *   briefing | study_guide | faq | timeline | diagram | video_script |
 *   spreadsheet | audio
 *
 * Generations are local: chat goes through LlmService::completeFull() (the
 * existing per-config provider switch handles Ollama/OpenAI/Anthropic/cloud
 * override). Spreadsheets are built locally with PhpSpreadsheet. Audio is
 * stubbed against a configurable TTS endpoint - when no endpoint is
 * configured the audio artefact lands in 'error' state with a clear message.
 *
 * Outputs are persisted as `research_studio_artefact` rows with full
 * source_object_ids JSON provenance + a citations map so the Studio pane
 * can render [N] popovers with snippet + scroll-to-source.
 */
class ResearchStudioService
{
    public const SUPPORTED_TYPES = [
        'briefing'     => 'Briefing Document',
        'study_guide'  => 'Study Guide',
        'faq'          => 'FAQ',
        'timeline'     => 'Timeline',
        'diagram'      => 'Diagram (Mermaid)',
        'video_script' => 'Video Script',
        'spreadsheet'  => 'Spreadsheet',
        'audio'        => 'Audio Overview',
    ];

    public function __construct(private LlmService $llm) {}

    public function listForProject(int $projectId): array
    {
        return DB::table('research_studio_artefact')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function get(int $id): ?object
    {
        return DB::table('research_studio_artefact')->where('id', $id)->first();
    }

    /**
     * Generate a Studio artefact. Returns the artefact row id.
     *
     * $sourceObjectIds: list of information_object ids the artefact should be
     *                   synthesised from (evidence-set members typically).
     * $outputType:      one of SUPPORTED_TYPES keys.
     * $options:         output-type specific options (spreadsheet uses
     *                   'columns_request' etc., audio uses 'voice_id').
     */
    public function generate(int $projectId, array $sourceObjectIds, string $outputType, array $options = [], ?int $createdBy = null): int
    {
        if (!array_key_exists($outputType, self::SUPPORTED_TYPES)) {
            throw new RuntimeException("Unsupported Studio output type: {$outputType}");
        }

        $artefactId = DB::table('research_studio_artefact')->insertGetId([
            'project_id'        => $projectId,
            'created_by'        => $createdBy,
            'output_type'       => $outputType,
            'title'             => $options['title'] ?? self::SUPPORTED_TYPES[$outputType],
            'source_object_ids' => json_encode(array_values(array_map('intval', $sourceObjectIds))),
            'status'            => 'generating',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        try {
            $sources = $this->loadSources($sourceObjectIds);

            $result = match ($outputType) {
                'briefing'     => $this->generateText('briefing',     $sources, $options),
                'study_guide'  => $this->generateText('study_guide',  $sources, $options),
                'faq'          => $this->generateText('faq',          $sources, $options),
                'timeline'     => $this->generateText('timeline',     $sources, $options),
                'diagram'      => $this->generateText('diagram',      $sources, $options),
                'video_script' => $this->generateText('video_script', $sources, $options),
                'spreadsheet'  => $this->generateSpreadsheet($projectId, $artefactId, $sources, $options),
                'audio'        => $this->generateAudio($artefactId, $sources, $options),
            };

            DB::table('research_studio_artefact')->where('id', $artefactId)->update(array_merge([
                'status'     => $result['status'] ?? 'ready',
                'updated_at' => date('Y-m-d H:i:s'),
            ], array_intersect_key($result, array_flip([
                'body', 'body_format', 'citations', 'model', 'tokens_used',
                'generation_time_ms', 'audio_url', 'audio_duration_seconds',
                'audio_transcript', 'xlsx_path', 'error_text',
            ]))));
        } catch (\Throwable $e) {
            Log::warning('Studio generation failed: ' . $e->getMessage());
            DB::table('research_studio_artefact')->where('id', $artefactId)->update([
                'status'     => 'error',
                'error_text' => $e->getMessage(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return (int) $artefactId;
    }

    public function delete(int $id): bool
    {
        $row = $this->get($id);
        if ($row && !empty($row->xlsx_path) && is_file($row->xlsx_path)) {
            @unlink($row->xlsx_path);
        }

        return DB::table('research_studio_artefact')->where('id', $id)->delete() > 0;
    }

    // ─── Source loading ─────────────────────────────────────────────────

    /**
     * Load the IO sources into the shape used in prompts + citation maps.
     * Each source: {n, object_id, title, identifier, snippet, url}
     */
    private function loadSources(array $sourceObjectIds): array
    {
        if (empty($sourceObjectIds)) {
            return [];
        }

        $rows = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', app()->getLocale());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->whereIn('io.id', $sourceObjectIds)
            ->select(
                'io.id',
                'io.identifier',
                'ioi.title',
                'ioi.scope_and_content',
                'slug.slug'
            )
            ->get();

        $appUrl = rtrim(config('app.url', ''), '/');
        $sources = [];
        $n = 1;
        foreach ($rows as $r) {
            $snippet = (string) ($r->scope_and_content ?? '');
            if (mb_strlen($snippet) > 800) {
                $snippet = mb_substr($snippet, 0, 800) . '...';
            }
            $sources[] = [
                'n'          => $n++,
                'object_id'  => (int) $r->id,
                'title'      => (string) ($r->title ?? 'Untitled'),
                'identifier' => (string) ($r->identifier ?? ''),
                'snippet'    => $snippet,
                'url'        => $appUrl . '/' . ($r->slug ?? $r->id),
            ];
        }

        return $sources;
    }

    // ─── Text generators ────────────────────────────────────────────────

    private function generateText(string $outputType, array $sources, array $options): array
    {
        [$systemPrompt, $userPrompt, $bodyFormat] = $this->promptFor($outputType, $sources, $options);

        $result = $this->llm->completeFull($systemPrompt, $userPrompt, $options['config_id'] ?? null, [
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens'  => $options['max_tokens']  ?? 3000,
        ]);

        if (empty($result['success'])) {
            return [
                'status'     => 'error',
                'error_text' => $result['error'] ?? 'LLM call failed',
            ];
        }

        return [
            'body'              => $result['text'] ?? '',
            'body_format'       => $bodyFormat,
            'citations'         => json_encode($sources),
            'model'             => $result['model'] ?? null,
            'tokens_used'       => (int) ($result['tokens_used'] ?? 0),
            'generation_time_ms' => (int) ($result['generation_time_ms'] ?? 0),
            'status'            => 'ready',
        ];
    }

    private function promptFor(string $outputType, array $sources, array $options): array
    {
        $sourceBlock = '';
        foreach ($sources as $s) {
            $sourceBlock .= "[{$s['n']}] {$s['title']}" . ($s['identifier'] ? " ({$s['identifier']})" : '') . "\n"
                . $s['snippet'] . "\n\n";
        }

        $system = 'You are an archival research assistant. You generate grounded research artefacts strictly from the supplied source passages. Every factual claim MUST be followed by a citation marker like [1] or [2] that maps to the supplied source numbers. Do not invent facts that are not in the sources. If the sources do not contain enough information, say so plainly.';

        return match ($outputType) {
            'briefing' => [
                $system,
                "Write a concise BRIEFING DOCUMENT (~600-900 words) from the following archival sources. Structure: '## Summary', '## Key facts', '## Stakeholders / actors', '## Open questions'. Use [N] markers tied to source numbers below.\n\nSources:\n{$sourceBlock}",
                'markdown',
            ],
            'study_guide' => [
                $system,
                "Write a STUDY GUIDE for a graduate seminar from the following archival sources. Structure: '## Reading list with one-line annotations', '## Key concepts', '## Discussion questions (10)', '## Suggested essay prompts (3)'. Cite with [N].\n\nSources:\n{$sourceBlock}",
                'markdown',
            ],
            'faq' => [
                $system,
                "Produce a FAQ from these sources. Format: '### Question?\\nAnswer with citations [N].' Cover the 8-12 most likely questions a researcher would have. Cite every answer.\n\nSources:\n{$sourceBlock}",
                'markdown',
            ],
            'timeline' => [
                $system,
                "Extract a chronological TIMELINE from these sources. Output as a markdown table with columns: Date | Event | Source. Sort ascending by date. If a date is approximate, prefix with c. or use range notation.\n\nSources:\n{$sourceBlock}",
                'markdown',
            ],
            'diagram' => [
                $system,
                "Generate a MERMAID diagram describing the relationships between actors, organisations, and events in these sources. Use 'graph TD' or 'graph LR' as appropriate. Output ONLY the mermaid code block (no surrounding prose). Citations as edge labels [N] where useful.\n\nSources:\n{$sourceBlock}",
                'mermaid',
            ],
            'video_script' => [
                $system,
                "Write a 5-7 minute VIDEO SCRIPT explaining the contents of these sources to a general audience. Use clear paragraph breaks. Annotate every fact with [N]. End with three suggested visuals.\n\nSources:\n{$sourceBlock}",
                'markdown',
            ],
            default => [$system, '', 'markdown'],
        };
    }

    // ─── Spreadsheet ────────────────────────────────────────────────────

    /**
     * Two-step generation: ask the LLM to project the sources into a
     * {header, intro, columns, rows} JSON doc, then build the .xlsx
     * locally with PhpSpreadsheet.
     */
    private function generateSpreadsheet(int $projectId, int $artefactId, array $sources, array $options): array
    {
        $columnsRequest = $options['columns_request'] ?? 'date, actor, location, event_summary, source_ref';

        $sourceBlock = '';
        foreach ($sources as $s) {
            $sourceBlock .= "[{$s['n']}] {$s['title']}\n{$s['snippet']}\n\n";
        }

        $system = 'You are an archival research analyst. You project source passages into structured data. Output STRICT JSON, nothing else. No markdown code fences, no preamble.';
        $user = "Project the following archival sources into a JSON document with this shape:\n"
            . "{\"header\":\"...\",\"intro\":\"...\",\"columns\":[\"col1\",\"col2\",...],\"rows\":[[v1,v2,...],...]}\n\n"
            . "Required columns: {$columnsRequest}\n"
            . "Use the [N] source numbers in the source_ref column when present.\n\n"
            . "Sources:\n{$sourceBlock}";

        $result = $this->llm->completeFull($system, $user, $options['config_id'] ?? null, [
            'temperature' => 0.1,
            'max_tokens'  => 3000,
        ]);

        if (empty($result['success'])) {
            return ['status' => 'error', 'error_text' => $result['error'] ?? 'LLM call failed'];
        }

        $raw = trim((string) ($result['text'] ?? ''));
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $doc = json_decode(trim($raw), true);

        if (!is_array($doc) || empty($doc['columns']) || !isset($doc['rows'])) {
            return [
                'status'     => 'error',
                'error_text' => 'LLM did not return a parseable spreadsheet JSON doc',
                'body'       => $result['text'],
                'body_format' => 'json',
            ];
        }

        $xlsxPath = $this->buildXlsxFile($projectId, $artefactId, $doc);

        return [
            'body'        => json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'body_format' => 'json',
            'xlsx_path'   => $xlsxPath,
            'citations'   => json_encode($sources),
            'model'       => $result['model'] ?? null,
            'tokens_used' => (int) ($result['tokens_used'] ?? 0),
            'generation_time_ms' => (int) ($result['generation_time_ms'] ?? 0),
            'status'      => 'ready',
        ];
    }

    private function buildXlsxFile(int $projectId, int $artefactId, array $doc): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr((string) ($doc['header'] ?? 'Studio'), 0, 31));

        $colIdx = 1;
        $rowIdx = 1;

        if (!empty($doc['intro'])) {
            $sheet->setCellValue([1, $rowIdx], (string) $doc['intro']);
            $sheet->getStyle([1, $rowIdx])->getFont()->setItalic(true);
            $rowIdx += 2;
        }

        foreach (($doc['columns'] ?? []) as $i => $colName) {
            $sheet->setCellValue([$i + 1, $rowIdx], (string) $colName);
            $sheet->getStyle([$i + 1, $rowIdx])->getFont()->setBold(true);
        }
        $rowIdx++;

        foreach (($doc['rows'] ?? []) as $row) {
            if (!is_array($row)) continue;
            foreach (array_values($row) as $i => $cell) {
                $sheet->setCellValue([$i + 1, $rowIdx], is_scalar($cell) ? (string) $cell : json_encode($cell));
            }
            $rowIdx++;
        }

        $dir = rtrim(config('heratio.storage_path'), '/') . '/research-studio/' . $projectId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/artefact-' . $artefactId . '.xlsx';

        (new XlsxWriter($spreadsheet))->save($path);

        return $path;
    }

    // ─── Audio ─────────────────────────────────────────────────────────

    /**
     * Two-step: LLM writes a two-voice walkthrough script -> POST script
     * to a configurable TTS endpoint. If no endpoint is configured, the
     * artefact lands in error state with a clear message (script is still
     * persisted as transcript so the operator can use it manually).
     */
    private function generateAudio(int $artefactId, array $sources, array $options): array
    {
        $sourceBlock = '';
        foreach ($sources as $s) {
            $sourceBlock .= "[{$s['n']}] {$s['title']}\n{$s['snippet']}\n\n";
        }

        $system = 'You are a podcast scriptwriter for an archives-and-heritage two-voice show. Voices are "Host" (curious generalist) and "Curator" (subject expert). Keep it accessible, ~5-8 minutes of read-aloud time. Cite source numbers [N] inline.';
        $user = "Write a two-voice walkthrough podcast script for the following archival sources. Alternate Host: and Curator: lines. Open with a hook, close with one open question for the audience.\n\nSources:\n{$sourceBlock}";

        $scriptResult = $this->llm->completeFull($system, $user, $options['config_id'] ?? null, [
            'temperature' => 0.4,
            'max_tokens'  => 4000,
        ]);

        if (empty($scriptResult['success'])) {
            return [
                'status'     => 'error',
                'error_text' => 'Audio script generation failed: ' . ($scriptResult['error'] ?? 'unknown'),
            ];
        }

        $transcript = (string) ($scriptResult['text'] ?? '');

        $ttsEndpoint = config('heratio.tts_endpoint') ?: env('HERATIO_TTS_ENDPOINT');
        $ttsKey      = config('heratio.tts_key')      ?: env('HERATIO_TTS_KEY');

        if (empty($ttsEndpoint)) {
            return [
                'status'           => 'error',
                'error_text'       => 'TTS endpoint not configured (HERATIO_TTS_ENDPOINT). Script saved as transcript.',
                'audio_transcript' => $transcript,
                'body'             => $transcript,
                'body_format'      => 'markdown',
                'citations'        => json_encode($sources),
                'model'            => $scriptResult['model'] ?? null,
                'tokens_used'      => (int) ($scriptResult['tokens_used'] ?? 0),
            ];
        }

        try {
            $req = Http::timeout((int) ($options['timeout'] ?? 600))->asJson();
            if ($ttsKey) {
                $req = $req->withToken($ttsKey);
            }
            $resp = $req->post(rtrim($ttsEndpoint, '/') . '/synthesize', [
                'script'      => $transcript,
                'voice_a'     => $options['voice_a'] ?? 'host',
                'voice_b'     => $options['voice_b'] ?? 'curator',
                'voice_id'    => $options['voice_id'] ?? null,
                'artefact_id' => $artefactId,
            ]);

            if (!$resp->ok()) {
                return [
                    'status'     => 'error',
                    'error_text' => 'TTS endpoint returned HTTP ' . $resp->status(),
                    'audio_transcript' => $transcript,
                ];
            }

            $body = $resp->json();

            return [
                'audio_url'              => $body['audio_url'] ?? null,
                'audio_duration_seconds' => (int) ($body['duration_seconds'] ?? 0),
                'audio_transcript'       => $transcript,
                'body'                   => $transcript,
                'body_format'            => 'markdown',
                'citations'              => json_encode($sources),
                'model'                  => $scriptResult['model'] ?? null,
                'tokens_used'            => (int) ($scriptResult['tokens_used'] ?? 0),
                'status'                 => 'ready',
            ];
        } catch (\Throwable $e) {
            return [
                'status'           => 'error',
                'error_text'       => 'TTS call failed: ' . $e->getMessage(),
                'audio_transcript' => $transcript,
            ];
        }
    }
}
