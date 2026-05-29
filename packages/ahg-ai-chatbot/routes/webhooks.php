<?php

/**
 * WhatsApp inbound webhook routes (issue #1095).
 *
 * Public, no auth, no CSRF (external webhook). Mounted on the `api` middleware
 * group by the service provider. Both verbs 404 unless
 * config('ahg-ai-chatbot.whatsapp.enabled') is true (enforced in the
 * controller). Light throttle guards against a flood.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

use AhgAiChatbot\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
        ->name('chatbot.whatsapp.verify');
    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
        ->name('chatbot.whatsapp.receive');
});
