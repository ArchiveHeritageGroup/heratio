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

use AhgPrivacy\Models\Dpia;
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

    /**
     * Stable name for the auto-drafted DPIA so a re-run refreshes the same row
     * rather than spawning a duplicate every scan.
     */
    private const AUTO_DPIA_NAME = 'Archival catalogue personal data (auto-drafted)';

    /** Human labels for the WP29 / Article 35(3) triggers DpiaRiskService emits. */
    private const TRIGGER_LABELS = [
        'special_category' => 'Special category (Art 9/10) data present',
        'large_scale_profiling' => 'Large-scale profiling / systematic monitoring',
        'biometric' => 'Biometric or genetic processing',
        'cross_border_non_adequate' => 'Cross-border transfer without documented safeguards',
    ];

    public function __construct(
        private ?DpiaRiskService $dpiaRisk = null,
        private ?DpiaService $dpia = null,
    ) {
        // Resolve lazily so existing callers that new-up the service still work.
        $this->dpiaRisk = $dpiaRisk ?? app(DpiaRiskService::class);
        $this->dpia = $dpia ?? app(DpiaService::class);
    }

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

    // ------------------------------------------------------------------
    // heratio#1199 DPIA slice
    // ------------------------------------------------------------------

    /**
     * Auto-draft a Data Protection Impact Assessment from a catalogue PII scan.
     *
     * The scan categories are folded into a synthetic processing-activity
     * payload and screened by the EXISTING DpiaRiskService (GDPR Article 35(3)
     * / WP29 high-risk triggers - see #1109). The screen decides whether a
     * DPIA is required; risk scoring is NOT reinvented here.
     *
     * When required, a draft Dpia is persisted (status=draft) via DpiaService
     * with the risk findings + a recommendation pre-filled into the standard
     * Article 35 fields, for a DPO to review and sign off through the existing
     * DPIA workflow. The draft is upserted by a stable name so a re-scan
     * refreshes it - but a draft a DPO has already advanced (review / completed
     * / archived) is left untouched.
     *
     * Jurisdiction-neutral: the narrative stays generic ("the applicable
     * data-protection regime"); the per-market module supplies the statute.
     * The gateway LLM, when reachable, only enriches the risk/measures prose
     * grounded in the category names - it never decides whether a DPIA is
     * required (that stays with the deterministic screen).
     *
     * @return array{
     *   required:bool, triggers:array<int,string>, trigger_labels:array<int,string>,
     *   note:string, source:string, dpia:?array<string,mixed>
     * }
     */
    public function draftDpia(array $scan): array
    {
        $categories = $scan['categories'] ?? [];
        $dataLabels = array_values(array_filter(array_map(
            static fn ($c) => (string) ($c['label'] ?? ''),
            $categories
        )));

        // Fold the scan into a normalised activity the risk screen understands.
        // National identifiers, payment cards and dates of birth are the kind
        // of identifying data that, at archival scale, pushes processing into
        // the "large-scale" band - we surface those category labels into the
        // haystack so the screen's keyword heuristics can see them.
        $screenInput = [
            'name' => self::AUTO_DPIA_NAME,
            'purpose' => 'Cataloguing, preservation and provision of access to archival records that contain personal data identified by an automated catalogue scan.',
            'categories_of_data' => $dataLabels ?: ['Personal data within records'],
            'categories_of_subjects' => ['Individuals named or identifiable in the records'],
            'transfers_outside_eea' => false,
            'safeguards' => '',
        ];

        $screen = $this->dpiaRisk->screen($screenInput);
        $triggers = $screen['triggers'] ?? [];
        $triggerLabels = array_map(
            fn (string $t) => self::TRIGGER_LABELS[$t] ?? ucfirst(str_replace('_', ' ', $t)),
            $triggers
        );

        if (! ($screen['high_risk'] ?? false)) {
            return [
                'required' => false,
                'triggers' => $triggers,
                'trigger_labels' => $triggerLabels,
                'note' => $screen['note'] ?? '',
                'source' => 'screen',
                'dpia' => null,
            ];
        }

        // High-risk: build the narrative (LLM-enriched when the gateway is up,
        // deterministic otherwise) and persist / refresh the draft DPIA.
        $narrative = $this->suggestDpiaNarrative($dataLabels, $triggerLabels);
        $source = $narrative === null ? 'heuristic' : 'llm';
        $narrative = $narrative ?? $this->fallbackDpiaNarrative($dataLabels, $triggerLabels);

        $existing = Dpia::query()->where('name', self::AUTO_DPIA_NAME)->first();
        if ($existing && $existing->status !== Dpia::STATUS_DRAFT) {
            // A DPO has already advanced this assessment - never clobber it.
            return [
                'required' => true,
                'triggers' => $triggers,
                'trigger_labels' => $triggerLabels,
                'note' => $screen['note'] ?? '',
                'source' => $source,
                'dpia' => $this->presentDpia($existing),
            ];
        }

        $payload = [
            'name' => self::AUTO_DPIA_NAME,
            'description' => $narrative['description'],
            'necessity_proportionality' => $narrative['necessity_proportionality'],
            'risks_to_subjects' => $narrative['risks_to_subjects'],
            'measures_to_mitigate' => $narrative['measures_to_mitigate'],
            'residual_risks' => $narrative['residual_risks'],
            'dpo_opinion' => null,
            'status' => Dpia::STATUS_DRAFT,
        ];

        if ($existing) {
            $dpia = $this->dpia->update($existing, $payload);
        } else {
            $dpia = $this->dpia->create($payload, optional(auth()->user())->id);
        }

        return [
            'required' => true,
            'triggers' => $triggers,
            'trigger_labels' => $triggerLabels,
            'note' => $screen['note'] ?? '',
            'source' => $source,
            'dpia' => $this->presentDpia($dpia),
        ];
    }

    /** The most recent auto-drafted DPIA (by stable name), presented for the page. */
    public function latestAutoDpia(): ?array
    {
        $dpia = Dpia::query()->where('name', self::AUTO_DPIA_NAME)->latest('updated_at')->first();

        return $dpia ? $this->presentDpia($dpia) : null;
    }

    /**
     * DPO accept on the auto-drafted DPIA: advance the draft into the formal
     * review stage of the existing DPIA workflow. From there the DPO completes
     * and signs it off on the DPIA register (which then back-fills any linked
     * ROPA row). Returns the refreshed presentation, or null if not draftable.
     */
    public function acceptDpia(int $id): ?array
    {
        $dpia = Dpia::query()->find($id);
        if (! $dpia || $dpia->status !== Dpia::STATUS_DRAFT) {
            return null;
        }
        $dpia = $this->dpia->moveToReview($dpia);

        return $this->presentDpia($dpia);
    }

    /** Shape a Dpia model for the autopilot page / JSON. */
    private function presentDpia(Dpia $dpia): array
    {
        return [
            'id' => (int) $dpia->id,
            'name' => (string) $dpia->name,
            'status' => (string) $dpia->status,
            'description' => (string) ($dpia->description ?? ''),
            'risks_to_subjects' => (string) ($dpia->risks_to_subjects ?? ''),
            'measures_to_mitigate' => (string) ($dpia->measures_to_mitigate ?? ''),
            'necessity_proportionality' => (string) ($dpia->necessity_proportionality ?? ''),
            'residual_risks' => (string) ($dpia->residual_risks ?? ''),
            'edit_url' => route('ahgprivacy.dpia.edit', ['id' => $dpia->id]),
            'updated_at' => optional($dpia->updated_at)->format('Y-m-d H:i'),
        ];
    }

    /**
     * Ask the gateway LLM for a DPIA narrative (description, necessity,
     * risks, measures, residual risk) grounded ONLY in the data-category and
     * trigger labels. Returns the five fields, or null on any failure (caller
     * falls back to the deterministic narrative). The model never decides
     * whether a DPIA is required - that is settled by the screen.
     *
     * @param array<int,string> $dataLabels
     * @param array<int,string> $triggerLabels
     * @return array<string,string>|null
     */
    private function suggestDpiaNarrative(array $dataLabels, array $triggerLabels): ?array
    {
        $llmClass = '\\AhgAiServices\\Services\\LlmService';
        if (! class_exists($llmClass)) {
            return null;
        }

        $cats = $dataLabels ? implode(', ', $dataLabels) : 'personal data within records';
        $trigs = $triggerLabels ? implode('; ', $triggerLabels) : 'high-risk processing';

        $prompt = <<<PROMPT
You are a data-protection assistant helping an archive draft a Data Protection Impact Assessment (DPIA) for processing already determined to be HIGH RISK.
A catalogue scan surfaced these categories of personal data: {$cats}.
The high-risk triggers identified are: {$trigs}.

Draft the assessment narrative. Provide:
- description: one or two sentences describing the processing and the personal data involved.
- necessity_proportionality: why the processing is necessary and proportionate for an archive's public-interest/archiving task.
- risks_to_subjects: the concrete risks to data subjects arising from the categories and triggers above.
- measures_to_mitigate: practical mitigation measures (access controls, field-level redaction, retention limits, audit logging, etc.).
- residual_risks: the residual risk after mitigation, stated plainly.

Rules:
- Ground every statement ONLY in the categories and triggers named above. Do NOT invent record contents, names, or facts.
- Stay jurisdiction-neutral: refer to "the applicable data-protection regime" - do NOT name a specific country's law (no POPIA / GDPR / IPSAS by name).
Return STRICT JSON only: an object with keys description, necessity_proportionality, risks_to_subjects, measures_to_mitigate, residual_risks. No prose, no markdown.
PROMPT;

        try {
            $llm = app($llmClass);
            $raw = $llm->complete($prompt, [
                'temperature' => 0.1,
                'purpose' => 'compliance.dpia_draft',
                'data_scope' => 'metadata',
            ]);
            if (! is_string($raw) || trim($raw) === '') {
                return null;
            }
            $json = $this->extractJson($raw);
            if (! is_array($json)) {
                return null;
            }

            $keys = ['description', 'necessity_proportionality', 'risks_to_subjects', 'measures_to_mitigate', 'residual_risks'];
            $out = [];
            foreach ($keys as $k) {
                $out[$k] = isset($json[$k]) ? trim((string) $json[$k]) : '';
            }
            // Require at least the risk + measures fields to consider the LLM useful.
            if ($out['risks_to_subjects'] === '' && $out['measures_to_mitigate'] === '') {
                return null;
            }
            foreach ($keys as $k) {
                if ($out[$k] === '') {
                    $out[$k] = $this->fallbackDpiaNarrative($dataLabels, $triggerLabels)[$k];
                }
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('ahg-privacy autopilot: DPIA LLM narrative failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Deterministic DPIA narrative used when the gateway is unavailable.
     *
     * @param array<int,string> $dataLabels
     * @param array<int,string> $triggerLabels
     * @return array<string,string>
     */
    private function fallbackDpiaNarrative(array $dataLabels, array $triggerLabels): array
    {
        $cats = $dataLabels ? implode(', ', $dataLabels) : 'personal data within records';
        $trigs = $triggerLabels ? implode('; ', $triggerLabels) : 'high-risk processing';

        return [
            'description' => 'Auto-drafted DPIA for the processing of archival records containing personal data ('.$cats.') surfaced by an automated catalogue scan.',
            'necessity_proportionality' => 'The processing is necessary to fulfil the institution\'s archiving and public-access task. Proportionality is supported by access controls, redaction of restricted records, and retention limits. Confirm necessity and proportionality against the applicable data-protection regime via your enabled market module.',
            'risks_to_subjects' => 'High-risk triggers identified by the screen: '.$trigs.'. Risks to data subjects include unauthorised disclosure, re-identification, and use beyond the archival purpose.',
            'measures_to_mitigate' => 'Field-level PII redaction on restricted records, role-based access controls, ODRL policy enforcement on access and reproduction, audit logging of access and edits, and a defensible retention schedule.',
            'residual_risks' => 'Residual risk is reduced to a level appropriate for public-interest archiving once the above measures are applied; the DPO should confirm acceptability against the applicable regime before sign-off.',
        ];
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
