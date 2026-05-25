<?php

/**
 * NerFeedbackService - Service for Heratio
 *
 * Task 9 of the AHG Authority Resolution Engine. Captures every archivist
 * 'reject' decision as a training-grade negative example for the NER service
 * at /opt/ahg-ai. One row per reject decision; mirrors enough context
 * (source text + offsets + entity_type + reason) to drive a retraining loop.
 *
 *   captureFromRejection()   -> hooked off DecisionRecorder::recordReject()
 *   exportUnexported()       -> JSONL (default) or CONLL flat file, marks rows
 *
 * Wrapped in try/catch by the caller - feedback capture MUST NOT break a
 * reject decision (the audit row + mention state flip are the durable spine).
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

namespace AhgAuthorityResolution\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NerFeedbackService
{
    public function __construct(
        private PromoteToMentionService $promoter,
    ) {}

    /**
     * Insert one ahg_ner_feedback row from a freshly written 'reject'
     * decision. Returns the new feedback id, or null if anything we need
     * is missing (decision row gone, not a reject, mention gone). Callers
     * are expected to swallow exceptions - this is best-effort.
     *
     * The rejection_reason column is required NOT NULL by schema; empty
     * input is normalised to '(no reason supplied)' so the historical
     * "five-button reject without a reason" path still records something.
     */
    public function captureFromRejection(int $decisionId, ?string $rejectionReason = null): ?int
    {
        $decision = DB::table('ahg_mention_decision')->where('id', $decisionId)->first();
        if (! $decision || $decision->decision_type !== 'reject') {
            return null;
        }

        $mention = DB::table('ahg_mention as m')
            ->join('ahg_ner_entity as n', 'n.id', '=', 'm.ner_entity_id')
            ->where('m.id', $decision->mention_id)
            ->first([
                'm.id as mention_id',
                'm.ner_entity_id',
                'm.object_id',
                'm.entity_type',
                'n.entity_value',
            ]);
        if (! $mention) {
            return null;
        }

        $context = DB::table('ahg_mention_context')
            ->where('mention_id', $decision->mention_id)
            ->first();

        $sourceText = $this->promoter->fetchSourceText((int) $mention->object_id);
        $reason = trim((string) ($rejectionReason ?? ''));
        if ($reason === '') {
            $reason = '(no reason supplied)';
        }

        $offsetStart = $context && $context->character_offset_start !== null ? (int) $context->character_offset_start : null;
        $offsetEnd = $context && $context->character_offset_end !== null ? (int) $context->character_offset_end : null;
        $nerModelVersion = $context && $context->ner_model_version !== null ? (string) $context->ner_model_version : null;

        try {
            return (int) DB::table('ahg_ner_feedback')->insertGetId([
                'mention_id' => (int) $mention->mention_id,
                'ner_entity_id' => (int) $mention->ner_entity_id,
                'decision_id' => $decisionId,
                'source_text' => $sourceText,
                'mention_value' => (string) $mention->entity_value,
                'mention_entity_type' => (string) $mention->entity_type,
                'mention_offset_start' => $offsetStart,
                'mention_offset_end' => $offsetEnd,
                'rejection_reason' => $reason,
                'archivist_user_id' => (int) $decision->archivist_user_id,
                'ner_model_version' => $nerModelVersion,
                'training_exported' => 0,
                'exported_at' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('NerFeedbackService: capture failed', [
                'decision_id' => $decisionId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Export every unexported ahg_ner_feedback row to a flat training file.
     *
     * @param  string  $format  'jsonl' (default) or 'conll'
     * @return array{count:int,path:string}
     */
    public function exportUnexported(string $format = 'jsonl'): array
    {
        $format = strtolower($format);
        if (! in_array($format, ['jsonl', 'conll'], true)) {
            throw new \InvalidArgumentException("Unknown export format: {$format}");
        }

        $rows = DB::table('ahg_ner_feedback')
            ->where('training_exported', 0)
            ->orderBy('id')
            ->get();

        $count = $rows->count();
        $ts = date('Ymd-His');
        $ext = $format === 'jsonl' ? 'jsonl' : 'conll';
        $relDir = 'auth-res/ner-feedback';
        $absDir = storage_path('app/'.$relDir);

        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }

        $path = $absDir.'/'.$ts.'.'.$ext;

        if ($count === 0) {
            // Still touch the file so caller has a deterministic artefact.
            @file_put_contents($path, '');

            return ['count' => 0, 'path' => $path];
        }

        $exportedIds = [];

        if ($format === 'jsonl') {
            $fh = fopen($path, 'wb');
            if ($fh === false) {
                throw new \RuntimeException("Could not open {$path} for writing.");
            }
            try {
                foreach ($rows as $r) {
                    $line = $this->buildJsonlLine($r);
                    fwrite($fh, $line."\n");
                    $exportedIds[] = (int) $r->id;
                }
            } finally {
                fclose($fh);
            }
        } else {
            // CONLL-2003 style: one token per line, blank line between docs.
            // We do not have token offsets - we emit a coarse approximation
            // where the rejected span is tagged B-<TYPE> on first token and
            // I-<TYPE> on subsequent tokens; everything else is O.
            $fh = fopen($path, 'wb');
            if ($fh === false) {
                throw new \RuntimeException("Could not open {$path} for writing.");
            }
            try {
                foreach ($rows as $r) {
                    $this->writeConllRecord($fh, $r);
                    $exportedIds[] = (int) $r->id;
                }
            } finally {
                fclose($fh);
            }
        }

        // Mark rows exported in one statement.
        if (! empty($exportedIds)) {
            DB::table('ahg_ner_feedback')
                ->whereIn('id', $exportedIds)
                ->update([
                    'training_exported' => 1,
                    'exported_at' => now(),
                ]);
        }

        return ['count' => $count, 'path' => $path];
    }

    private function buildJsonlLine(object $r): string
    {
        $start = $r->mention_offset_start !== null ? (int) $r->mention_offset_start : null;
        $end = $r->mention_offset_end !== null ? (int) $r->mention_offset_end : null;

        $payload = [
            'feedback_id' => (int) $r->id,
            'mention_id' => (int) $r->mention_id,
            'ner_entity_id' => (int) $r->ner_entity_id,
            'decision_id' => (int) $r->decision_id,
            'text' => (string) $r->source_text,
            'spans' => [[
                'start' => $start,
                'end' => $end,
                'type' => (string) $r->mention_entity_type,
                'value' => (string) $r->mention_value,
                'label' => 'reject',
                'rejection_reason' => (string) $r->rejection_reason,
                'archivist_user_id' => (int) $r->archivist_user_id,
                'ner_model_version' => $r->ner_model_version !== null ? (string) $r->ner_model_version : null,
            ]],
            'created_at' => (string) $r->created_at,
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  resource  $fh
     */
    private function writeConllRecord($fh, object $r): void
    {
        $text = (string) $r->source_text;
        $value = (string) $r->mention_value;
        $type = (string) $r->mention_entity_type;

        // Header comment for the document so the retrainer can pivot back.
        fwrite($fh, "# feedback_id={$r->id}  mention_id={$r->mention_id}  reject_type={$type}\n");

        // Tokenise whitespace; mark the rejected span best-effort by string match.
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];
        $rejectedTokens = preg_split('/\s+/u', trim($value)) ?: [];
        $rejTokenCount = count(array_filter($rejectedTokens, fn ($t) => $t !== ''));

        $iSpan = -1;  // index where the rejected span starts (token-aligned)
        if ($rejTokenCount > 0) {
            for ($i = 0; $i + $rejTokenCount <= count($tokens); $i++) {
                $slice = array_slice($tokens, $i, $rejTokenCount);
                if (implode(' ', $slice) === implode(' ', $rejectedTokens)) {
                    $iSpan = $i;
                    break;
                }
            }
        }

        foreach ($tokens as $idx => $tok) {
            if ($idx === $iSpan) {
                $tag = 'B-REJ-'.$type;
            } elseif ($iSpan >= 0 && $idx > $iSpan && $idx < $iSpan + $rejTokenCount) {
                $tag = 'I-REJ-'.$type;
            } else {
                $tag = 'O';
            }
            fwrite($fh, $tok."\t".$tag."\n");
        }
        fwrite($fh, "\n");
    }
}
