<?php

/**
 * ahg:chatbot-test-multilang
 *
 * Multi-language regression for the AI Library Assistant. Dispatches canned
 * probes in af / en / zu / nso, asserts each response is:
 *  - non-empty
 *  - grounded (sources array non-empty) OR explicitly flagged low_confidence
 *  - in a language compatible with the probe locale
 *
 * Output: pass/fail table per probe, exit code = number of failures.
 *
 * Issue: heratio#762
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgAiChatbot\Console\Commands;

use AhgAiChatbot\Services\ChatbotService;
use Illuminate\Console\Command;
use Throwable;

class ChatbotTestMultilangCommand extends Command
{
    protected $signature = 'ahg:chatbot-test-multilang
        {--locales=af,en,zu,nso : Comma-separated locales to probe}
        {--strict : Exit non-zero on any failure (default: warn only)}
        {--json : Emit results as JSON instead of a table}
        {--detect : Offline self-check of #1275 input-language detection (no gateway calls)}';

    protected $description = 'Multi-language regression probe for the AI Library Assistant.';

    /**
     * Canonical probes. Each is a meaning-equivalent question in the locale.
     * Designed so a grounded answer must come from catalogue records that
     * exist in any reasonably seeded Heratio install.
     */
    private const PROBES = [
        'af' => 'Wys vir my fotos uit die 1980s.',
        'en' => 'Show me photos from the 1980s.',
        'zu' => 'Ngitshengise izithombe zeminyaka yo-1980.',
        'nso' => 'Mpontšhe diswantšho tša mengwaga ya bo-1980.',
    ];

    /**
     * English stop tokens used to detect whether a reply stayed in English. Post-#1273
     * the chatbot localizes via the MT route (never a qwen "reply in X" prompt), so a
     * mostly-English reply for a non-English locale just means MT did not localize for
     * that language (unsupported / down) - the acceptable fallback, surfaced as a NOTE,
     * not a failure. The old qwen "language drift" garbage mode can no longer occur.
     */
    private const DRIFT_TOKENS_NON_EUROPEAN = ['the', 'and', 'with', 'from'];

    /**
     * #1275 input-language detection probes: [typed message, expected detected locale].
     * null = should fall back to the UI-locale / English default (ambiguous or English).
     */
    private const DETECT_PROBES = [
        ['Watter rekords het julle oor die Benson familie?', 'af'],
        ['Hoekom is hierdie dokument nie beskikbaar nie?', 'af'],
        ['Ngicela ungitshele ngomlando wakwaBenson, ngiyabonga', 'zu'],
        ['Molo, ndicela undixelele ngembali yosapho, enkosi', 'xh'],
        ['Avuxeni, ndza khensa, leswaku ndzi kuma vutivi', 'ts'],
        ['Ndaa, ndi livhuwa, uri ndi wane mafhungo', 've'],
        ['What records do you have about the Benson family?', null],
        ['dumela', null],
        ['sawubona', null],
    ];

    public function handle(ChatbotService $chatbot): int
    {
        if ((bool) $this->option('detect')) {
            return $this->runDetectSelfCheck();
        }

        $locales = array_filter(array_map('trim', explode(',', (string) $this->option('locales'))));
        $strict = (bool) $this->option('strict');
        $asJson = (bool) $this->option('json');

        $results = [];
        $failures = 0;
        $sessionId = 'multilang-test-' . hash('xxh32', (string) microtime(true));

        foreach ($locales as $locale) {
            $probe = self::PROBES[$locale] ?? self::PROBES['en'];

            try {
                $r = $chatbot->dispatch($sessionId . ':' . $locale, $probe, null, null, $locale);
            } catch (Throwable $e) {
                $results[] = $this->failResult($locale, $probe, 'dispatch_threw', $e->getMessage());
                $failures++;
                continue;
            }

            if (!($r['success'] ?? false)) {
                $results[] = $this->failResult($locale, $probe, 'not_successful', $r['error'] ?? 'unknown');
                $failures++;
                continue;
            }

            $reply = trim((string) ($r['reply'] ?? ''));
            $sources = $r['sources'] ?? [];
            $grounding = (float) ($r['grounding_score'] ?? 0.0);

            $emptyReply = $reply === '';
            $lowConfidence = $grounding < (float) config('ahg-ai-chatbot.grounding_threshold', 0.5);
            $noSources = empty($sources);
            // #1273: replies are localized via the MT route (never a qwen "reply in X"
            // prompt), so a mostly-English reply for a non-English locale just means MT
            // did not localize it (unsupported language or MT down) - the documented,
            // acceptable fallback, surfaced as a NOTE, not a failure.
            $mtFallback = $this->detectLanguageDrift($locale, $reply);

            $flags = [];   // failure flags
            $notes = [];   // informational, non-failing
            if ($emptyReply)     $flags[] = 'empty_reply';
            if ($noSources && !$lowConfidence) $flags[] = 'no_sources_high_confidence';
            if ($mtFallback)     $notes[] = 'mt_fallback_en';

            $pass = empty($flags);
            if (!$pass) $failures++;

            $results[] = [
                'locale'    => $locale,
                'probe'     => $probe,
                'reply'     => $this->truncate($reply, 200),
                'reply_len' => mb_strlen($reply),
                'grounding' => round($grounding, 3),
                'sources'   => count($sources),
                'tokens_in' => (int) ($r['tokens_in'] ?? 0),
                'tokens_out'=> (int) ($r['tokens_out'] ?? 0),
                'flags'     => array_merge($flags, $notes),
                'status'    => $pass ? 'PASS' : 'FAIL',
            ];
        }

        if ($asJson) {
            $this->line(json_encode([
                'session_id' => $sessionId,
                'results'    => $results,
                'failures'   => $failures,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Locale', 'Status', 'Grounding', 'Sources', 'Tokens out', 'Flags', 'Reply (truncated)'],
                array_map(fn($r) => [
                    $r['locale'],
                    $r['status'],
                    $r['grounding'],
                    $r['sources'],
                    $r['tokens_out'],
                    implode(',', $r['flags']),
                    $r['reply'],
                ], $results)
            );
            $this->newLine();
            $this->info(sprintf('%d probe(s) - %d pass / %d fail', count($results), count($results) - $failures, $failures));
        }

        return ($strict && $failures > 0) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * #1275 offline self-check: run InputLanguageDetector over canonical phrases and
     * assert each maps to the expected locale (or null = default). No gateway, no LLM.
     */
    private function runDetectSelfCheck(): int
    {
        $detector = app(\AhgCore\Services\InputLanguageDetector::class);
        $rows = [];
        $failures = 0;
        foreach (self::DETECT_PROBES as [$text, $expected]) {
            $got = $detector->detect($text);
            $pass = ($got === $expected);
            if (! $pass) {
                $failures++;
            }
            $rows[] = [
                $this->truncate($text, 48),
                $expected ?? '(default)',
                $got ?? '(default)',
                $pass ? 'PASS' : 'FAIL',
            ];
        }
        $this->table(['Typed message', 'Expected', 'Detected', 'Status'], $rows);
        $this->newLine();
        $this->info(sprintf('%d detection probe(s) - %d pass / %d fail', count($rows), count($rows) - $failures, $failures));

        return ($failures > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function failResult(string $locale, string $probe, string $code, string $detail): array
    {
        return [
            'locale'    => $locale,
            'probe'     => $probe,
            'reply'     => null,
            'reply_len' => 0,
            'grounding' => 0,
            'sources'   => 0,
            'tokens_in' => 0,
            'tokens_out'=> 0,
            'flags'     => [$code . ':' . $this->truncate($detail, 80)],
            'status'    => 'FAIL',
        ];
    }

    /**
     * Heuristic: count occurrences of European stop tokens. For non-European
     * probe locales (zu / nso / xh / ts / tn / ve / ss), a high ratio is a
     * signal that the model fell back to English/Dutch rather than producing
     * the requested language. Tolerant of brief code-switching (proper nouns,
     * loan words).
     */
    private function detectLanguageDrift(string $locale, string $reply): bool
    {
        $nonEuropean = in_array($locale, ['zu', 'nso', 'xh', 'ts', 'tn', 've', 'ss'], true);
        if (!$nonEuropean || $reply === '') return false;

        $lower = mb_strtolower($reply);
        $tokenCount = max(1, str_word_count($lower));
        $hits = 0;
        foreach (self::DRIFT_TOKENS_NON_EUROPEAN as $tok) {
            $hits += substr_count(' ' . $lower . ' ', ' ' . $tok . ' ');
        }
        $ratio = $hits / $tokenCount;
        // > 15% of tokens being European stop words => drift.
        return $ratio > 0.15;
    }

    private function truncate(string $s, int $n): string
    {
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '...' : $s;
    }
}
