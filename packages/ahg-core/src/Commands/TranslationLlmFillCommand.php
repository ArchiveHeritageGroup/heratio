<?php

/**
 * ahg:translation-llm-fill - fill missing UI strings for a locale through the
 * AI gateway's LLM chat route (heratio#1510, #1416, #1419).
 *
 * The gateway's /ai/v1/translate route only serves en/af and a generic "bnt"
 * model (#1419), so ahg:translation-mt-batch cannot reach most locales. This
 * uses the route the Arabic fill used (qwen3.6:27b through
 * /ai/v1/ollama/api/chat): batches of 40 strings, JSON out, no thinking,
 * temperature 0.1, an archives/libraries/museums/galleries system prompt.
 *
 * A translation is written only when it is non-empty, keeps exactly the
 * source's placeholders (%1%, %s/%d, %{name}, :name, {{ var }}, HTML tags,
 * entities), is in the target script, and carries no stray English. Anything
 * else keeps the English fallback. Only missing keys and English stubs
 * (value == key) are filled; existing translations are never overwritten.
 * Progress is checkpointed every 10 batches so a run can resume, and the
 * locale is flagged machine-translated in lang/_meta.json for native-speaker
 * review (#1445).
 *
 * Afrikaans is refused: per #1410 the LLM must not seed af (use
 * ahg:translation-mt-batch af, which goes to the gateway's NLLB/opus model).
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

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TranslationLlmFillCommand extends Command
{
    protected $signature = 'ahg:translation-llm-fill
        {locales : Comma-separated locale codes, filled in that order}
        {--model=qwen3.6:27b : Gateway model}
        {--batch=40 : Strings per model call}
        {--limit=0 : Stop a locale after this many accepted strings (0 = all)}
        {--until= : Stop the whole run at this time on the host clock, HH:MM (an overnight window may cross midnight)}
        {--dry-run : Count what would be translated; call nothing, write nothing}';

    protected $description = 'Fill missing UI strings for one or more locales through the AI gateway LLM route, with placeholder and script checks';

    private const URL = 'https://ai.theahg.co.za/ai/v1/ollama/api/chat';

    /** Locale prefix => regex for a character of its script. Latin-script locales are absent. */
    private const SCRIPTS = [
        'ar' => '\p{Arabic}', 'fa' => '\p{Arabic}', 'ur' => '\p{Arabic}', 'he' => '\p{Hebrew}',
        'zh' => '\p{Han}', 'ja' => '[\p{Han}\p{Hiragana}\p{Katakana}]', 'ko' => '\p{Hangul}',
        'th' => '\p{Thai}', 'ka' => '\p{Georgian}', 'ta' => '\p{Tamil}', 'el' => '\p{Greek}',
        'ru' => '\p{Cyrillic}', 'uk' => '\p{Cyrillic}', 'mk' => '\p{Cyrillic}', 'sr' => '\p{Cyrillic}',
        'am' => '\p{Ethiopic}',
    ];

    /** Locales the LLM must not seed (see the class comment). */
    private const REFUSED = ['af', 'en'];

    private ?int $deadline = null;

    /** Seconds to wait before each retry of a batch the gateway did not answer. */
    private const RETRY_WAITS = [30, 120, 300];

    private const MAX_FAILED_IN_A_ROW = 5;

    private int $failedInARow = 0;

    private bool $stopRun = false;

    public function handle(): int
    {
        if ($until = $this->option('until')) {
            if (! preg_match('/^(\d{1,2}):(\d{2})$/', $until, $m)) {
                $this->error('--until must be HH:MM');

                return self::FAILURE;
            }
            // The host's local time (cron and the operator's clock), not the
            // application timezone, which may be UTC: 06:00 means 06:00 here.
            $tz = getenv('TZ') ?: (trim((string) @file_get_contents('/etc/timezone')) ?: config('app.timezone'));
            $local = now($tz);
            $t = $local->copy()->setTime((int) $m[1], (int) $m[2]);
            $this->deadline = ($t->lessThanOrEqualTo($local) ? $t->addDay() : $t)->getTimestamp();
        }

        $source = json_decode((string) file_get_contents(base_path('lang/en.json')), true) ?: [];
        foreach (array_filter(array_map('trim', explode(',', (string) $this->argument('locales')))) as $locale) {
            if (in_array($locale, self::REFUSED, true)) {
                $this->warn("{$locale}: skipped (the LLM must not seed this locale; see heratio#1410).");

                continue;
            }
            if ($this->pastDeadline()) {
                $this->info('Stop time reached.');
                break;
            }
            if ($this->stopRun) {
                break;
            }
            $this->fillLocale($locale, $source);
        }

        return self::SUCCESS;
    }

    private function fillLocale(string $locale, array $source): void
    {
        $path = base_path("lang/{$locale}.json");
        $target = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        // Strings the model already handed back unchanged (paths, codes, words
        // spelt the same in both languages): the English fallback is right for
        // them, so they are not sent again.
        $same = array_flip($this->meta()['locales'][$locale]['mt_same_as_source'] ?? []);
        $todo = array_values(array_filter(array_keys($source), fn ($k) => preg_match('/\p{L}.*\p{L}/u', (string) $k)
            && (! isset($target[$k]) || $target[$k] === $k) && ! isset($same[$k])));
        $this->info("{$locale}: ".count($todo).' string(s) to fill');
        if ($this->option('dry-run') || ! $todo) {
            return;
        }

        $name = (class_exists(\Locale::class) ? \Locale::getDisplayName(str_replace('@valencia', '', $locale), 'en') : '') ?: $locale;
        if ($locale === 'ca@valencia') {
            $name = 'Valencian (Catalan as written in Valencia)';
        }
        $limit = (int) $this->option('limit');
        $ok = $rejected = $batches = $untried = 0;
        $newSame = [];
        foreach (array_chunk($todo, max(1, (int) $this->option('batch'))) as $chunk) {
            if ($this->pastDeadline() || $this->stopRun || ($limit && $ok >= $limit)) {
                break;
            }
            $reply = $this->translateBatch($chunk, $name);
            for ($try = 0; $reply === null && $try < count(self::RETRY_WAITS) && ! $this->pastDeadline(); $try++) {
                sleep(self::RETRY_WAITS[$try]);
                $reply = $this->translateBatch($chunk, $name);
            }
            if ($reply === null) {
                // The gateway did not answer: the batch is untried, not rejected,
                // and the next run picks it up. Several in a row means the node is
                // busy or down; stop rather than run through the queue unanswered.
                $untried += count($chunk);
                if (++$this->failedInARow >= self::MAX_FAILED_IN_A_ROW) {
                    $this->stopRun = true;
                    $this->warn("  {$locale}: the gateway failed ".self::MAX_FAILED_IN_A_ROW.' batches in a row; stopping this run.');
                    break;
                }

                continue;
            }
            $this->failedInARow = 0;
            foreach ($chunk as $i => $en) {
                $out = $reply['s'.$i] ?? $reply[$en] ?? null;
                if (is_string($out) && trim($out) === $en) {
                    $newSame[] = $en;
                } elseif (is_string($out) && $this->acceptable($en, trim($out), $locale)) {
                    $target[$en] = self::plainDashes(trim($out));
                    $ok++;
                } else {
                    $rejected++;
                }
            }
            if (++$batches % 10 === 0) {
                $this->save($path, $target);
                $this->line("  {$locale}: {$ok} accepted, {$rejected} kept English (checkpoint)");
            }
        }
        $this->save($path, $target);
        $this->recordMeta($locale, $ok, $newSame);
        $this->info("{$locale}: {$ok} accepted, ".count($newSame)." same as English, {$rejected} rejected (kept English), {$untried} untried (gateway did not answer)");
    }

    /** @return array<string, string>|null null when the gateway did not answer */
    private function translateBatch(array $chunk, string $language): ?array
    {
        $items = [];
        foreach ($chunk as $i => $en) {
            $items['s'.$i] = $en;
        }
        $system = "You translate the user interface of an archival management system used by archives, libraries, museums and galleries into {$language}. "
            .'Use the terms archivists and librarians use in that language. Keep every placeholder exactly as written: %1%, %2%, %s, %d, %{name}, :name, {{ var }}, HTML tags and entities. '
            .'Keep acronyms and standard names (ISAD(G), ISAAR, EAD, OAI-PMH, IIIF, RiC, CSV, PDF, URL, API, ICIP) unchanged. Do not add explanations. '
            .'Reply with a JSON object mapping each id to its translation, with the same ids.';
        try {
            $resp = Http::withToken((string) $this->gatewayKey())->timeout(600)->post(self::URL, [
                'model' => (string) $this->option('model'), 'stream' => false, 'format' => 'json', 'think' => false,
                'options' => ['temperature' => 0.1],
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($items, JSON_UNESCAPED_UNICODE)]],
            ]);
            if (! $resp->successful()) {
                $this->warn('  gateway answered '.$resp->status().': '.mb_substr($resp->body(), 0, 160));

                return null;
            }
            $data = json_decode((string) $resp->json('message.content', ''), true);

            // An answer that is not a JSON object counts as answered-but-unusable.
            return is_array($data) ? array_filter($data, 'is_string') : [];
        } catch (\Throwable $e) {
            $this->warn('  gateway call failed: '.$e->getMessage());

            return null;
        }
    }

    /** The acceptance rules from heratio#1510. */
    public function acceptable(string $en, string $out, string $locale): bool
    {
        if ($out === '' || mb_strlen($out) > max(40, mb_strlen($en) * 4)) {
            return false;
        }
        if (self::placeholders($en) !== self::placeholders($out)) {
            return false;
        }
        $script = self::SCRIPTS[explode('_', explode('@', $locale)[0])[0]] ?? null;
        $plain = (string) preg_replace('/%\d+%|%[sd]|%\{\w+\}|:[a-z_]+|\{\{.*?\}\}|<[^>]+>|&\w+;/u', ' ', $out);
        if ($script !== null) {
            if (! preg_match('/'.$script.'/u', $plain)) {
                return false;
            }
            // Latin letters glued to the target script, or English words the source does not have.
            if (preg_match('/[A-Za-z]'.$script.'|'.$script.'[A-Za-z]/u', $plain)) {
                return false;
            }
            preg_match_all('/[A-Za-z]{3,}/', $plain, $latin);
            foreach ($latin[0] as $w) {
                // A lowercase English word is leftover English; a name or an
                // acronym may stay, but only one the source has.
                if ($w === strtolower($w) || strpos($en, $w) === false) {
                    return false;
                }
            }
        } elseif (mb_strtolower($out) === mb_strtolower($en) && preg_match('/[a-z]{4,}/', $en)) {
            return false;   // Latin script: an unchanged English string is no translation
        }

        return true;
    }

    /** House style: plain hyphens, never em or en dashes. */
    public static function plainDashes(string $s): string
    {
        return strtr($s, ["\u{2014}" => '-', "\u{2013}" => '-', "\u{2015}" => '-']);
    }

    /** @return list<string> sorted placeholder multiset */
    public static function placeholders(string $s): array
    {
        preg_match_all('/%\d+%|%[sd]|%\{\w+\}|(?<![\w:]):[a-z_]+\b|\{\{.*?\}\}|<\/?[a-zA-Z][^>]*>|&[a-zA-Z]+;|&#\d+;/u', $s, $m);
        $p = array_map(fn ($t) => preg_replace('/\s+/', '', $t), $m[0]);
        sort($p);

        return $p;
    }

    private function save(string $path, array $target): void
    {
        $json = json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        $tmp = $path.'.tmp';
        file_put_contents($tmp, $json);
        rename($tmp, $path);   // atomic: a release taken mid-run never sees half a file
    }

    private function meta(): array
    {
        return json_decode((string) @file_get_contents(base_path('lang/_meta.json')), true) ?: ['locales' => []];
    }

    private function recordMeta(string $locale, int $ok, array $same = []): void
    {
        if ($ok === 0 && $same === []) {
            return;
        }
        $path = base_path('lang/_meta.json');
        $meta = $this->meta();
        $entry = $meta['locales'][$locale] ?? [];
        if ($same) {
            $entry['mt_same_as_source'] = array_values(array_unique(array_merge($entry['mt_same_as_source'] ?? [], $same)));
        }
        if ($ok === 0) {
            $meta['locales'][$locale] = $entry;
            $this->save($path, $meta);

            return;
        }
        $entry['mt_batch_last_run'] = date('Y-m-d');
        $entry['mt_batch_count'] = (int) ($entry['mt_batch_count'] ?? 0) + $ok;
        $entry['mt_batch_model'] = $this->option('model').' via gateway /ai/v1/ollama/api/chat (ahg:translation-llm-fill)';
        $entry['review_required'] = 'native-speaker review required before production';
        $meta['locales'][$locale] = $entry;
        $this->save($path, $meta);
    }

    private function pastDeadline(): bool
    {
        return $this->deadline !== null && time() >= $this->deadline;
    }

    private function gatewayKey(): ?string
    {
        if (class_exists(\AhgAiServices\Support\AiServicesSettings::class)) {
            return \AhgAiServices\Support\AiServicesSettings::gatewayKey();
        }

        return DB::table('ahg_ai_settings')->where('feature', 'general')->where('setting_key', 'api_key')->value('setting_value');
    }
}
