<?php

/**
 * WorkflowTaskOverdueMail
 *
 * Phase 2 of #674 (Email + notifications). The audit table flagged
 * "Task overdue" as having no email coverage; this is the missing
 * Mailable. Dispatched from the workflow-overdue scheduled command
 * when a task's due_at < NOW() and last_notified_at is older than the
 * configured nag interval.
 *
 * Expected $context shape:
 *   - task_id        (int)
 *   - workflow_name  (string)
 *   - assignee_name  (string)
 *   - assignee_email (string)
 *   - due_at         (string, ISO 8601)
 *   - overdue_days   (int)
 *   - task_url       (string)
 *   - preferred_locale (string|null)
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgWorkflow\Mail;

use App\Mail\Concerns\LocaleAwareMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class WorkflowTaskOverdueMail extends Mailable implements ShouldQueue
{
    use LocaleAwareMailable, Queueable, SerializesModels;

    public function __construct(public array $context)
    {
        $this->recipientEmail = $context['assignee_email'] ?? null;
        $this->locale = $context['preferred_locale'] ?? null;
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->resolveEmailLocale());

        $name = $this->context['workflow_name'] ?? 'Workflow';
        $days = (int) ($this->context['overdue_days'] ?? 0);

        return new Envelope(
            subject: __('Overdue: :name (:days days)', ['name' => $name, 'days' => $days]),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->resolveEmailLocale());

        return new Content(
            view: 'ahg-workflow::emails.task-overdue',
            text: 'ahg-workflow::emails.task-overdue-text',
            with: ['ctx' => $this->context],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
