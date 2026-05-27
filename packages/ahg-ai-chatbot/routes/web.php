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

// Public / authenticated end-user UI
Route::middleware(['auth'])->prefix('chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'index'])->name('chatbot.index');
    Route::post('/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    Route::get('/history', [ChatbotController::class, 'history'])->name('chatbot.history');
    Route::post('/reset', [ChatbotController::class, 'reset'])->name('chatbot.reset');
});

// Admin management routes
Route::middleware(['auth'])->prefix('admin/chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'admin'])->name('admin.chatbot.index');
    Route::get('/review', [ChatbotController::class, 'review'])->name('admin.chatbot.review');
});
