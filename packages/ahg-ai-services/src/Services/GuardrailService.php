<?php

/**
 * GuardrailService - RAG / LLM dispatch guardrails for Heratio
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

namespace AhgAiServices\Services;

use AhgAiServices\Support\AiServicesSettings;

/**
 * Policy guardrails applied to every LLM / RAG dispatch (issue #141, AI
 * Governance & Sovereignty design doc Goal 4). Three guardrails:
 *
 *   1. allowed_data_scopes - a request's data scope must be permitted for the
 *      target provider; out-of-scope data sent to a cloud provider is blocked,
 *      and PII in cloud-bound prompts is masked.
 *   2. purpose limitation  - the request's declared purpose must be in the
 *      operator-sanctioned set, else it is blocked or flagged.
 *   3. grounding           - a RAG output is scored against its retrieved
 *      source bundle; poorly-grounded output is flagged as a possible
 *      hallucination.
 *
 * One operator mode governs enforcement strength: off | warn | mask | block.
 * The defaults make `warn` safe to deploy - guardrails compute and flag but
 * never block or mutate a prompt until the operator opts up.
 *
 * Pure and self-contained: construct with an explicit config array for tests,
 * or let it load from AiServicesSettings in production. It never throws and
 * never touches the network.
 */
class GuardrailService
{
    public const MODE_OFF   = 'off';
    public const MODE_WARN  = 'warn';
    public const MODE_MASK  = 'mask';
    public const MODE_BLOCK = 'block';

    /** @var array{mode:string,cloud_allowed_scopes:array,local_providers:array,sanctioned_purposes:array,grounding_threshold:float} */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? self::loadConfig();
    }

    /** Production config from AiServicesSettings; falls back to defaults. */
    public static function loadConfig(): array
    {
        if (class_exists(AiServicesSettings::class)) {
            try {
                return [
                    'mode'                 => AiServicesSettings::ragGuardrailMode(),
                    'cloud_allowed_scopes' => AiServicesSettings::ragCloudAllowedScopes(),
                    'local_providers'      => AiServicesSettings::ragLocalProviders(),
                    'sanctioned_purposes'  => AiServicesSettings::ragSanctionedPurposes(),
                    'grounding_threshold'  => AiServicesSettings::ragGroundingThreshold(),
                ];
            } catch (\Throwable $e) {
                // Settings table absent on a fresh install - fall through.
            }
        }

        return self::defaultConfig();
    }

    /** The shipped defaults - also the contract the AtoM-AHG port mirrors. */
    public static function defaultConfig(): array
    {
        return [
            'mode'                 => self::MODE_WARN,
            'cloud_allowed_scopes' => ['public', 'internal'],
            'local_providers'      => ['ollama'],
            'sanctioned_purposes'  => [
                'description_generation', 'summarization', 'translation',
                'entity_extraction', 'spellcheck', 'research_assistance',
                'metadata_enrichment',
            ],
            'grounding_threshold'  => 0.45,
        ];
    }

    public function mode(): string
    {
        return $this->config['mode'] ?? self::MODE_WARN;
    }

    /** True when the named provider sits outside the local trust domain. */
    public function isCloudProvider(string $provider): bool
    {
        $local = array_map('strtolower', (array) ($this->config['local_providers'] ?? ['ollama']));

        return !in_array(strtolower(trim($provider)), $local, true);
    }

    /**
     * Inspect a request before dispatch. Returns the decision plus
     * possibly-masked prompts. Never throws - a malformed request degrades to
     * an allow.
     *
     * @param array $req keys: provider, model, system_prompt, user_prompt, data_scope, purpose
     * @return array{action:string,reason:?string,mode:string,is_cloud:bool,data_scope:string,purpose:string,purpose_sanctioned:bool,pii_masked:int,system_prompt:string,user_prompt:string,flags:array}
     */
    public function inspect(array $req): array
    {
        $provider = (string) ($req['provider'] ?? '');
        $system   = (string) ($req['system_prompt'] ?? '');
        $user     = (string) ($req['user_prompt'] ?? '');
        $scope    = $this->normalise($req['data_scope'] ?? '', 'internal');
        $purpose  = $this->normalise($req['purpose'] ?? '', 'unspecified');
        $mode     = $this->mode();
        $isCloud  = $this->isCloudProvider($provider);

        $out = [
            'action'             => 'allow',
            'reason'             => null,
            'mode'               => $mode,
            'provider'           => $provider,
            'is_cloud'           => $isCloud,
            'data_scope'         => $scope,
            'purpose'            => $purpose,
            'purpose_sanctioned' => true,
            'pii_masked'         => 0,
            'system_prompt'      => $system,
            'user_prompt'        => $user,
            'flags'              => [],
        ];

        if ($mode === self::MODE_OFF) {
            return $out;
        }

        $enforce = ($mode === self::MODE_BLOCK);
        $mutate  = ($mode === self::MODE_MASK || $mode === self::MODE_BLOCK);

        // Guardrail 2 - purpose limitation.
        if ($purpose === 'unspecified') {
            $out['flags'][] = 'purpose_unspecified';
        } elseif (!in_array($purpose, (array) ($this->config['sanctioned_purposes'] ?? []), true)) {
            $out['purpose_sanctioned'] = false;
            $out['flags'][] = 'purpose_not_sanctioned';
            if ($enforce) {
                $out['action'] = 'block';
                $out['reason'] = "Purpose '{$purpose}' is not in the sanctioned set";

                return $out;
            }
        }

        // Guardrail 1 - data-scope enforcement. Only cloud providers leave the
        // local trust domain, so local dispatch may carry any scope.
        if ($isCloud) {
            $allowed = (array) ($this->config['cloud_allowed_scopes'] ?? []);
            if (!in_array($scope, $allowed, true)) {
                $out['flags'][] = 'data_scope_out_of_policy';
                if ($enforce) {
                    $out['action'] = 'block';
                    $out['reason'] = "Data scope '{$scope}' may not be sent to cloud provider '{$provider}'";

                    return $out;
                }
            }
            // PII masking on cloud-bound prompts (mask + block modes).
            if ($mutate) {
                [$maskedSystem, $countSystem] = $this->maskPii($system);
                [$maskedUser,   $countUser]   = $this->maskPii($user);
                $masked = $countSystem + $countUser;
                if ($masked > 0) {
                    $out['system_prompt'] = $maskedSystem;
                    $out['user_prompt']   = $maskedUser;
                    $out['pii_masked']    = $masked;
                    $out['flags'][]       = 'pii_masked';
                    if ($out['action'] === 'allow') {
                        $out['action'] = 'mask';
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Grounding / hallucination check on a RAG output. Scores how many of the
     * output's significant terms appear in the retrieved source bundle.
     * Returns null when no bundle was supplied (a plain completion - nothing
     * to ground against). Never throws.
     *
     * @param array $contextSources the RAG provenance bundle (source snippets)
     * @return array{grounding_score:float,grounded:bool,flag:?string,terms_checked:int}|null
     */
    public function checkGrounding(string $output, array $contextSources): ?array
    {
        if ($this->mode() === self::MODE_OFF || empty($contextSources)) {
            return null;
        }

        $context = strtolower(trim(implode(' ', array_map('strval', $contextSources))));
        if ($context === '') {
            return null;
        }

        $terms = $this->significantTerms($output);
        // Too few significant terms to judge grounding either way.
        if (count($terms) < 5) {
            return ['grounding_score' => 1.0, 'grounded' => true, 'flag' => null, 'terms_checked' => count($terms)];
        }

        $supported = 0;
        foreach ($terms as $term) {
            if (strpos($context, $term) !== false) {
                $supported++;
            }
        }
        $score     = round($supported / count($terms), 4);
        $threshold = (float) ($this->config['grounding_threshold'] ?? 0.45);
        $grounded  = $score >= $threshold;

        return [
            'grounding_score' => $score,
            'grounded'        => $grounded,
            'flag'            => $grounded ? null : 'low_grounding',
            'terms_checked'   => count($terms),
        ];
    }

    /**
     * Fold an inspect() decision and an optional checkGrounding() result into
     * the compact `guardrail` array attached to an LLM result and persisted on
     * the inference record.
     */
    public function summarize(array $inspect, ?array $grounding): array
    {
        $guardrail = [
            'mode'               => $inspect['mode'] ?? $this->mode(),
            'action'             => $inspect['action'] ?? 'allow',
            'data_scope'         => $inspect['data_scope'] ?? null,
            'purpose'            => $inspect['purpose'] ?? null,
            'purpose_sanctioned' => $inspect['purpose_sanctioned'] ?? true,
            'pii_masked'         => (int) ($inspect['pii_masked'] ?? 0),
            'flags'              => array_values((array) ($inspect['flags'] ?? [])),
        ];
        if (!empty($inspect['reason'])) {
            $guardrail['reason'] = $inspect['reason'];
        }
        if ($grounding !== null) {
            $guardrail['grounding_score'] = $grounding['grounding_score'];
            $guardrail['grounded']        = $grounding['grounded'];
            if (!empty($grounding['flag'])) {
                $guardrail['flags'][] = $grounding['flag'];
            }
        }
        $guardrail['flags'] = array_values(array_unique($guardrail['flags']));

        return $guardrail;
    }

    /**
     * Mask personally-identifiable patterns in a prompt. Jurisdiction-neutral:
     * email addresses and number sequences carrying 9+ digits (phone numbers,
     * national-ID / account numbers). Returns [maskedText, replacementCount].
     *
     * @return array{0:string,1:int}
     */
    public function maskPii(string $text): array
    {
        $count = 0;

        $text = preg_replace_callback(
            '/[\w.+-]+@[\w-]+\.[\w.-]+/u',
            function () use (&$count) {
                $count++;

                return '[REDACTED:email]';
            },
            $text
        ) ?? $text;

        // Digit runs (optionally separated by spaces / dashes / dots / parens)
        // carrying at least 9 digits - long enough to be a phone or ID number,
        // short enough date ranges like "1939-1945" (8 digits) are left alone.
        $text = preg_replace_callback(
            '/\+?\d[\d\s().-]{6,}\d/u',
            function ($m) use (&$count) {
                if (strlen(preg_replace('/\D/', '', $m[0])) >= 9) {
                    $count++;

                    return '[REDACTED:number]';
                }

                return $m[0];
            },
            $text
        ) ?? $text;

        return [$text, $count];
    }

    /**
     * Distinct significant terms in a string: lowercased runs of 5+ Latin
     * letters, minus a small stopword set. Used by the grounding check.
     *
     * @return string[]
     */
    public function significantTerms(string $text): array
    {
        preg_match_all('/[a-z]{5,}/', strtolower($text), $matches);

        $stop = array_flip([
            'which', 'their', 'there', 'these', 'those', 'where', 'about',
            'would', 'could', 'should', 'other', 'through', 'being', 'because',
            'between', 'during', 'before', 'after', 'while', 'within', 'first',
            'three', 'among', 'under', 'above', 'below', 'using', 'based',
            'including', 'various', 'several', 'however', 'therefore',
        ]);

        $terms = [];
        foreach ($matches[0] as $word) {
            if (!isset($stop[$word])) {
                $terms[$word] = true;
            }
        }

        return array_keys($terms);
    }

    /** Lowercase + trim a scalar, substituting a default when empty. */
    private function normalise($value, string $default): string
    {
        $v = strtolower(trim((string) $value));

        return $v !== '' ? $v : $default;
    }
}
