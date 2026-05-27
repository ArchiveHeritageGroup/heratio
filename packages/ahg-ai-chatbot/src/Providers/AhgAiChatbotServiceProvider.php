<?php

/**
 * AHG AI Chatbot Service Provider
 *
 * Registers ChatbotService + ChatbotController and boots the feature.
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgAiChatbot\Providers;

use AhgAiChatbot\Controllers\ChatbotController;
use AhgAiChatbot\Services\ChatbotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgAiChatbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatbotService::class);

        // Merge package config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ahg-ai-chatbot.php',
            'ahg-ai-chatbot'
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-ai-chatbot');

        $this->ensureSchema();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgAiChatbot\Console\Commands\ChatbotTestMultilangCommand::class,
            ]);
        }

        // Register routes
        $router = $this->app['router'];
        $router->middleware(['web'])
            -> group(__DIR__ . '/../../routes/web.php');

        // Register global widget-injection middleware on the web stack so the
        // floating chatbot appears on every authenticated HTML page without
        // touching the locked layout templates.
        try {
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            if (method_exists($kernel, 'appendMiddlewareToGroup')) {
                $kernel->appendMiddlewareToGroup('web', \AhgAiChatbot\Middleware\InjectChatbotWidget::class);
            }
        } catch (Throwable) {
            // Best effort; layout-level @include still works as a fallback.
        }
    }

    /**
     * Auto-install the ahg_ai_chatbot_message table if absent.
     */
    protected function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('ahg_ai_chatbot_message')) {
                return;
            }

            DB::statement("
                CREATE TABLE ahg_ai_chatbot_message (
                    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    session_id      VARCHAR(64) NOT NULL,
                    role            ENUM('user','assistant','system') NOT NULL DEFAULT 'user',
                    content         TEXT NOT NULL,
                    sources         JSON DEFAULT NULL,
                    grounding_score FLOAT(5,4) DEFAULT NULL,
                    model           VARCHAR(100) DEFAULT NULL,
                    tokens_in       INT UNSIGNED DEFAULT NULL,
                    tokens_out      INT UNSIGNED DEFAULT NULL,
                    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX ix_session (session_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable) {
            // Never block boot
        }
    }
}
