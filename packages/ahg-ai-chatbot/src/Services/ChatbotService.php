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
    private QdrantRetriever $retriever;
    private \AhgAiServices\Services\GuardrailService $guardrail;
    private int $maxHistory;
    private int $maxContext;
    private float $groundingThreshold;
    private ?string $systemPrompt;
    private string $temperature;

    public function __construct(?QdrantRetriever $retriever = null)
    {
        $this->retriever         = $retriever ?? new QdrantRetriever();
        $this->guardrail         = new \AhgAiServices\Services\GuardrailService();
        $this->maxHistory        = (int) config('ahg-ai-chatbot.max_history', 20);
        $this->maxContext        = (int) config('ahg-ai-chatbot.max_context_records', 5);
        $this->groundingThreshold = (float) config('ahg-ai-chatbot.grounding_threshold', 0.5);
        $this->systemPrompt      = config('ahg-ai-chatbot.system_prompt');
        $this->temperature      = (string) config('ahg-ai-chatbot.temperature', '0.3');
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
     * @return array{records: array, query: sting}
     */
    public function retrieveCatalogue(string $query): array
    {
        return $this->retriever->search($query, $this->maxContext);
    }

    /**
     * Build the RAG prompt pair for a turn.
     *
     * @param string $userMessage Raw user message
     * @param array $records Retrieved catalogue records
     * @param array $history Conversation history (oldest first)
     * @return array{system: string, user: string}
     */
    public function buildRagPrompt(string $userMessage, array $records, array $history = []): array
    {
        $systemBase = $this->baseSystemPrompt();

        // Inject catalogue context if records were retrieved
        $contextBlock = '';
        if (!empty($records)) {
            $lines = ["SOURCES — extracted from the Heratio catalogue:\n"];
            foreach (array_slice($records, 0, $this->maxContext) as $i => $rec) {
                $num = $i + 1;
                $lines[] = "[{$num}] {$rec['title']}\n   ID: {$rec['identifier']}\n   URL: {$rec['url']}\n   Excerpt: {$rec['excerpt']}";
            }
            $contextBlock = "\n\n" . implode("\n", $lines);
        }

        $systemPrompt = $systemBase . $contextBlock;

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
1. Answer only from the provided SOURCES. If no relevant source is available,
   say "I could not find anything in the catalogue matching your query."
2. ALWAYS cite your sources using the format [N] where N is the source number.
   Example: "The fonds was created by the National Archives [1]."
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
    public function dispatch(string $sessionId, string $userMessage, ?int $userId = null): array
    {
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

        // RAG retrieval
        $catalogue = $this->retrieveCatalogue($userMessage);
        $records   = $catalogue['records'] ?? [];

        // Conversation history
        $history = $this->getHistory($sessionId);

        // Build prompt
        $prompts = $this->buildRagPrompt($userMessage, $records, $history);

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
                $sources = array_map(function ($rec, int $i) {
                    return [
                        'title'      => $rec['title'],
                        'identifier' => $rec['identifier'],
                        'url'        => $rec['url'],
                        'ref'        => '[' . ($i + 1) . ']',
                    ];
                }, $records, array_keys($records));
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
