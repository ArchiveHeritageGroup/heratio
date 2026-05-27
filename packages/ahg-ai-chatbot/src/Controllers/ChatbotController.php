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
        $history   = $this->chatbot->getHistory($sessionId);
        $stats     = $this->chatbot->getStats();

        return view('ahg-ai-chatbot::index', compact('sessionId', 'history', 'stats'));
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
            'message' => 'required|string|max:4000',
        ]);

        $sessionId = $this->resolveSessionId($request);
        $userId    = Auth::id();

        $result = $this->chatbot->dispatch($sessionId, $request->input('message'), $userId);

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
            \Illuminate\Support\Facades\DB::table('ahg_notification')->insert([
                'recipient_role' => 'librarian',
                'subject'        => 'Chatbot escalation [' . $reference . ']',
                'body'           => $data['message'],
                'metadata'       => $payload,
                'created_at'     => now(),
            ]);
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
