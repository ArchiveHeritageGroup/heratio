<?php
/**
 * Heratio - generates EU AI Act Annex IV technical-documentation bundles.
 *
 * Pulls live data from ai_inference_log (Article 12 chain), ai_model_registry,
 * ai_risk_register (when available, sibling issue #724), and the application
 * config, then emits one Markdown bundle per AI service to
 * storage/ai-compliance/annex-iv/<service>-<YYYY-MM-DD>.md.
 *
 * Each emitted bundle is fingerprinted (SHA-256) and the fingerprint is
 * written into the tamper-evident inference chain via InferenceLogger so the
 * existence and content of every regulator-facing document is independently
 * verifiable.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Console\Commands;

use AhgAiCompliance\Models\AiModelRegistry;
use AhgAiCompliance\Services\InferenceLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AnnexIvCommand extends Command
{
    /** Services covered by the Annex IV generator. Mirrors install-model-registry.sql seed rows. */
    private const KNOWN_SERVICES = ['llm', 'htr', 'ner', 'donut', 'guardrail', 'translate'];

    protected $signature = 'ai-compliance:annex-iv
        {--service= : Restrict to a single service (llm|htr|ner|donut|guardrail|translate)}
        {--out= : Output directory (default: storage/ai-compliance/annex-iv)}
        {--pdf : Also emit a PDF rendering alongside each Markdown bundle (requires dompdf/dompdf)}';

    protected $description = 'Generate EU AI Act Annex IV technical-documentation bundles per AI service';

    public function handle(): int
    {
        $outDir = (string) ($this->option('out') ?: storage_path('ai-compliance/annex-iv'));
        if (!is_dir($outDir) && !@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");
            return self::FAILURE;
        }

        $services = $this->option('service')
            ? [$this->option('service')]
            : self::KNOWN_SERVICES;

        $written = [];
        foreach ($services as $service) {
            try {
                $path = $this->generateForService((string) $service, $outDir);
                if ($path !== null) {
                    $written[] = $path;
                    $this->info("  wrote {$path}");
                }
            } catch (Throwable $e) {
                $this->error("  FAILED for service '{$service}': " . $e->getMessage());
            }
        }

        $this->line('');
        $this->info(sprintf('Generated %d Annex IV bundle(s) in %s', count($written), $outDir));
        $this->line('Retention: at least 10 years from date of last placing on market (Article 11(3)).');

        return $written === [] ? self::FAILURE : self::SUCCESS;
    }

    private function generateForService(string $service, string $outDir): ?string
    {
        $today = Carbon::now()->format('Y-m-d');
        $path = rtrim($outDir, '/') . '/' . $service . '-' . $today . '.md';

        $model = AiModelRegistry::current($service);
        if ($model === null) {
            $this->warn("  no ai_model_registry entry for service '{$service}'; skipping");
            return null;
        }

        $stats = $this->inferenceStats($service);
        $risks = $this->riskRows($service);

        $doc = $this->renderDeclarationOfConformity($service, $model)
            . "\n\n---\n\n"
            . $this->renderAnnexIv($service, $model, $stats, $risks);

        // Best-effort write; preserves any earlier hash mismatch for diff.
        File::put($path, $doc);

        // Fingerprint the bundle into the Article 12 chain (against the
        // Markdown source - PDFs are derived renderings, not the canonical
        // artifact).
        $this->writeReceiptForBundle($service, $model, $doc, $path);

        if ($this->option('pdf')) {
            $pdfPath = $this->renderPdf($doc, $path);
            if ($pdfPath !== null) {
                $this->info("  wrote {$pdfPath}");
            }
        }

        return $path;
    }

    /**
     * Render the Markdown bundle to PDF via dompdf/dompdf. The PDF lands at
     * the same path as the Markdown source with a .pdf extension. PDFs are
     * derived artifacts and are NOT chain-fingerprinted - operators can
     * always re-render from the canonical Markdown.
     */
    private function renderPdf(string $markdown, string $mdPath): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->warn('  --pdf requested but dompdf/dompdf is not installed; skipping PDF render');
            return null;
        }
        if (!class_exists(\League\CommonMark\GithubFlavoredMarkdownConverter::class)) {
            $this->warn('  --pdf requested but league/commonmark is not installed; skipping PDF render');
            return null;
        }

        try {
            $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                'html_input'         => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $html = (string) $converter->convert($markdown);

            $document = '<!doctype html><html><head><meta charset="utf-8">'
                . '<style>'
                . 'body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.4; color: #111; }'
                . 'h1 { font-size: 16pt; border-bottom: 2px solid #333; padding-bottom: 4px; margin-top: 18pt; }'
                . 'h2 { font-size: 13pt; border-bottom: 1px solid #999; padding-bottom: 2px; margin-top: 14pt; }'
                . 'h3 { font-size: 11pt; margin-top: 12pt; }'
                . 'table { border-collapse: collapse; width: 100%; margin: 8pt 0; }'
                . 'th, td { border: 1px solid #999; padding: 4pt 6pt; vertical-align: top; }'
                . 'th { background: #eee; text-align: left; font-weight: bold; }'
                . 'code { background: #f4f4f4; padding: 1pt 3pt; font-family: DejaVu Sans Mono, monospace; font-size: 9pt; }'
                . 'pre { background: #f4f4f4; padding: 6pt; border: 1px solid #ddd; font-family: DejaVu Sans Mono, monospace; font-size: 9pt; white-space: pre-wrap; }'
                . 'hr { border: 0; border-top: 1px solid #ccc; margin: 12pt 0; }'
                . 'blockquote { border-left: 3px solid #999; padding: 2pt 8pt; color: #555; margin: 8pt 0; }'
                . '@page { margin: 18mm 14mm; }'
                . '</style></head><body>'
                . $html
                . '</body></html>';

            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
            ]);
            $dompdf->loadHtml($document, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfPath = preg_replace('/\.md$/', '.pdf', $mdPath);
            File::put($pdfPath, $dompdf->output());
            return $pdfPath;
        } catch (Throwable $e) {
            $this->warn('  PDF render failed for ' . basename($mdPath) . ': ' . $e->getMessage());
            return null;
        }
    }

    private function renderDeclarationOfConformity(string $service, AiModelRegistry $model): string
    {
        $templatePath = __DIR__ . '/../../../resources/templates/eu-declaration-of-conformity.md.template';
        $template = is_readable($templatePath)
            ? (string) file_get_contents($templatePath)
            : '# EU Declaration of Conformity (template missing)';

        $heratioVersion = $this->heratioVersion();
        $issueDate = Carbon::now()->format('Y-m-d');
        $bundleHashPlaceholder = '<computed-below>';

        $substitutions = [
            '{{SERVICE_NAME}}'         => $this->serviceLabel($service),
            '{{SERVICE}}'              => $service,
            '{{MODEL_ID}}'             => (string) $model->model_id,
            '{{MODEL_VERSION}}'        => (string) $model->model_version,
            '{{DEPLOYED_AT}}'          => $model->deployed_at ? $model->deployed_at->format('Y-m-d') : '',
            '{{HERATIO_VERSION}}'      => $heratioVersion,
            '{{SIGNING_PARTY_NAME}}'   => config('ahg.compliance.signing_party_name', 'Johan Pieterse'),
            '{{SIGNING_PARTY_ROLE}}'   => config('ahg.compliance.signing_party_role', 'Provider authorised representative'),
            '{{SIGNING_PARTY_EMAIL}}'  => config('ahg.compliance.signing_party_email', 'johan@theahg.co.za'),
            '{{PROVIDER_ADDRESS}}'     => config('ahg.compliance.provider_address', 'Plain Sailing Information Systems'),
            '{{PLACE_OF_ISSUE}}'       => config('ahg.compliance.place_of_issue', 'Republic of South Africa'),
            '{{ISSUE_DATE}}'           => $issueDate,
            '{{ANNEX_IV_HASH}}'        => $bundleHashPlaceholder,
        ];

        return strtr($template, $substitutions);
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<int,array<string,mixed>> $risks
     */
    private function renderAnnexIv(string $service, AiModelRegistry $model, array $stats, array $risks): string
    {
        $label = $this->serviceLabel($service);
        $heratioVersion = $this->heratioVersion();
        $generatedAt = Carbon::now()->toIso8601String();

        $accuracyMetrics = is_array($model->accuracy_metrics_json) ? $model->accuracy_metrics_json : [];
        $accuracyBlock = $accuracyMetrics === []
            ? '_No metrics recorded. Operator should update via /admin/ai-compliance/models._'
            : "```json\n" . json_encode($accuracyMetrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```";

        $riskBlock = $risks === []
            ? '_No risk-register entries are linked to this service yet (sibling issue #724 / Article 9). See `/admin/ai-compliance/risk` once that module is live._'
            : $this->riskTable($risks);

        $lifecycleRows = AiModelRegistry::forService($service)->get();
        $lifecycleTable = $this->lifecycleTable($lifecycleRows);

        $callCount = (int) ($stats['call_count'] ?? 0);
        $firstSeen = $stats['first_seen'] ?? null;
        $lastSeen = $stats['last_seen'] ?? null;

        return <<<MD
# Annex IV - Technical Documentation

> Heratio AI service **{$label}** (`{$service}`)
> Generated {$generatedAt}
> Heratio release `{$heratioVersion}`
> Article 11 of Regulation (EU) 2024/1689 ("EU AI Act") - documentation drawn up before placing on market and kept up to date. Retention: at least 10 years (Article 11(3)).

## 1. General description of the AI system

| Field | Value |
| --- | --- |
| Service | `{$service}` ({$label}) |
| Model identifier | `{$model->model_id}` |
| Model version | `{$model->model_version}` |
| Deployed | {$this->fmtDate($model->deployed_at)} |
| Gateway endpoint | `{$this->safeStr($model->gateway_endpoint)}` |
| Provider | Plain Sailing Information Systems |
| Heratio release | `{$heratioVersion}` |
| Intended purpose | {$this->safeStr($model->intended_purpose)} |
| Persons / groups on which the system is intended to be used | Archivists, researchers, museum curators, and authorised end users of Heratio-hosted GLAM platforms. Output is reviewed by a human before being relied upon. |
| Interactions with other systems | Invoked by Heratio packages `ahg-ai-services`, `ahg-research`, `ahg-information-object-manage`. Calls flow through the AHG AI gateway (`ai.theahg.co.za`). Every inference is recorded in `ai_inference_log` (Article 12). |

## 2. Detailed description of system elements and development process

- **Methods used** - The model is consumed as-is via the AHG AI gateway; Heratio does not retrain. Heratio's own contribution is the orchestration layer (prompt assembly, source grounding, post-processing) and the human-in-the-loop UI.
- **Design choices** - Defence-in-depth: rule-based guardrail (`guardrail` service) gates every prompt and response; rights-policy middleware (ODRL) enforces use-policies on archival material; receipts (Article 12, issue #693) make every call individually verifiable.
- **System architecture** - See `docs/reference/ai-compliance-article-12.md` for the receipts pipeline and `docs/reference/ai-gateway-htr-routing.md` for the gateway topology. Source code at `packages/ahg-ai-services/` and `packages/ahg-ai-compliance/`.
- **Computational resources** - Inference is remote (AHG gateway on `ai.theahg.co.za`). Heratio holds no GPU-bound workload.
- **Data requirements** (cross-ref Article 10 - data governance) - Training-data summary captured per-row in `ai_model_registry.training_data_summary`. For this row:

```
{$this->safeStr($model->training_data_summary)}
```

## 3. Information about monitoring, functioning and control

- **Capabilities** - {$this->safeStr($model->intended_purpose)}
- **Known limits** -

```
{$this->safeStr($model->known_limits)}
```

- **Appropriate level of accuracy** - The service is invoked **only** with a human reviewer in the loop. Heratio does not autonomously act on model output. Accuracy is therefore measured per output reviewed, not per call.
- **Accuracy metrics** -

{$accuracyBlock}

- **Live operational telemetry** (from `ai_inference_log` Article 12 chain):

| Metric | Value |
| --- | --- |
| Total calls logged | {$callCount} |
| First call observed | {$this->safeStr($firstSeen)} |
| Latest call observed | {$this->safeStr($lastSeen)} |

## 4. Performance metrics and foreseeable risks

Refer to section 3 for accuracy metrics. Foreseeable unintended outcomes documented under "Known limits". Specific risks reviewed under section 5.

Cross-cutting concerns - all services share these mitigations:

- Output never autonomously published; archivist confirms before commit.
- Source-grounded prompts (RAG) - model is not asked to invent facts when an archival record can answer.
- ODRL rights policies block use of material under restrictive licences from being surfaced to the model.
- Guardrail rules screen for PII leakage and copyright lift.

## 5. Risk management documentation (Article 9 cross-reference)

Risks linked to this service from `ai_risk_register` (sibling issue #724):

{$riskBlock}

## 6. Changes through the lifecycle

Every deployment of a new model version creates a new row in `ai_model_registry`. The retired model row is kept with `retired_at` populated so the lifecycle is fully reconstructable.

{$lifecycleTable}

## 7. Harmonised standards applied

Where harmonised standards under Article 40 of the AI Act have not yet been adopted, Heratio applies the following common specifications:

- ISO/IEC 42001:2023 (AI management systems) - aspirational alignment.
- ISO/IEC 23894:2023 (AI risk management) - aspirational alignment.
- NIST AI RMF v1.0 - guidance.

Alternative solutions adopted: tamper-evident receipts (Article 12, issue #693), source-grounding (RAG) for all generative endpoints, and a deterministic guardrail layer.

## 8. EU Declaration of conformity

The full EU Declaration of Conformity for this service is prepended to this bundle (above the `---` separator).

## 9. Post-market monitoring plan

The provider operates the following post-market monitoring controls:

1. **Continuous chain logging** - every inference call writes one row to `ai_inference_log`; deviations from expected call patterns surface immediately.
2. **Per-quarter review** - operator reviews `ai_inference_log` aggregates by service, error rate, and downstream archivist override rate.
3. **User feedback channel** - in-app feedback link on every AI-assisted output (research portal). Feedback is triaged into the risk register (Article 9).
4. **Incident reporting** - serious incidents are reported under Article 73 within the statutory window.
5. **Annual model review** - this Annex IV bundle is regenerated at least annually and on every model version change. New versions land in `storage/ai-compliance/annex-iv/` and previous versions are retained for at least 10 years (Article 11(3)).

> NOTE - Phase 2 follow-up: automate (1) anomaly detection on `ai_inference_log` and (2) the archivist-override capture path. Tracked as a TODO in the parent issue.

---

_Generated by `php artisan ai-compliance:annex-iv --service={$service}`. Source: `packages/ahg-ai-compliance/src/Console/Commands/AnnexIvCommand.php`._
MD;
    }

    /**
     * @return array<string,mixed>
     */
    private function inferenceStats(string $service): array
    {
        try {
            if (!Schema::hasTable('ai_inference_log')) {
                return [];
            }
            $row = DB::table('ai_inference_log')
                ->where('service', $service)
                ->selectRaw('COUNT(*) AS c, MIN(ts) AS first_ts, MAX(ts) AS last_ts')
                ->first();
            return [
                'call_count' => (int) ($row->c ?? 0),
                'first_seen' => $row->first_ts ?? null,
                'last_seen'  => $row->last_ts ?? null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function riskRows(string $service): array
    {
        try {
            if (!Schema::hasTable('ai_risk_register')) {
                return [];
            }
            return DB::table('ai_risk_register')
                ->where('service', $service)
                ->orderBy('id')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @param array<int,array<string,mixed>> $risks */
    private function riskTable(array $risks): string
    {
        $head = "| ID | Risk | Likelihood | Severity | Mitigation |\n| --- | --- | --- | --- | --- |";
        $rows = [];
        foreach ($risks as $r) {
            $rows[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $this->safeStr($r['id'] ?? ''),
                $this->safeStr($r['risk_description'] ?? $r['description'] ?? ''),
                $this->safeStr($r['likelihood'] ?? ''),
                $this->safeStr($r['severity'] ?? ''),
                $this->safeStr($r['mitigation'] ?? ''),
            );
        }
        return $head . "\n" . implode("\n", $rows);
    }

    private function lifecycleTable(iterable $rows): string
    {
        $head = "| Model | Version | Deployed | Retired | Endpoint |\n| --- | --- | --- | --- | --- |";
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| `%s` | `%s` | %s | %s | `%s` |',
                $this->safeStr($row->model_id),
                $this->safeStr($row->model_version),
                $this->fmtDate($row->deployed_at),
                $this->fmtDate($row->retired_at) ?: '_active_',
                $this->safeStr($row->gateway_endpoint),
            );
        }
        return $head . "\n" . implode("\n", $lines);
    }

    private function writeReceiptForBundle(string $service, AiModelRegistry $model, string $doc, string $path): void
    {
        try {
            /** @var InferenceLogger $logger */
            $logger = app(InferenceLogger::class);
            $logger->log(
                service:      'annex-iv',
                modelId:      (string) $model->model_id,
                modelVersion: (string) $model->model_version,
                inputBody:    $service . '|' . (string) $model->model_id . '|' . (string) $model->model_version,
                outputBody:   $doc,
                extra: [
                    'doc_path' => $path,
                ],
            );
        } catch (Throwable $e) {
            // The receipt is best-effort; the bundle on disk is the
            // authoritative artefact. Surface the failure but do not
            // abort the run.
            $this->warn('  (receipt chain write failed: ' . $e->getMessage() . ')');
        }
    }

    private function serviceLabel(string $service): string
    {
        return match ($service) {
            'llm'       => 'General language model (assistive)',
            'htr'       => 'Handwritten text recognition',
            'ner'       => 'Named-entity recognition',
            'donut'     => 'Document understanding / structured extraction',
            'guardrail' => 'Content-policy guardrail',
            'translate' => 'Machine translation (SADC + EU languages)',
            default     => $service,
        };
    }

    private function heratioVersion(): string
    {
        $versionFile = base_path('version.json');
        if (is_readable($versionFile)) {
            $json = json_decode((string) file_get_contents($versionFile), true);
            if (is_array($json) && isset($json['version'])) {
                return (string) $json['version'];
            }
        }
        return 'unknown';
    }

    private function fmtDate($value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return (string) $value;
    }

    private function safeStr($value): string
    {
        if ($value === null) {
            return '';
        }
        $s = is_scalar($value) ? (string) $value : json_encode($value);
        // Markdown-safe: collapse pipe chars in table cells.
        return str_replace(['|', "\r\n", "\r"], ['\\|', "\n", "\n"], $s);
    }
}
