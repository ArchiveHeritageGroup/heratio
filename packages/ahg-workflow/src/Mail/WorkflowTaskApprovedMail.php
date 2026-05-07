<?php

/**
 * WorkflowTaskApprovedMail - notify a user that their workflow task was approved
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

namespace AhgWorkflow\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowTaskApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $context) {}

    public function envelope(): Envelope
    {
        $name = $this->context['workflow_name'] ?? 'Workflow';
        return new Envelope(subject: "Approved: $name");
    }

    public function content(): Content
    {
        return new Content(view: 'ahg-workflow::emails.task-approved');
    }

    public function attachments(): array { return []; }
}
