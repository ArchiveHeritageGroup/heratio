<?php

/**
 * BackupCompletedMail - Heratio
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
 * Sent to the configured admin email when a backup run completes
 * successfully. Closes Phase 2 of #671 (email notification wire-up).
 *
 * The $backup payload is a plain array (not an Eloquent model) so the
 * queued job is trivially serialisable and survives across queue
 * workers — fields:
 *   - id          (string)  e.g. md5(filename) or 'multi-<timestamp>'
 *   - components  (array)   ['database', 'uploads', ...]
 *   - files       (array)   list of file rows (filename / size human)
 *   - size_bytes  (int)     total bytes across files
 *   - duration_ms (int)     runtime of the create() action
 *   - status      (string)  'success' | 'success_with_warnings'
 *   - warnings    (array)   optional non-fatal messages
 *   - completed_at(string)  ISO 8601 timestamp
 */
class BackupCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $backup) {}

    public function envelope(): Envelope
    {
        $when = $this->backup['completed_at'] ?? gmdate('Y-m-d H:i \U\T\C');
        $suffix = ($this->backup['status'] ?? 'success') === 'success_with_warnings'
            ? ' (with warnings)'
            : '';

        return new Envelope(
            subject: 'Heratio backup completed: '.$when.$suffix
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'ahg-backup::emails.backup-completed',
            text: 'ahg-backup::emails.backup-completed-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
