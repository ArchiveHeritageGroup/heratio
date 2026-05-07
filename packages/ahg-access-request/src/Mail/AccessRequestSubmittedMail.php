<?php

/**
 * AccessRequestSubmittedMail - Heratio
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

namespace AhgAccessRequest\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the requester when they submit a new access request.
 * Closes #95 (third gate of three).
 */
class AccessRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public object $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Access request received - reference #' . $this->request->id);
    }

    public function content(): Content
    {
        return new Content(view: 'ahg-access-request::emails.submitted');
    }

    public function attachments(): array { return []; }
}
