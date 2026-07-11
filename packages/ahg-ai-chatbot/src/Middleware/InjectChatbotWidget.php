<?php

/**
 * InjectChatbotWidget - global response middleware that appends the chatbot
 * floating widget to every authenticated HTML page. Avoids modifying the
 * locked layout templates in ahg-theme-b5 / ahg-information-object-manage.
 *
 * The injection is opt-out per role via config('ahg-ai-chatbot.widget.roles').
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgAiChatbot\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InjectChatbotWidget
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$this->shouldInject($request, $response)) {
            return $response;
        }

        try {
            $widget = View::make('ahg-ai-chatbot::widget')->render();
            $content = (string) $response->getContent();
            if (str_contains($content, '</body>') && !str_contains($content, 'id="ahg-chatbot-widget"')) {
                $content = str_replace('</body>', $widget . '</body>', $content);
                $response->setContent($content);
            }
        } catch (Throwable) {
            // Never let widget rendering kill a page response.
        }

        return $response;
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (!config('ahg-ai-chatbot.widget.enabled', true)) return false;
        if (!config('ahg-ai-chatbot.enabled', true)) return false;
        if (!Auth::check()) return false;
        if (!$response->headers->has('Content-Type')) return false;
        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) return false;
        if ($response->getStatusCode() >= 300) return false;

        $path = $request->path();
        // Don't inject into chatbot's own routes, asset paths, or the MVA Claims sub-app
        // (it has its own feedback/assistant UI and a standalone layout).
        foreach (['chatbot', 'admin/chatbot', 'api', 'oai', 'sru', '_debugbar', 'mva'] as $skip) {
            if (str_starts_with($path, $skip)) return false;
        }

        return true;
    }
}
