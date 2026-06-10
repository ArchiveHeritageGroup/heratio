<?php

/**
 * ComplianceAutopilotService - Heratio ahg-privacy
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgPrivacy\Services;

use AhgPrivacy\Models\RetentionProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * heratio#1199 - compliance autopilot. Scans the catalogue for personal data (via the
 * existing PiiScanService), aggregates the categories found, and auto-drafts a Records of
 * Processing Activities (Article 30 / ROPA) entry pre-filled with those categories, for a
 * data-protection officer to review and save. Jurisdiction-neutral; the scanner is
 * market-pluggable (POPIA / GDPR / etc.) per its own configuration.
 */
class ComplianceAutopilotService
{
    private const LABELS = [
        'email' => 'Email addresses',
        'phone' => 'Phone numbers',
        'ip' => 'IP addresses',
        'national_id' => 'National identifiers',
        'credit_card' => 'Payment card numbers',
        'dob' => 'Dates of birth',
    ];

    /** Scan up to $limit catalogue descriptions for PII and aggregate by category. */
    public function scanCatalogue(int $limit = 300): array
    {
        $scanner = app(PiiScanService::class);
        $rows = DB::table('information_object_i18n')
            ->where('culture', 'en')
            ->where(function ($q) { $q->whereNotNull('title')->orWhereNotNull('scope_and_content'); })
            ->select('id', 'title', 'scope_and_content')
            ->limit($limit)->get();

        $agg = [];
        $withPii = 0;
        $scanned = 0;
        foreach ($rows as $r) {
            $scanned++;
            $text = trim(strip_tags((string) $r->title.' '.(string) $r->scope_and_content));
            if ($text === '') {
                continue;
            }
            $findings = $scanner->scan($text);
            if (! $findings) {
                continue;
            }
            $withPii++;
            $seen = [];
            foreach ($findings as $f) {
                $t = $f['type'] ?? 'other';
                $agg[$t] = $agg[$t] ?? ['type' => $t, 'count' => 0, 'records' => 0, 'samples' => []];
                $agg[$t]['count']++;
                if (! isset($seen[$t])) {
                    $seen[$t] = true;
                    $agg[$t]['records']++;
                    if (count($agg[$t]['samples']) < 5) {
                        $agg[$t]['samples'][] = (int) $r->id;
                    }
                }
            }
        }

        $cats = array_values($agg);
        usort($cats, fn ($a, $b) => $b['count'] <=> $a['count']);
        foreach ($cats as &$c) {
            $c['label'] = self::LABELS[$c['type']] ?? ucfirst(str_replace('_', ' ', $c['type']));
        }

        return ['scanned' => $scanned, 'records_with_pii' => $withPii, 'categories' => $cats];
    }

    /** Build a ROPA (Article 30) draft from a scan result. Not saved - for review. */
    public function draftRopa(array $scan): array
    {
        $cats = array_map(fn ($c) => $c['label'], $scan['categories'] ?? []);

        return [
            'name' => 'Archival description and access (auto-drafted)',
            'purpose' => 'Cataloguing, preservation and provision of access to archival and collection records, some of which contain personal data.',
            'lawful_basis' => 'Public task / archiving in the public interest (review against the applicable regime).',
            'categories_of_data' => $cats ?: ['Personal data within records'],
            'categories_of_subjects' => ['Individuals named or identifiable in the records (creators, correspondents, subjects of records)'],
            'recipients' => ['Researchers and the public, subject to access controls and redaction'],
            'retention_period' => 'Permanent retention as archival records, per the appraisal/retention policy.',
            'security_measures' => 'Access controls, field-level PII redaction on restricted records, audit logging.',
            'transfers_outside_eea' => false,
            'safeguards' => '',
            'is_active' => true,
        ];
    }

    /**
     * Auto-draft a retention schedule from a catalogue scan and persist it as a
     * set of proposals (one per data category found) for a data-protection
     * officer to accept. For each scanned category the gateway LLM is asked to
     * suggest a defensible retention period, a generic legal/policy basis, and a
     * disposal action - grounded ONLY in the category names the scan surfaced,
     * never in invented record content.
     *
     * Jurisdiction-neutral: the model is instructed to stay generic ("per the
     * applicable retention regime / appraisal policy") so the per-market module
     * (POPIA / GDPR / IPSAS / etc.) supplies the concrete statute. No single
     * country is hardcoded.
     *
     * @return array{proposals:array<int,array<string,mixed>>,source:string}
     */
    public function draftRetentionSchedule(array $scan): array
    {
        $categories = $scan['categories'] ?? [];
        if (! $categories) {
            return ['proposals' => [], 'source' => 'none'];
        }

        $suggestions = $this->suggestRetentionPeriods($categories);
        $source = $suggestions === null ? 'heuristic' : 'llm';
        $suggestions = $suggestions ?? [];

        $proposals = [];
        foreach ($categories as $c) {
            $key = $c['type'] ?? 'other';
            $label = $c['label'] ?? ucfirst(str_replace('_', ' ', (string) $key));
            $s = $suggestions[$key] ?? $this->fallbackRetention($label);

            $row = [
                'category' => (string) $key,
                'category_label' => (string) $label,
                'records_affected' => (int) ($c['records'] ?? 0),
                'retention_period' => $this->clip($s['retention_period'] ?? '', 191) ?: 'Review against the applicable retention policy',
                'legal_basis' => $s['legal_basis'] ?? null,
                'disposal_action' => $this->clip($s['disposal_action'] ?? '', 191) ?: null,
                'rationale' => $s['rationale'] ?? null,
                'source' => $source,
                'status' => RetentionProposal::STATUS_PROPOSED,
            ];

            // Upsert by category so a re-scan refreshes the proposal text but
            // never clobbers a row a DPO has already accepted.
            $existing = RetentionProposal::query()->where('category', $row['category'])->first();
            if ($existing && $existing->status === RetentionProposal::STATUS_ACCEPTED) {
                $proposals[] = $existing->toArray();
                continue;
            }
            $model = RetentionProposal::query()->updateOrCreate(['category' => $row['category']], $row);
            $proposals[] = $model->toArray();
        }

        usort($proposals, fn ($a, $b) => ($b['records_affected'] ?? 0) <=> ($a['records_affected'] ?? 0));

        return ['proposals' => $proposals, 'source' => $source];
    }

    /** All persisted retention proposals, newest-impact first. */
    public function listRetentionProposals(): array
    {
        return RetentionProposal::query()
            ->orderByRaw("status = 'accepted'")
            ->orderByDesc('records_affected')
            ->get()
            ->map->toArray()
            ->all();
    }

    /** Mark a proposal accepted (DPO sign-off). */
    public function acceptRetentionProposal(int $id, ?int $userId): bool
    {
        $p = RetentionProposal::query()->find($id);
        if (! $p) {
            return false;
        }
        $p->status = RetentionProposal::STATUS_ACCEPTED;
        $p->accepted_at = now();
        $p->accepted_by = $userId;
        $p->save();

        return true;
    }

    /**
     * Ask the gateway LLM for a retention period + generic basis per category.
     * Returns a map keyed by category type, or null on any failure (caller
     * falls back to the deterministic heuristic). The model is grounded in the
     * supplied category labels only - it is explicitly told not to invent data.
     *
     * @return array<string,array<string,mixed>>|null
     */
    private function suggestRetentionPeriods(array $categories): ?array
    {
        $llmClass = '\\AhgAiServices\\Services\\LlmService';
        if (! class_exists($llmClass)) {
            return null;
        }

        $lines = [];
        foreach ($categories as $c) {
            $lines[] = '- '.($c['type'] ?? 'other').': '.($c['label'] ?? '').' ('.((int) ($c['records'] ?? 0)).' records)';
        }
        $list = implode("\n", $lines);

        $prompt = <<<PROMPT
You are a records-management assistant helping an archive draft a defensible retention schedule.
A catalogue scan surfaced these categories of personal data (category key, human label, affected record count):
{$list}

For EACH category key, propose:
- retention_period: a short, defensible retention period (e.g. "7 years after last contact", "Permanent - archival value", "Until consent withdrawn + 1 year")
- legal_basis: a GENERIC, jurisdiction-neutral basis, e.g. "per the applicable data-protection retention regime and the institution's appraisal/retention policy". Do NOT name a specific country's law (no POPIA / GDPR / IPSAS by name); the per-market module supplies that.
- disposal_action: one of "Secure deletion", "Anonymise", "Transfer to permanent archive", "Periodic disposal review"
- rationale: one sentence explaining the choice, referring only to the category named.

Rules:
- Ground every suggestion ONLY in the category names above. Do NOT invent record contents, names, or facts.
- Stay jurisdiction-neutral. Frame periods/bases generically.
Return STRICT JSON only, an object keyed by the category key, each value an object with keys retention_period, legal_basis, disposal_action, rationale. No prose, no markdown.
PROMPT;

        try {
            $llm = app($llmClass);
            $raw = $llm->complete($prompt, [
                'temperature' => 0.1,
                'purpose' => 'compliance.retention_schedule',
                'data_scope' => 'metadata',
            ]);
            if (! is_string($raw) || trim($raw) === '') {
                return null;
            }
            $json = $this->extractJson($raw);
            if (! is_array($json)) {
                return null;
            }

            $out = [];
            foreach ($json as $key => $val) {
                if (! is_array($val)) {
                    continue;
                }
                $out[(string) $key] = [
                    'retention_period' => isset($val['retention_period']) ? (string) $val['retention_period'] : '',
                    'legal_basis' => isset($val['legal_basis']) ? (string) $val['legal_basis'] : null,
                    'disposal_action' => isset($val['disposal_action']) ? (string) $val['disposal_action'] : null,
                    'rationale' => isset($val['rationale']) ? (string) $val['rationale'] : null,
                ];
            }

            return $out ?: null;
        } catch (Throwable $e) {
            Log::warning('ahg-privacy autopilot: retention LLM suggestion failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Deterministic fallback used when the gateway is unavailable. */
    private function fallbackRetention(string $label): array
    {
        return [
            'retention_period' => 'Review against the applicable retention policy',
            'legal_basis' => 'Per the applicable data-protection retention regime and the institution\'s appraisal/retention policy.',
            'disposal_action' => 'Periodic disposal review',
            'rationale' => 'Auto-drafted placeholder for "'.$label.'"; complete the period and basis from your market module.',
        ];
    }

    /** Pull the first JSON object out of a model response (handles code fences). */
    private function extractJson(string $raw)
    {
        $s = trim($raw);
        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```[a-zA-Z]*\s*/', '', $s) ?? $s;
            $s = preg_replace('/\s*```$/', '', $s) ?? $s;
        }
        $start = strpos($s, '{');
        $end = strrpos($s, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($s, $start, $end - $start + 1), true);
    }

    private function clip(?string $s, int $max): string
    {
        $s = trim((string) $s);

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }
}
