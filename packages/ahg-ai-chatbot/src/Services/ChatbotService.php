<?php

/**
 * ChatbotService
 *
 * RAG-grounded chatbot engine. Orchestrates:
 *   1. retrieveCatalogue(query)  — Qdrant/KM retrieval
 *   2. buildRagPrompt(query, hits, history) — compose system + user prompt
 *   3. dispatch(userMessage, ...) — GuardrailService → LlmService → InferenceLogger
 *   4. saveMessage(...)          — persist to ahg_ai_chatbot_message
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgAiChatbot\Services;

use AhgAiChatbot\Services\QdrantRetriever;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    /** #1208 glossary injection: max place-names / subject terms surfaced per kind. */
    private const GLOSSARY_MAX_PER_KIND = 40;

    private QdrantRetriever $retriever;
    private \AhgAiServices\Services\GuardrailService $guardrail;
    private ChatbotSkillService $skills;
    private PreservationKnowledgeService $preservation;
    private int $maxHistory;
    private int $maxContext;
    private float $groundingThreshold;
    private ?string $systemPrompt;
    private string $temperature;
    private int $preservationPassages;

    public function __construct(
        ?QdrantRetriever $retriever = null,
        ?ChatbotSkillService $skills = null,
        ?PreservationKnowledgeService $preservation = null,
    ) {
        $this->retriever         = $retriever ?? new QdrantRetriever();
        $this->guardrail         = new \AhgAiServices\Services\GuardrailService();
        $this->skills            = $skills ?? new ChatbotSkillService();
        $this->preservation      = $preservation ?? new PreservationKnowledgeService();
        $this->maxHistory        = (int) config('ahg-ai-chatbot.max_history', 20);
        $this->maxContext        = (int) config('ahg-ai-chatbot.max_context_records', 5);
        $this->groundingThreshold = (float) config('ahg-ai-chatbot.grounding_threshold', 0.5);
        $this->systemPrompt      = config('ahg-ai-chatbot.system_prompt');
        $this->temperature      = (string) config('ahg-ai-chatbot.temperature', '0.3');
        $this->preservationPassages = (int) config('ahg-ai-chatbot.preservation_passages', 3);
    }

    // ─── Conversation management ──────────────────────────────────

    /**
     * Store one message in the session history.
     */
    public function saveMessage(
        string $sessionId,
        string $role,
        string $content,
        ?array $sources = null,
        ?float $groundingScore = null,
        ?string $model = null,
        ?int $tokensIn = null,
        ?int $tokensOut = null,
    ): int {
        return (int) DB::table('ahg_ai_chatbot_message')->insertGetId([
            'session_id'      => $sessionId,
            'role'            => $role,
            'content'         => $content,
            'sources'         => $sources !== null ? json_encode($sources, JSON_UNESCAPED_UNICODE) : null,
            'grounding_score' => $groundingScore,
            'model'           => $model,
            'tokens_in'       => $tokensIn,
            'tokens_out'      => $tokensOut,
            'created_at'      => now(),
        ]);
    }

    /**
     * Load conversation history for a session (most-recent first N turns).
     */
    public function getHistory(string $sessionId, int $limit = 0): array
    {
        $limit = $limit > 0 ? $limit : $this->maxHistory;

        $rows = DB::table('ahg_ai_chatbot_message')
            ->where('session_id', $sessionId)
            ->orderByDesc('created_at')
            ->limit($limit * 2)  // user + assistant pairs
            ->orderBy('created_at')
            ->get();

        return $rows->map(function ($row) {
            return [
                'id'              => (int) $row->id,
                'role'            => $row->role,
                'content'         => $row->content,
                'sources'         => $row->sources ? json_decode($row->sources, true) : null,
                'grounding_score' => $row->grounding_score !== null ? (float) $row->grounding_score : null,
                'created_at'     => $row->created_at,
            ];
        })->values()->toArray();
    }

    /**
     * Clear all messages for a session.
     */
    public function clearHistory(string $sessionId): int
    {
        return DB::table('ahg_ai_chatbot_message')
            ->where('session_id', $sessionId)
            ->delete();
    }

    // ─── RAG core ──────────────────────────────────────────────────

    /**
     * Retrieve catalogue records relevant to the query.
     *
     * #1208 (culture = language): when $cultures is a non-empty list, retrieval is
     * constrained to the records described IN any of those languages (the UNION of
     * their LanguageCorpusService corpora). A null/empty/all-unknown selection
     * leaves retrieval fully unscoped (unchanged).
     *
     * @param  string|array<int,string>|null  $cultures
     * @return array{records: array, query: string}
     */
    public function retrieveCatalogue(string $query, $cultures = null): array
    {
        return $this->retriever->search($query, $this->maxContext, $cultures);
    }

    /**
     * Build the RAG prompt pair for a turn.
     *
     * @param string $userMessage Raw user message
     * @param array $records Retrieved catalogue records
     * @param array $history Conversation history (oldest first)
     * @return array{system: string, user: string}
     */
    public function buildRagPrompt(string $userMessage, array $records, array $history = [], ?array $currentRecord = null, string $preservationBlock = '', $cultures = null): array
    {
        $systemBase = $this->baseSystemPrompt();

        // #1208 (culture = language): when the conversation is scoped to one or more
        // languages, tell the model it is a guide to THOSE languages' heritage
        // corpora and must answer from the SOURCES below (which are already
        // constrained to records in any of those languages), citing them, rather
        // than from general knowledge. Additive: unscoped conversations get exactly
        // the base prompt as before. Multi-culture: every selected language is named.
        $cultures = $this->normaliseCultureScope($cultures);
        if (!empty($cultures)) {
            $labels = array_map(fn ($c) => $this->cultureLabel($c), $cultures);
            $langs = $this->joinList($labels);
            $corpora = count($labels) > 1 ? 'heritage-language corpora' : 'heritage-language corpus';
            $systemBase .= "\n\nLANGUAGE-CORPUS SCOPE: This conversation is a guide to the {$langs} "
                . "{$corpora}. The SOURCES below are drawn primarily from records held in or about "
                . "{$langs}; where little is held in-language, closely related records described in another "
                . "language are blended in and marked '(related - described in another language)'. Answer "
                . "from these SOURCES and cite them with [N]: lead with the in-language material and treat any "
                . "marked cross-language sources as supporting context. Do not draw on general knowledge of "
                . "{$langs} or of topics outside these SOURCES. If the SOURCES do not cover the question, say "
                . "so plainly and invite the user to broaden their search, rather than answering from outside "
                . "these SOURCES.";

            // #1208 glossary injection: surface the catalogue's OWN controlled
            // vocabulary (place + subject access points) for the scoped language(s).
            $systemBase .= $this->glossaryBlock($cultures);
        }

        // Inject catalogue context if records were retrieved
        $contextBlock = '';
        if (!empty($records)) {
            $lines = ["SOURCES — extracted from the Heratio catalogue:\n"];
            foreach (array_slice($records, 0, $this->maxContext) as $i => $rec) {
                $num = $i + 1;
                // #1208 soft blend: flag a cross-language source so the model (and its
                // citations) stay honest about which records are in the scoped language.
                $mark = (array_key_exists('in_corpus', $rec) && $rec['in_corpus'] === false)
                    ? ' (related - described in another language)'
                    : '';
                $lines[] = "[{$num}] {$rec['title']}{$mark}\n   ID: {$rec['identifier']}\n   URL: {$rec['url']}\n   Excerpt: {$rec['excerpt']}";
            }
            $contextBlock = "\n\n" . implode("\n", $lines);
        }

        // When the user is viewing a specific record, tell the model so deictic queries
        // ("tell me about this", "what is it") resolve to that record (source [1]) and the
        // answer does not drift to an unrelated catalogue hit.
        if ($currentRecord !== null) {
            $contextBlock .= "\n\nCURRENT VIEW: The user is looking at source [1], \""
                . $currentRecord['title'] . "\""
                . ($currentRecord['identifier'] !== '' ? " (ID: {$currentRecord['identifier']})" : '')
                . ". Treat \"this\", \"this item\", \"this record\", \"it\" and similar as referring to source [1]."
                . " When the question is about the item being viewed, answer from source [1] and cite it.";
        }

        // Supplementary preservation-knowledge grounding (issue #1243). Additive:
        // it never replaces the catalogue SOURCES; it gives the model curated,
        // cite-able digital-preservation guidance for preservation-domain
        // questions. Empty string when nothing relevant was retrieved.
        $systemPrompt = $systemBase . $contextBlock . $preservationBlock;

        // Build conversation-turn user block
        $turn = "USER QUERY: {$userMessage}";

        if (!empty($history)) {
            $lines = ["CONVERSATION HISTORY:"];
            foreach ($history as $msg) {
                $role = ucfirst($msg['role']);
                $content = mb_strlen($msg['content']) > 400
                    ? mb_substr($msg['content'], 0, 400) . '...'
                    : $msg['content'];
                $lines[] = "{$role}: {$content}";
            }
            $turn = implode("\n", $lines) . "\n\n" . $turn;
        }

        return [
            'system' => $systemPrompt,
            'user'   => $turn,
        ];
    }

    /**
     * #1208 glossary injection: when the conversation is scoped to one or more
     * languages, surface the catalogue's OWN controlled vocabulary for those
     * languages - the place-name and subject access points drawn from the
     * authority records (via LanguageCorpusService::terms) - so the model uses
     * the catalogue's spellings and can match a user's wording to a real access
     * point rather than inventing one. Additive + fail-soft: returns '' when the
     * corpus service is absent, the scope is empty, injection is disabled, or no
     * terms are found. Names are de-duplicated case-insensitively across the
     * selected cultures and capped per kind to keep the prompt budget sane.
     *
     * @param  array<int,string>  $cultures  normalised base subtags
     */
    private function glossaryBlock(array $cultures): string
    {
        if (empty($cultures) || ! config('ahg-ai-chatbot.glossary_injection', true)) {
            return '';
        }

        $svcClass = '\\AhgSemanticSearch\\Services\\LanguageCorpusService';
        if (! class_exists($svcClass)) {
            return '';
        }

        try {
            $svc = new $svcClass();
            $places = [];
            $subjects = [];
            foreach ($cultures as $culture) {
                foreach ($svc->terms($culture) as $t) {
                    $name = trim((string) ($t['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $key = mb_strtolower($name);
                    if (($t['kind'] ?? '') === 'place') {
                        $places[$key] = $name;
                    } else {
                        $subjects[$key] = $name;
                    }
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        $places   = array_slice(array_values($places), 0, self::GLOSSARY_MAX_PER_KIND);
        $subjects = array_slice(array_values($subjects), 0, self::GLOSSARY_MAX_PER_KIND);
        if (empty($places) && empty($subjects)) {
            return '';
        }

        $parts = [];
        if (!empty($places)) {
            $parts[] = 'Places: ' . implode('; ', $places) . '.';
        }
        if (!empty($subjects)) {
            $parts[] = 'Subjects: ' . implode('; ', $subjects) . '.';
        }

        return "\n\nGLOSSARY - controlled vocabulary from this catalogue's own authority records for the "
            . "scoped language(s). Use these spellings, and when the user's wording matches one of these "
            . "places or subjects, treat it as that access point:\n" . implode("\n", $parts);
    }

    /**
     * Base system prompt — archival-specialist persona with source-citation
     * and grounding rules.
     */
    private function baseSystemPrompt(): string
    {
        $custom = $this->systemPrompt;
        if ($custom !== null && trim($custom) !== '') {
            return trim($custom);
        }

        return <<<'PROMPT'
You are a knowledgeable archival research assistant working within the Heratio
catalogue. Full name: Heratio Archival Research Assistant.
Your role is to help users understand and navigate archival descriptions using
the Records in Contexts (RiC-O) framework and ISAD(G) descriptive standards.

RULES:
1. Answer from the provided SOURCES (catalogue records) and, for
   digital-preservation questions, from the PRESERVATION KNOWLEDGE passages
   when present. If neither contains anything relevant, say "I could not find
   anything in the catalogue matching your query."
2. ALWAYS cite your sources. Cite catalogue records using the format [N] where N
   is the source number (e.g. "The fonds was created by the National Archives
   [1]."). Cite preservation-knowledge passages by their (source: ...) tag
   verbatim. Do not invent digital-preservation guidance beyond what the
   PRESERVATION KNOWLEDGE passages state.
3. Keep answers concise, factual, and professionally worded in accordance
   with ISAD(G) principles.
4. Distinguish clearly between facts stated in the description and your own
   interpretive commentary.
5. If a query falls outside the catalogue scope (e.g. scheduling, legal
   advice), gently redirect the user.
6. Never fabricate provenance, reference non-existent records, or
   present speculation as fact.
7. Respect POPIA / GDPR / applicable privacy law: do not reveal
   personally-identifiable information beyond what is already in the
   public catalogue description.
8. Do NOT infer or state specific titles, authors, personal names, dates, or
   holdings that are not written verbatim in a SOURCE. A general or vague
   description (e.g. "science fiction and fantasy titles") does NOT mean any
   particular work, author, or item is held - never name a specific work or
   person unless a source explicitly lists it. If the user asks about something
   specific and no source names it, reply exactly: "I could not find anything in
   the catalogue matching your query."
PROMPT;
    }

    /**
     * Dispatch a message through the full pipeline:
     * guardrail → RAG retrieval → build prompt → LLM → log → save.
     *
     * @return array{
     *   success: bool,
     *   reply: string|null,
     *   sources: array,
     *   grounding_score: float|null,
     *   model: string|null,
     *   tokens_in: int,
     *   tokens_out: int,
     *   flags: array,
     *   error: string|null
     * }
     */
    public function dispatch(string $sessionId, string $userMessage, ?int $userId = null, ?string $pageUrl = null, ?string $locale = null, $cultures = null): array
    {
        // #1208 (culture = language): normalise the requested culture scope once to
        // a list of base subtags. An unusable / unknown / empty selection collapses
        // to an empty list, i.e. fully unscoped - the conversation then behaves
        // exactly as before (fail soft, never 500).
        $cultures = $this->normaliseCultureScope($cultures);

        // #1273: answer in the visitor's language via the sanctioned MT route
        // (AnswerLocalizer -> gateway /translate), NEVER a qwen "reply in X" prompt.
        // Default to the request locale; prompts stay English, only the reply is translated.
        $locale = $locale ?? app()->getLocale();

        // #1275 (opt-in): reply in the language the message was TYPED in rather than the UI
        // locale. Detection is local (no network, no qwen - InputLanguageDetector); it only
        // overrides the locale on a confident, MT-supported result, otherwise the #1273
        // UI-locale default stands. The detected code is only ever an MT target; AnswerLocalizer
        // still fails soft to English, so a bad detection never yields qwen garbage.
        if (config('ahg-ai-chatbot.reply_in_input_language', false)) {
            $detected = app(\AhgCore\Services\InputLanguageDetector::class)->detect($userMessage);
            if ($detected !== null) {
                $locale = $detected;
            }
        }

        // Guardrail pre-check
        $guardInspection = $this->guardrail->inspect([
            'provider'   => 'chatbot',
            'user_prompt'   => $userMessage,
            'data_scope' => 'internal',
            'purpose'    => 'research_assistance',
        ]);

        if ($guardInspection['action'] === 'block') {
            return [
                'success'         => false,
                'reply'           => null,
                'sources'         => [],
                'grounding_score' => null,
                'model'           => null,
                'tokens_in'       => 0,
                'tokens_out'      => 0,
                'flags'           => ['blocked'],
                'error'           => 'Blocked: ' . ($guardInspection['reason'] ?? 'guardrail policy'),
            ];
        }

        // ── Skill dispatch (issue #1095) ──────────────────────────────
        // Intent detection runs before RAG. When a library task-skill matches
        // (renew_loan / submit_ill_request / check_item_status) we answer
        // deterministically from the library services and skip the LLM. A null
        // return means "no skill" and we fall through to the RAG path below.
        try {
            $skillResult = $this->skills->handle($userMessage, $userId);
        } catch (\Throwable $e) {
            Log::warning('Chatbot skill dispatch failed, falling back to RAG: ' . $e->getMessage());
            $skillResult = null;
        }

        if (is_array($skillResult) && ($skillResult['handled'] ?? false)) {
            $reply = (string) ($skillResult['reply'] ?? '');
            // #1273: localize the deterministic skill reply via the MT route (never an LLM).
            $reply = app(\AhgCore\Services\AnswerLocalizer::class)->localize($reply, $locale);

            $flags = array_values(array_unique(array_merge(
                (array) ($guardInspection['flags'] ?? []),
                ['skill:' . ($skillResult['intent'] ?? 'unknown')]
            )));

            // Persist the turn (user + assistant) so history stays coherent.
            $this->saveMessage($sessionId, 'user', $userMessage, null, null, null, null, null);
            $this->saveMessage($sessionId, 'assistant', $reply, null, null, 'skill:' . ($skillResult['intent'] ?? 'unknown'), null, null);

            return [
                'success'         => $reply !== '',
                'reply'           => $reply,
                'sources'         => [],
                'grounding_score' => null,
                'model'           => 'skill:' . ($skillResult['intent'] ?? 'unknown'),
                'tokens_in'       => 0,
                'tokens_out'      => 0,
                'flags'           => $flags,
                'skill'           => $skillResult['intent'] ?? null,
                'data'            => $skillResult['data'] ?? [],
                'error'           => $reply === '' ? 'Skill produced an empty reply' : null,
            ];
        }

        // RAG retrieval - constrained to the selected language corpora when scoped (#1208).
        $catalogue = $this->retrieveCatalogue($userMessage, $cultures);
        $records   = $catalogue['records'] ?? [];

        // Current-page record: if the user is on a catalogue record page, make it the primary
        // source so the assistant grounds answers in the item being viewed instead of drifting
        // to an unrelated hit. Deduped and placed first as source [1].
        $currentRecord = $this->resolveCurrentRecord($pageUrl);
        if ($currentRecord !== null) {
            $records = array_values(array_filter($records, function ($r) use ($currentRecord) {
                $sameId = ($currentRecord['identifier'] ?? '') !== '' && ($r['identifier'] ?? '') === $currentRecord['identifier'];
                $sameUrl = ($currentRecord['url'] ?? '') !== '' && ($r['url'] ?? '') === $currentRecord['url'];

                return ! $sameId && ! $sameUrl;
            }));
            array_unshift($records, $currentRecord);
            $records = array_slice($records, 0, max($this->maxContext, 1));
        }

        // Conversation history
        $history = $this->getHistory($sessionId);

        // Supplementary preservation-knowledge grounding (issue #1243). Pure,
        // deterministic retrieval over the curated in-repo digital-preservation
        // corpus - NO AI / embedding / gateway call. We always probe, but only
        // attach the block when the query looks like a preservation question and
        // relevant curated passages are found; otherwise it stays empty and the
        // assistant behaves exactly as before.
        $preservationBlock = '';
        $preservationSources = [];
        try {
            if ($this->preservation->looksLikePreservationQuestion($userMessage)) {
                $passages = $this->preservation->retrieve($userMessage, $this->preservationPassages);
                if (!empty($passages)) {
                    $preservationBlock = $this->preservation->buildContextBlock($userMessage, $this->preservationPassages);
                    $preservationSources = array_map(fn ($p) => [
                        'title'  => $p['title'],
                        'source' => $p['source'],
                        'kind'   => 'preservation-knowledge',
                    ], $passages);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[chatbot] preservation knowledge retrieval failed: ' . $e->getMessage());
            $preservationBlock = '';
            $preservationSources = [];
        }

        // Build prompt (scoped to the selected language corpora when set).
        $prompts = $this->buildRagPrompt($userMessage, $records, $history, $currentRecord, $preservationBlock, $cultures);

        // LLM dispatch
        $llm = app(\AhgAiServices\Services\LlmService::class);
        $result = $llm->completeFull(
            $prompts['system'],
            $prompts['user'],
            null,
            [
                'purpose'         => 'research_assistance',
                'data_scope'      => 'internal',
                'skip_quota_gate' => false,
            ]
        );

        $reply   = $result['text'] ?? null;
        $success = !empty($result['success']) && is_string($reply) && $reply !== '';

        // Grounding check
        $sources         = [];
        $groundingScore  = null;
        $groundingResult = null;
        if ($success && !empty($records)) {
            $sourceTexts = array_column($records, 'excerpt');
            $groundingResult = $this->guardrail->checkGrounding($reply, $sourceTexts);
            if ($groundingResult !== null) {
                $groundingScore = (float) $groundingResult['grounding_score'];
            }
            // Build the source chips from the retrieved records regardless of
            // whether the grounding check ran, so an inline [N] citation in the
            // reply always has a matching source chip (no dangling [N]). When the
            // answer is suppressed as ungrounded below, $sources is cleared again.
            $sources = array_map(function ($rec, int $i) {
                return [
                    'title'      => $rec['title'],
                    'identifier' => $rec['identifier'],
                    'url'        => $rec['url'],
                    'ref'        => '[' . ($i + 1) . ']',
                ];
            }, $records, array_keys($records));
        }

        // Enforce grounding: if the answer is not supported by the retrieved
        // sources (hallucination / over-extrapolation of a vague description -
        // e.g. inventing specific titles or authors a source never named), do
        // NOT present it. Replace with an honest "not found" and drop the
        // fabricated citations, so the assistant never surfaces invented
        // records or attributes claims to sources that do not contain them.
        $ungroundedSuppressed = false;
        if ($success && $groundingResult !== null && !($groundingResult['grounded'] ?? true)) {
            $reply   = 'I could not find anything in the catalogue matching your query.';
            $sources = [];
            $ungroundedSuppressed = true;
        }

        // A reply that cites sources [N] while there are NO source chips to back
        // them (empty retrieval - e.g. an unindexed catalogue) is fabricating
        // records and citations. Suppress it rather than present invented
        // holdings with dangling citation markers.
        if ($success && !$ungroundedSuppressed && empty($sources)
            && preg_match('/\[\d+\]/', (string) $reply)) {
            $reply   = 'I could not find anything in the catalogue matching your query.';
            $ungroundedSuppressed = true;
        }

        // #1273: localize the English reply into the visitor's language via the MT route
        // (AnswerLocalizer -> gateway /translate), never a qwen "reply in X" prompt. The
        // grounding check above ran on the English reply; we translate only the presented
        // text and fail-soft to English on any miss (unsupported language / MT down).
        if ($success) {
            $reply = app(\AhgCore\Services\AnswerLocalizer::class)->localize((string) $reply, $locale);
        }

        // Surface curated preservation-knowledge passages as additional sources
        // (issue #1243) so the user can see / follow the cited guidance. These
        // are appended after the catalogue sources and never replace them.
        if ($success && !empty($preservationSources)) {
            foreach ($preservationSources as $ps) {
                $sources[] = [
                    'title'      => $ps['title'],
                    'identifier' => '',
                    'url'        => null,
                    'source'     => $ps['source'],
                    'kind'       => 'preservation-knowledge',
                    'ref'        => '(source: ' . $ps['source'] . ')',
                ];
            }
        }

        // Tokens
        $tokensIn  = (int) ($result['tokens_in']  ?? $result['tokens_used'] ?? 0);
        $tokensOut = (int) ($result['tokens_out'] ?? 0);

        // Flags from guardrail
        $flags = array_values(array_unique(
            array_merge((array) ($guardInspection['flags'] ?? []), (array) ($result['flags'] ?? []))
        ));
        if ($groundingResult !== null && !($groundingResult['grounded'] ?? true)) {
            $flags[] = 'low_grounding';
        }
        if ($ungroundedSuppressed) {
            $flags[] = 'ungrounded_suppressed';
        }

        // Persist user message
        $this->saveMessage($sessionId, 'user', $userMessage, null, null, null, null, null);

        // Persist assistant reply
        if ($success) {
            $this->saveMessage(
                $sessionId,
                'assistant',
                $reply,
                $sources ?: null,
                $groundingScore,
                $result['model'] ?? null,
                $tokensIn,
                $tokensOut
            );
        }

        return [
            'success'         => $success,
            'reply'           => $reply,
            'sources'         => $sources,
            'grounding_score' => $groundingScore,
            'model'           => $result['model'] ?? null,
            'tokens_in'       => $tokensIn,
            'tokens_out'      => $tokensOut,
            'flags'           => $flags,
            'error'           => $success ? null : ($result['error'] ?? 'LLM call failed'),
        ];
    }

    /**
     * Resolve the catalogue record the user is currently viewing, from the page URL (the request
     * Referer). Takes the last path segment as a slug, looks it up, and returns it in the canonical
     * record shape - or null when the page is not a catalogue record. Used to ground the assistant
     * in the item on screen so "tell me about this" resolves correctly.
     *
     * @return array{id:int,title:string,identifier:string,url:string,excerpt:string}|null
     */
    public function resolveCurrentRecord(?string $pageUrl): ?array
    {
        if (! $pageUrl || trim($pageUrl) === '') {
            return null;
        }
        $path = parse_url($pageUrl, PHP_URL_PATH) ?: $pageUrl;
        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }
        $segs = explode('/', $path);
        // Admin / functional areas are never a catalogue record page.
        if (in_array($segs[0] ?? '', ['admin', 'api', 'chatbot', 'ask', 'login', 'logout', 'search', 'clipboard', 'index.php'], true)) {
            return null;
        }
        $slug = end($segs);
        if ($slug === '' || strlen($slug) > 255) {
            return null;
        }
        try {
            $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
            if (! $objectId) {
                return null;
            }
            if (! DB::table('information_object')->where('id', $objectId)->exists()) {
                return null; // a slug that is not an information object (actor, repository, ...)
            }
            $identifier = (string) DB::table('information_object')->where('id', $objectId)->value('identifier');
            $i18n = DB::table('information_object_i18n')->where('id', $objectId)->where('culture', 'en')->first()
                ?? DB::table('information_object_i18n')->where('id', $objectId)->first();

            $title = trim((string) ($i18n->title ?? '')) ?: ('Record ' . $objectId);
            $excerpt = trim((string) ($i18n->scope_and_content ?? ''));
            if ($excerpt === '') {
                $excerpt = $title;
            } elseif (mb_strlen($excerpt) > 350) {
                $excerpt = mb_substr($excerpt, 0, 347) . '...';
            }

            return [
                'id'         => (int) $objectId,
                'title'      => $title,
                'identifier' => $identifier,
                'url'        => url('/' . $slug),
                'excerpt'    => $excerpt,
            ];
        } catch (\Throwable $e) {
            Log::warning('[chatbot] resolveCurrentRecord failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Normalise a requested culture/language scope to a de-duplicated list of base
     * subtags, using the SAME rules as LanguageCorpusService so the chatbot scope
     * and the corpus surface agree on what "a language" is.
     *
     * #1208 multi-culture: accepts a single culture string, a comma-separated string
     * ("xh,zu,nso"), or an array of culture codes. Blank / unusable / unknown codes
     * are dropped. Returns [] for an empty / all-unusable selection or when the
     * corpus service is unavailable - i.e. fail soft to an unscoped conversation.
     * Order is preserved (first occurrence wins). Never throws.
     *
     * @param  string|array<int,string>|null  $cultures
     * @return array<int,string>
     */
    public function normaliseCultureScope($cultures): array
    {
        // Flatten the input to a flat list of candidate codes. A single string may
        // itself be a comma / pipe / whitespace separated list.
        $candidates = [];
        if (is_array($cultures)) {
            foreach ($cultures as $c) {
                if (is_string($c)) {
                    $candidates = array_merge($candidates, preg_split('/[,\|\s]+/', $c) ?: []);
                }
            }
        } elseif (is_string($cultures)) {
            $candidates = preg_split('/[,\|\s]+/', $cultures) ?: [];
        }

        $candidates = array_values(array_filter($candidates, fn ($c) => trim((string) $c) !== ''));
        if (empty($candidates)) {
            return [];
        }

        $svcClass = '\\AhgSemanticSearch\\Services\\LanguageCorpusService';
        if (! class_exists($svcClass)) {
            return [];
        }

        try {
            $svc = new $svcClass();
            $out = [];
            foreach ($candidates as $c) {
                $base = $svc->sanitiseCulture((string) $c);
                if ($base !== null && ! in_array($base, $out, true)) {
                    $out[] = $base;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Join a list of labels into a natural-language enumeration:
     * ["A"] -> "A"; ["A","B"] -> "A and B"; ["A","B","C"] -> "A, B and C".
     *
     * @param  array<int,string>  $items
     */
    private function joinList(array $items): string
    {
        $items = array_values(array_filter($items, fn ($i) => trim((string) $i) !== ''));
        $n = count($items);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    /**
     * Public wrapper over cultureLabel() for callers (e.g. the controller / view)
     * that need a display label for a scoped culture.
     */
    public function cultureLabelPublic(string $culture): string
    {
        return $this->cultureLabel($culture);
    }

    /**
     * Human label for a culture code (delegates to LanguageCorpusService so labels
     * stay in step). Falls back to the upper-cased code if the service is absent.
     */
    private function cultureLabel(string $culture): string
    {
        $svcClass = '\\AhgSemanticSearch\\Services\\LanguageCorpusService';
        if (class_exists($svcClass)) {
            try {
                return (new $svcClass())->label($culture);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return strtoupper($culture);
    }

    // ─── Admin helpers ────────────────────────────────────────────

    /**
     * Get aggregate stats for the chatbot.
     */
    public function getStats(): array
    {
        try {
            $total = DB::table('ahg_ai_chatbot_message')->count();

            $recent = DB::table('ahg_ai_chatbot_message')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $lowGrounding = DB::table('ahg_ai_chatbot_message')
                ->whereNotNull('grounding_score')
                ->where('grounding_score', '<', $this->groundingThreshold)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $sessions = DB::table('ahg_ai_chatbot_message')
                ->select('session_id')
                ->distinct()
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            return [
                'total_messages'   => (int) $total,
                'messages_30d'     => (int) $recent,
                'low_grounding_30d' => (int) $lowGrounding,
                'sessions_30d'     => (int) $sessions,
            ];
        } catch (Throwable $e) {
            return [
                'total_messages'   => 0,
                'messages_30d'     => 0,
                'low_grounding_30d' => 0,
                'sessions_30d'     => 0,
            ];
        }
    }

    /**
     * Fetch recent assistant messages needing review (low grounding).
     */
    public function getReviewQueue(int $limit = 50): array
    {
        return DB::table('ahg_ai_chatbot_message')
            ->where('role', 'assistant')
            ->whereNotNull('grounding_score')
            ->where('grounding_score', '<', $this->groundingThreshold)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id'              => (int) $row->id,
                'content'         => $row->content,
                'grounding_score' => (float) $row->grounding_score,
                'session_id'      => $row->session_id,
                'model'           => $row->model,
                'created_at'      => $row->created_at,
                'sources'         => $row->sources ? json_decode($row->sources, true) : null,
            ])
            ->values()
            ->toArray();
    }
}
