<?php

/**
 * RepatriationClaimRegisteredMail - Heratio
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

namespace AhgSemanticSearch\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the claimant community (when a contact email is given) confirming that
 * their repatriation claim has been received and recorded. #1207.
 */
class RepatriationClaimRegisteredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public object $claim) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your repatriation claim has been received - reference #'.($this->claim->id ?? ''));
    }

    public function content(): Content
    {
        return new Content(view: 'ahg-semantic-search::emails.repatriation.registered');
    }

    public function attachments(): array
    {
        return [];
    }
}
