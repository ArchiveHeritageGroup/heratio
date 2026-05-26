<?php

/**
 * EventServiceProvider
 *
 * Phase 3 of #674 - wires the EmailAuditListener to the four mail
 * lifecycle events. Laravel 11/12 dropped the framework's stock
 * EventServiceProvider in favour of auto-discovery, but auto-discovery
 * only walks app/Listeners for typed handle($event) methods; we use
 * named per-event handlers (handleMessageSending / handleMessageSent /
 * handleMessageFailed / handleMailSuppressed) to keep the listener
 * single-class instead of fanning out to four files, so the explicit
 * $listen map is the cleanest registration path.
 *
 * Registered in bootstrap/providers.php alongside AppServiceProvider.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Providers;

use App\Events\MailSuppressed;
use App\Listeners\EmailAuditListener;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Event -> [Listener@method] map.
     *
     * @var array<class-string, array<int, string>>
     */
    protected array $listen = [
        MessageSending::class => [EmailAuditListener::class.'@handleMessageSending'],
        MessageSent::class => [EmailAuditListener::class.'@handleMessageSent'],
        MessageFailed::class => [EmailAuditListener::class.'@handleMessageFailed'],
        MailSuppressed::class => [EmailAuditListener::class.'@handleMailSuppressed'],
    ];

    public function boot(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
