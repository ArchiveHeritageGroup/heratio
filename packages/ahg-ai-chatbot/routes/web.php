<?php

use AhgAiChatbot\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Chatbot Routes
|--------------------------------------------------------------------------
| Conversational RAG chatbot over the Heratio catalogue.
| Routes under /chatbot for end-users; admin prefix for management.
|--------------------------------------------------------------------------
*/

// Public chatbot policy page (anonymous so visitors can read terms first).
Route::get('chatbot/policy', [ChatbotController::class, 'policy'])->name('chatbot.policy');

// Authenticated end-user UI
Route::middleware(['auth'])->prefix('chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'index'])->name('chatbot.index');
    Route::post('/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    Route::get('/history', [ChatbotController::class, 'history'])->name('chatbot.history');
    Route::post('/reset', [ChatbotController::class, 'reset'])->name('chatbot.reset');
    Route::post('/escalate', [ChatbotController::class, 'escalate'])->name('chatbot.escalate');
});

// Admin management routes
Route::middleware(['auth'])->prefix('admin/chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'admin'])->name('admin.chatbot.index');
    Route::get('/review', [ChatbotController::class, 'review'])->name('admin.chatbot.review');
});
