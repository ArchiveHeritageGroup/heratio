<?php

/**
 * ChatbotController
 *
 * Conversational chatbot UI and API endpoints.
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgAiChatbot\Controllers;

use AhgAiChatbot\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    private ChatbotService $chatbot;

    public function __construct(ChatbotService $chatbot)
    {
        $this->chatbot = $chatbot;
    }

    /**
     * GET /chatbot — chat UI.
     */
    public function index(Request $request)
    {
        if (!config('ahg-ai-chatbot.enabled', true)) {
            abort(404);
        }

        $sessionId = $this->resolveSessionId($request);

        // #1208 (culture = language): an optional ?language[]=/?language=a,b,c (or
        // ?culture=) on the chat entry deep-links a scope (e.g. from a record page).
        // Otherwise a fresh visit to the chat page starts UNSCOPED: the language
        // scope is a per-conversation choice the user makes here and must NOT
        // silently carry over from an earlier conversation. For an authenticated
        // user resolveSessionId() returns a stable per-user hash, so a persisted
        // scope would otherwise stick to every future chat (the "3 stale items in
        // the dropdown = wrong session" bug). Clear any prior scope on entry so it
        // cannot resurface via the message() fallback either. The live selection is
        // submitted with each turn (see the scripts block), so per-conversation
        // persistence does not depend on restoring it here.
        $cultures = $this->resolveRequestedCultures($request);
        if ($request->has('language') || $request->has('culture')) {
            $this->setSessionCultures($sessionId, $cultures);
        } else {
            $cultures = [];
            $this->setSessionCultures($sessionId, []);
        }

        $history       = $this->chatbot->getHistory($sessionId);
        $stats         = $this->chatbot->getStats();
        $cultureLabels = array_map(fn ($c) => $this->chatbot->cultureLabelPublic($c), $cultures);

        // The directory of selectable languages (those with described records),
        // built the same way /language-corpus does - via LanguageCorpusService.
        $availableCultures = $this->availableCultures();

        return view('ahg-ai-chatbot::index', compact(
            'sessionId',
            'history',
            'stats',
            'cultures',
            'cultureLabels',
            'availableCultures'
        ));
    }

    /**
     * POST /chatbot/message — send a message (JSON API).
     */
    public function message(Request $request): JsonResponse
    {
        if (!config('ahg-ai-chatbot.enabled', true)) {
            return response()->json(['success' => false, 'error' => 'Chatbot is disabled'], 404);
        }

        $request->validate([
            'message'    => 'required|string|max:4000',
            // #1208 multi-culture: language/culture may be a single code, a
            // comma-separated list, OR an array of codes (?language[]=).
            'language'   => 'nullable',
            'language.*' => 'nullable|string|max:32',
            'culture'    => 'nullable',
            'culture.*'  => 'nullable|string|max:32',
        ]);

        $sessionId = $this->resolveSessionId($request);
        $userId    = Auth::id();

        // The page the user is on (Referer) lets the assistant ground answers in the record being
        // viewed. An explicit page_url in the body wins when supplied (e.g. SPA / API callers).
        $pageUrl = $request->input('page_url') ?: $request->headers->get('referer');

        // #1208 (culture = language): scope this turn to one or more language
        // corpora. A scope supplied on the turn updates the persisted session scope;
        // otherwise the last persisted scope (set on entry or a prior turn) is reused
        // so follow-up turns stay scoped. Blank / unknown -> unscoped (downstream).
        if ($request->has('language') || $request->has('culture')) {
            $cultures = $this->resolveRequestedCultures($request);
            $this->setSessionCultures($sessionId, $cultures);
        } else {
            $cultures = $this->getSessionCultures($sessionId);
        }

        $result = $this->chatbot->dispatch(
            $sessionId,
            $request->input('message'),
            $userId,
            is_string($pageUrl) ? $pageUrl : null,
            null,
            $cultures
        );

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'] ?? 'Chatbot unavailable',
                'reply'   => null,
            ], 500);
        }

        return response()->json([
            'success'         => true,
            'reply'           => $result['reply'],
            'sources'         => $result['sources'] ?? [],
            'grounding_score' => $result['grounding_score'],
            'model'           => $result['model'],
            'tokens_in'       => $result['tokens_in'] ?? 0,
            'tokens_out'      => $result['tokens_out'] ?? 0,
            'flags'           => $result['flags'] ?? [],
        ]);
    }

    /**
     * GET /chatbot/history — load session history.
     */
    public function history(Request $request): JsonResponse
    {
        $sessionId = $this->resolveSessionId($request);
        $limit     = max(0, (int) $request->input('limit', 0));
        $history   = $this->chatbot->getHistory($sessionId, $limit);

        return response()->json(['success' => true, 'history' => $history]);
    }

    /**
     * POST /chatbot/reset — clear session.
     */
    public function reset(Request $request): JsonResponse
    {
        $sessionId = $this->resolveSessionId($request);
        $cleared   = $this->chatbot->clearHistory($sessionId);

        return response()->json(['success' => true, 'cleared' => $cleared]);
    }

    /**
     * GET /admin/chatbot — admin dashboard.
     */
    public function admin()
    {
        $stats      = $this->chatbot->getStats();
        $reviewQueue = $this->chatbot->getReviewQueue();

        return view('ahg-ai-chatbot::admin', compact('stats', 'reviewQueue'));
    }

    /**
     * GET /admin/chatbot/review — low-grounding review queue.
     */
    public function review()
    {
        $rows = $this->chatbot->getReviewQueue();

        return view('ahg-ai-chatbot::review', compact('rows'));
    }

    /**
     * GET /admin/chatbot/preservation-knowledge?q=... (issue #1243)
     *
     * Debug / verification surface for the deterministic preservation-knowledge
     * retrieval layer. Returns the curated passages (with source citations) that
     * would be injected as supplementary grounding for the given query. No LLM
     * call is made - this is the pure, testable retrieval result. Admin-guarded.
     */
    public function preservationKnowledge(
        Request $request,
        \AhgAiChatbot\Services\PreservationKnowledgeService $knowledge
    ): JsonResponse {
        $query = trim((string) $request->input('q', ''));
        $limit = max(1, min(10, (int) $request->input('limit', 3)));

        if ($query === '') {
            return response()->json([
                'success'      => false,
                'error'        => 'Provide a query via ?q=',
                'corpus_files' => count($knowledge->corpusFiles()),
            ], 422);
        }

        $passages = $knowledge->retrieve($query, $limit);

        return response()->json([
            'success'                => true,
            'query'                  => $query,
            'is_preservation_query'  => $knowledge->looksLikePreservationQuestion($query),
            'corpus_files'           => count($knowledge->corpusFiles()),
            'indexed_sections'       => count($knowledge->getIndex()),
            'passages'               => $passages,
            'retrieval'              => 'deterministic keyword/section index (no AI / embedding / gateway call)',
        ]);
    }

    /**
     * GET /chatbot/policy - plain-language POPIA / GDPR notice.
     * Public; no auth required so visitors can read before opting in.
     */
    public function policy()
    {
        return view('ahg-ai-chatbot::policy');
    }

    /**
     * POST /chatbot/escalate - 'talk to a librarian' handoff.
     * Writes an ahg_notification row addressed to the librarian role + emails
     * if SMTP is configured. Returns a tracking reference.
     */
    public function escalate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:4000',
            'name'    => 'nullable|string|max:200',
            'email'   => 'nullable|email|max:200',
            'context' => 'nullable|string|max:4000',
        ]);

        $sessionId = $this->resolveSessionId($request);
        $userId    = Auth::id();

        $payload = json_encode([
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'name'       => $data['name'] ?? (Auth::user()->name ?? null),
            'email'      => $data['email'] ?? (Auth::user()->email ?? null),
            'message'    => $data['message'],
            'context'    => $data['context'] ?? null,
            'created_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE);

        $reference = 'CB-' . strtoupper(Str::random(8));

        try {
            app(\AhgCore\Services\NotificationService::class)->notifyAdmins(
                type: 'chatbot-escalation',
                title: 'Chatbot escalation [' . $reference . ']',
                message: $data['message'],
                link: '/admin/chatbot/review',
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Chatbot escalation notification insert failed: ' . $e->getMessage());
        }

        return response()->json([
            'success'   => true,
            'reference' => $reference,
            'message'   => __('A librarian will follow up on your question shortly. Reference: :ref', ['ref' => $reference]),
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * #1208 multi-culture: the requested language/culture scope SET from the request
     * (?language[]= array, comma-separated ?language=a,b,c, or single ?language= /
     * ?culture=, body or query), normalised + de-duplicated to LanguageCorpusService
     * base subtags. Returns [] for blank / unknown / unusable input (-> unscoped).
     *
     * @return array<int,string>
     */
    private function resolveRequestedCultures(Request $request): array
    {
        // Prefer 'language'; fall back to 'culture'. Each may be a string (possibly
        // comma-separated) or an array (?language[]=). normaliseCultureScope handles
        // strings, comma-separated strings AND arrays uniformly.
        $raw = $request->input('language');
        if ($raw === null || $raw === '' || (is_array($raw) && count($raw) === 0)) {
            $raw = $request->input('culture');
        }

        return $this->chatbot->normaliseCultureScope($raw);
    }

    /**
     * Per-chat-session persisted language scope key in the Laravel session. Keyed on
     * the chatbot session id so different chats (or anonymous vs authed) do not
     * bleed scope into each other.
     */
    private function cultureSessionKey(string $sessionId): string
    {
        return 'chatbot_culture:' . $sessionId;
    }

    /**
     * Persist (or clear, when empty) the language scope SET for a chat session.
     *
     * @param  array<int,string>  $cultures
     */
    private function setSessionCultures(string $sessionId, array $cultures): void
    {
        $cultures = array_values(array_filter($cultures, fn ($c) => is_string($c) && $c !== ''));
        try {
            if (empty($cultures)) {
                session()->forget($this->cultureSessionKey($sessionId));
            } else {
                session()->put($this->cultureSessionKey($sessionId), $cultures);
            }
        } catch (\Throwable $e) {
            // Session store unavailable (e.g. stateless API call) - scope just stays
            // per-turn rather than persisted. Non-fatal.
        }
    }

    /**
     * The persisted language scope SET for a chat session, or [] when unscoped.
     * Tolerates a legacy single-string value (pre-multi-culture sessions).
     *
     * @return array<int,string>
     */
    private function getSessionCultures(string $sessionId): array
    {
        try {
            $v = session()->get($this->cultureSessionKey($sessionId));
        } catch (\Throwable $e) {
            return [];
        }

        if (is_array($v)) {
            return array_values(array_filter($v, fn ($c) => is_string($c) && $c !== ''));
        }
        if (is_string($v) && $v !== '') {
            return [$v]; // legacy single-culture session value
        }

        return [];
    }

    /**
     * #1208 multi-culture: the directory of selectable languages for the chat scope
     * selector, built the same way /language-corpus does - via LanguageCorpusService
     * (only languages with described records). Fail-soft: [] when the service is
     * absent or any lookup fails, so the selector simply does not render.
     *
     * @return array<int,array{code:string,label:string,records:int}>
     */
    private function availableCultures(): array
    {
        $svcClass = '\\AhgSemanticSearch\\Services\\LanguageCorpusService';
        if (! class_exists($svcClass)) {
            return [];
        }

        try {
            return (new $svcClass())->availableCultures();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve the session ID: caller-supplied token wins; otherwise a signed
     * cookie (authenticated users); otherwise a fresh UUID stored in session.
     */
    private function resolveSessionId(Request $request): string
    {
        // Explicitly supplied (e.g. API callers)
        $token = $request->input('_session');
        if (is_string($token) && trim($token) !== '') {
            return hash('sha256', trim($token));
        }

        // Authenticated user: stable per-user session
        if (Auth::check()) {
            return hash('sha256', 'user:' . Auth::id());
        }

        // Anonymous: session cookie or generate fresh
        $cookie = $request->cookie('chatbot_sid');
        if (is_string($cookie) && trim($cookie) !== '') {
            return trim($cookie);
        }

        $sid = Str::uuid()->toString();
        \Illuminate\Support\Facades\Cookie::queue(
            \Illuminate\Cookie\Cookie::make('chatbot_sid', $sid, 60 * 24 * 7)
        );

        return $sid;
    }
}
