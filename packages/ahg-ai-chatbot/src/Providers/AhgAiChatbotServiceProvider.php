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
use AhgAiChatbot\Services\ChatbotSkillService;
use AhgAiChatbot\Services\PreservationKnowledgeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgAiChatbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatbotSkillService::class);
        $this->app->singleton(PreservationKnowledgeService::class);
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

        // The chatbot views @extends('layouts/admin'), a non-namespaced layout
        // name that Heratio never defined. Register a compat path on the default
        // view finder so that name resolves to a bridge that extends the
        // canonical theme::layouts.1col. Keeps the locked chatbot blades intact.
        $this->callAfterResolving('view', function ($factory): void {
            $factory->getFinder()->addLocation(__DIR__ . '/../../resources/compat-views');
        });

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

        // #1095 - WhatsApp inbound webhook on the stateless `api` group so the
        // external POST is exempt from CSRF. Routes 404 unless the channel is
        // enabled (enforced in the controller).
        if (is_file(__DIR__ . '/../../routes/webhooks.php')) {
            $router->middleware(['api'])
                ->group(__DIR__ . '/../../routes/webhooks.php');
        }

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
