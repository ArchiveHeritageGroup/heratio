<?php

/**
 * BackupFailedMail - Heratio
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

namespace AhgBackup\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the configured admin email when a backup run fails outright
 * (no usable artefacts were written). Closes Phase 2 of #671.
 *
 * $backup payload fields:
 *   - id            (string)  e.g. 'failed-<timestamp>'
 *   - components    (array)   requested components
 *   - partial_files (array)   any files that DID land before failure
 *   - errors        (array)   list of error strings from create()
 *   - duration_ms   (int)     runtime of the create() action
 *   - status        (string)  'failed'
 *   - completed_at  (string)  ISO 8601 timestamp
 */
class BackupFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $backup) {}

    public function envelope(): Envelope
    {
        $when = $this->backup['completed_at'] ?? gmdate('Y-m-d H:i \U\T\C');

        return new Envelope(
            subject: 'Heratio backup FAILED: '.$when
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'ahg-backup::emails.backup-failed',
            text: 'ahg-backup::emails.backup-failed-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
