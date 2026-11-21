<?php

/**
 * NerEvidenceScorer - Service for Heratio
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1501 - an evidence score for NER entities the extractor left unscored.
 *
 * Written to ahg_ner_entity.evidence_score / evidence_method / evidence_reasons,
 * NEVER to `confidence`, which keeps meaning "the extractor said this".
 *
 * Two methods:
 *
 * rules (CPU, no network). The first signal found wins, strongest first:
 *   reviewer decisions on the same name+type elsewhere  0.85 accepted / 0.15 rejected / mixed in between
 *   active gazetteer entry                              0.90 same type / 0.25 other type
 *   authority record or place term with that name       0.80 same kind / 0.25 other kind
 *   shape of the value                                  0.20 - 0.60
 * Shape alone never reaches 0.8, so a threshold of 0.8 means "something in the
 * catalogue backs this". Ceiling 0.90: a match is never certainty, the same
 * shape PiiScanService uses.
 *
 * llm_verify (GPU through the gateway, only when asked). A small model is asked
 * whether the value is of its type in the record's own text, and the score is
 * P(yes) / (P(yes) + P(no)) from the first answer token's log-probabilities.
 */
class NerEvidenceScorer
{
    public const METHOD_RULES  = 'rules';
    public const METHOD_VERIFY = 'llm_verify';

    private const PERSONISH = ['PERSON', 'PER', 'ORG', 'NORP'];
    private const PLACEISH  = ['GPE', 'LOC', 'FAC'];
    public const  SCORABLE  = ['PERSON', 'PER', 'ORG', 'NORP', 'GPE', 'LOC', 'FAC', 'DATE'];

    private const ACCEPTED = ['linked', 'approved', 'accepted'];
    private const REJECTED = ['reject', 'rejected'];

    private const QUESTION = [
        'PERSON' => 'the name of a person',
        'PER'    => 'the name of a person',
        'ORG'    => 'the name of an organisation',
        'NORP'   => 'a nationality, religious or political group',
        'GPE'    => 'the name of a place',
        'LOC'    => 'the name of a place',
        'FAC'    => 'the name of a building or facility',
        'DATE'   => 'a date or period',
    ];

    /** @var array<string,array{0:float,1:string}> per-run cache of type|value => [score, reasons] */
    private array $cache = [];

    /** @var array<string,string>|null lowercased gazetteer label/alias => entity_type */
    private ?array $gazetteer = null;

    /**
     * Rules-score the given entity ids. Skips rows the extractor scored, types
     * with no rule, and rows already scored unless $rescore. Never throws: a
     * scoring failure must not lose an extraction.
     */
    public function scoreIds(array $ids, bool $rescore = false, bool $write = true): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return 0;
        }
        $n = 0;
        try {
            $rows = DB::table('ahg_ner_entity')->whereIn('id', $ids)->whereNull('confidence')
                ->whereIn('entity_type', self::SCORABLE)
                ->when(! $rescore, fn ($q) => $q->whereNull('evidence_score'))
                // A model check is the stronger signal; rules never overwrite it.
                ->where(fn ($q) => $q->whereNull('evidence_method')->orWhere('evidence_method', '<>', self::METHOD_VERIFY))
                ->get(['id', 'object_id', 'entity_type', 'entity_value']);
            foreach ($rows as $row) {
                [$score, $reasons] = $this->rulesScore((string) $row->entity_type, (string) $row->entity_value, (int) $row->object_id);
                if ($write) {
                    $this->store((int) $row->id, $score, self::METHOD_RULES, $reasons);
                }
                $n++;
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] NER evidence scoring failed: ' . $e->getMessage());
        }

        return $n;
    }

    /**
     * @return array{0:float,1:string} [score, reasons]
     */
    public function rulesScore(string $type, string $value, int $objectId = 0): array
    {
        $type = strtoupper($type);
        $value = trim($value);
        $key = $type . '|' . mb_strtolower($value) . '|' . $objectId;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $result = $this->reviewerSignal($type, $value, $objectId)
            ?? $this->gazetteerSignal($type, $value)
            ?? $this->authoritySignal($type, $value)
            ?? $this->shapeSignal($type, $value);

        return $this->cache[$key] = [round($result[0], 4), mb_substr($result[1], 0, 255)];
    }

    /** Earlier human decisions on the same name and type, on other records. */
    private function reviewerSignal(string $type, string $value, int $objectId): ?array
    {
        $counts = DB::table('ahg_ner_entity')->where('entity_type', $type)->where('entity_value', $value)
            ->where('object_id', '<>', $objectId)
            ->selectRaw('SUM(status IN (' . $this->placeholders(self::ACCEPTED) . ')) a, SUM(status IN (' . $this->placeholders(self::REJECTED) . ')) r',
                array_merge(self::ACCEPTED, self::REJECTED))
            ->first();
        $a = (int) ($counts->a ?? 0);
        $r = (int) ($counts->r ?? 0);
        if ($a + $r === 0) {
            return null;
        }
        if ($r === 0) {
            return [0.85, "accepted by a reviewer on {$a} other record(s)"];
        }
        if ($a === 0) {
            return [0.15, "rejected by a reviewer on {$r} other record(s)"];
        }

        return [0.15 + 0.70 * $a / ($a + $r), "reviewers accepted {$a} and rejected {$r} elsewhere"];
    }

    /** The operator's own curated list (ahg_ner_custom_entity). */
    private function gazetteerSignal(string $type, string $value): ?array
    {
        if ($this->gazetteer === null) {
            $this->gazetteer = [];
            try {
                foreach (DB::table('ahg_ner_custom_entity')->where('is_active', 1)->get(['entity_type', 'label', 'aliases']) as $g) {
                    $names = [(string) $g->label];
                    foreach ((array) json_decode((string) $g->aliases, true) as $alias) {
                        if (is_string($alias)) {
                            $names[] = $alias;
                        }
                    }
                    foreach ($names as $name) {
                        if (trim($name) !== '') {
                            $this->gazetteer[mb_strtolower(trim($name))] = strtolower((string) $g->entity_type);
                        }
                    }
                }
            } catch (\Throwable) {
                // table absent: no gazetteer signal
            }
        }
        $gType = $this->gazetteer[mb_strtolower($value)] ?? null;
        if ($gType === null) {
            return null;
        }
        $kind = match (true) {
            in_array($gType, ['person', 'persons'], true) => 'PERSON',
            in_array($gType, ['organization', 'organisations', 'org'], true) => 'ORG',
            in_array($gType, ['place', 'places', 'location'], true) => 'PLACE',
            in_array($gType, ['date', 'dates'], true) => 'DATE',
            default => null,
        };
        if ($kind === null) {
            return [0.60, 'in the gazetteer as ' . $gType];
        }

        return $kind === $this->kind($type)
            ? [0.90, 'in the gazetteer']
            : [0.25, 'in the gazetteer as ' . strtolower($kind) . ', not ' . strtolower($this->kind($type))];
    }

    /** An existing authority record (people, bodies) or place term with exactly this name. */
    private function authoritySignal(string $type, string $value): ?array
    {
        $kind = $this->kind($type);
        if ($kind === 'DATE') {
            return null;
        }
        $isActor = DB::table('actor_i18n')->where('authorized_form_of_name', $value)->exists();
        $isPlace = DB::table('term_i18n')->join('term', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 42)->where('term_i18n.name', $value)->exists();
        if ($kind === 'PLACE') {
            if ($isPlace) {
                return [0.80, 'matches a place term'];
            }
            if ($isActor) {
                return [0.25, 'matches an authority record for a person or body, not a place'];
            }
        } else {
            if ($isActor) {
                return [0.80, 'matches an authority record'];
            }
            if ($isPlace) {
                return [0.25, 'matches a place term, not a person or body'];
            }
        }

        return null;
    }

    /** Fallback: does the value look like what it is tagged as. Never above 0.60. */
    private function shapeSignal(string $type, string $value): array
    {
        $value = (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}.]+$/u', '', $value); // "(Institute of Directors"
        $tokens = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($tokens);
        $capital = (bool) preg_match('/^\p{Lu}/u', $value);
        // "the University of ..." - a name with a lower-case word caught in front.
        $leadIn = ! $capital && $count > 1 && preg_match('/^\p{Lu}/u', $tokens[1]);
        $digits = (bool) preg_match('/\d/u', $value);

        if ($this->kind($type) === 'DATE') {
            if (preg_match('/\b(1\d{3}|20\d{2})\b/', $value)) {
                return [0.60, 'shape: contains a year'];
            }
            if (preg_match('/\b\d{1,2}[\/.\-]\d{1,2}\b/', $value)) {
                return [0.50, 'shape: a numeric date'];
            }

            return [0.30, 'shape: no year in the date'];
        }
        if ($count === 0 || mb_strlen($value) < 2) {
            return [0.20, 'shape: too short'];
        }
        if ($digits) {
            return [0.20, 'shape: contains digits'];
        }
        if ($leadIn) {
            return [0.40, 'shape: name with a lower-case word in front'];
        }
        if (! $capital) {
            return [0.25, 'shape: does not start with a capital'];
        }

        return match ($this->kind($type)) {
            'PERSON' => match (true) {
                $count > 5 => [0.25, 'shape: too many words for a name'],
                $count === 1 => [0.35, 'shape: a single word'],
                default => [0.50, 'shape: name-like'],
            },
            'ORG' => $count > 8 ? [0.25, 'shape: too many words for a name'] : [0.45, 'shape: name-like'],
            default => $count > 4 ? [0.25, 'shape: too many words for a place'] : [0.45, 'shape: name-like'],
        };
    }

    /**
     * Ask a small model through the AI gateway (uses a GPU). Writes and returns
     * the score, or null when the gateway did not answer usefully - in which
     * case the row is left as it was.
     */
    public function verify(int $entityId, string $model): ?float
    {
        $row = DB::table('ahg_ner_entity')->where('id', $entityId)->first(['id', 'object_id', 'entity_type', 'entity_value']);
        $question = $row ? (self::QUESTION[strtoupper((string) $row->entity_type)] ?? null) : null;
        if (! $question) {
            return null;
        }
        $context = $this->contextFor((int) $row->object_id, (string) $row->entity_value);
        if ($context === '') {
            return null;
        }
        $prompt = "Text: {$context}\n\nQuestion: In the text above, is \"{$row->entity_value}\" {$question}? Answer yes or no.\nAnswer:";

        $response = Http::timeout(120)
            ->withHeaders(['Authorization' => 'Bearer ' . (string) AiServicesSettings::gatewayKey()])
            ->post($this->gatewayBase() . '/ollama/api/generate', [
                'model' => $model, 'prompt' => $prompt, 'stream' => false,
                'logprobs' => true, 'top_logprobs' => 10,
                'options' => ['temperature' => 0, 'num_predict' => 1],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('gateway HTTP ' . $response->status() . ': ' . mb_substr((string) $response->body(), 0, 200));
        }
        $p = self::yesProbability((array) ($response->json('logprobs.0.top_logprobs') ?? []));
        if ($p === null) {
            return null;
        }
        // Clamped so a model's 0.9999 is never stored as the 1.0 #1480 removed.
        $p = round(max(0.01, min(0.99, $p)), 4);
        $this->store($entityId, $p, self::METHOD_VERIFY, $model . ' P(yes)=' . number_format($p, 2));

        return $p;
    }

    /**
     * P(yes) / (P(yes) + P(no)) over the first token's alternatives. Null when
     * neither answer is among them.
     *
     * @param list<array{token?:string,logprob?:float}> $top
     */
    public static function yesProbability(array $top): ?float
    {
        $yes = $no = 0.0;
        foreach ($top as $t) {
            $tok = strtolower(trim((string) ($t['token'] ?? '')));
            $prob = exp((float) ($t['logprob'] ?? -INF));
            if ($tok === 'yes') {
                $yes += $prob;
            } elseif ($tok === 'no') {
                $no += $prob;
            }
        }

        return ($yes + $no) > 0 ? $yes / ($yes + $no) : null;
    }

    /** About 800 characters of the record's text around the first mention. */
    private function contextFor(int $objectId, string $value): string
    {
        $rows = DB::table('information_object_i18n')->where('id', $objectId)->get(['title', 'scope_and_content']);
        $text = '';
        foreach ($rows as $r) {
            $t = trim(strip_tags((string) $r->title . "\n" . (string) $r->scope_and_content));
            if ($text === '' || mb_stripos($t, $value) !== false) {
                $text = $t;
                if (mb_stripos($t, $value) !== false) {
                    break;
                }
            }
        }
        $pos = mb_stripos($text, $value);
        $start = $pos === false ? 0 : max(0, $pos - 400);

        return trim(mb_substr($text, $start, 800));
    }

    /** The gateway base (never a GPU node), same resolution NerService uses. */
    private function gatewayBase(): string
    {
        $url = (string) (DB::table('ahg_ner_settings')->where('setting_key', 'api_url')->value('setting_value')
            ?: AiServicesSettings::apiUrl() ?: '');
        if ($url === '' || preg_match('~:11434|:5004|://(?:127\.0\.0\.1|localhost|192\.168\.|10\.|172\.(?:1[6-9]|2\d|3[01])\.)~i', $url)) {
            $url = 'https://ai.theahg.co.za/ai/v1';
        }

        return rtrim($url, '/');
    }

    private function store(int $id, float $score, string $method, string $reasons): void
    {
        DB::table('ahg_ner_entity')->where('id', $id)->update([
            'evidence_score' => $score, 'evidence_method' => $method,
            'evidence_reasons' => mb_substr($reasons, 0, 255), 'evidence_scored_at' => now(),
        ]);
    }

    private function kind(string $type): string
    {
        $type = strtoupper($type);

        return match (true) {
            in_array($type, ['PERSON', 'PER'], true) => 'PERSON',
            in_array($type, self::PERSONISH, true) => 'ORG',
            in_array($type, self::PLACEISH, true) => 'PLACE',
            default => 'DATE',
        };
    }

    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
