<?php

/**
 * VoiceSttController - voice search speech-to-text fallback (heratio#1767).
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

namespace AhgCore\Controllers;

use AhgCore\Services\VoiceSttService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * GET  /voice/stt/config  - what the browser fallback needs (on/off, limits, messages).
 * POST /voice/stt         - multipart `audio`; returns {ok, text} or {ok:false, message}.
 *
 * The recognised text goes back to the browser, which runs it through the
 * same command matcher as browser speech ("search for ..."). The upload is
 * deleted when the request ends and is never stored.
 *
 * Public by default (voice search works for guests), so the route carries no
 * 'auth' middleware; voice_stt_require_auth turns sign-in on here instead.
 */
class VoiceSttController extends Controller
{
    public function __construct(private VoiceSttService $stt) {}

    public function config(): JsonResponse
    {
        $enabled = $this->stt->isEnabled() && (! $this->stt->requiresAuth() || Auth::check());

        return response()->json([
            'enabled' => $enabled,
            'max_seconds' => $this->stt->maxSeconds(),
            'max_bytes' => $this->stt->maxKilobytes() * 1024,
            'upload_url' => route('voice.stt.transcribe', [], false),
            'messages' => [
                'unavailable' => $this->unavailableMessage(),
                'recording' => __('Recording - click the microphone again to stop'),
                'sending' => __('Recognising speech...'),
                'no_speech' => __('No speech detected'),
                'denied' => __('Microphone access denied. Please enable it in browser settings.'),
                'no_dictation' => __('Dictation into fields needs a browser with built-in speech recognition.'),
            ],
        ]);
    }

    public function transcribe(Request $request): JsonResponse
    {
        try {
            return $this->handle($request);
        } finally {
            // Audio is not stored: remove the upload now rather than at PHP shutdown.
            $file = $request->file('audio');
            if ($file && $file->isValid() && is_file($file->getRealPath())) {
                @unlink($file->getRealPath());
            }
        }
    }

    private function handle(Request $request): JsonResponse
    {
        if (! $this->stt->isEnabled()) {
            return response()->json(['ok' => false, 'error' => 'disabled', 'message' => $this->unavailableMessage()], 404);
        }
        if ($this->stt->requiresAuth() && ! Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'auth', 'message' => __('Please sign in to use voice search.')], 401);
        }

        $key = 'voice-stt:'.(Auth::id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, $this->stt->rateLimit())) {
            return response()->json([
                'ok' => false, 'error' => 'rate_limited',
                'message' => __('Too many voice searches. Please wait a minute and try again.'),
            ], 429)->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $validator = Validator::make($request->all(), [
            'audio' => ['required', 'file', 'max:'.$this->stt->maxKilobytes(), 'mimetypes:'.implode(',', VoiceSttService::MIME_TYPES)],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false, 'error' => 'invalid_audio',
                'message' => __('The recording could not be used. Please try again or type your search.'),
            ], 422);
        }

        $result = $this->stt->transcribe($request->file('audio'), app()->getLocale());
        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => 'unavailable', 'message' => $this->unavailableMessage()], 503);
        }

        return response()->json(['ok' => true, 'text' => $result['text']]);
    }

    private function unavailableMessage(): string
    {
        return __('Voice search is not available right now; please type your search');
    }
}
