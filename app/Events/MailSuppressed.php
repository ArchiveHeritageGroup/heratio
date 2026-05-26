<?php

/**
 * MailSuppressed event
 *
 * Phase 3 of #674. Fired by EmailSuppressionGate when a dispatch is
 * blocked because the recipient is on the bounce / complaint list.
 * EmailAuditListener listens for this and inserts a status=suppressed
 * row in ahg_sent_email so the operator has a complete picture of every
 * email attempt - sent, failed, AND silently dropped.
 *
 * The mailable_class field on the audit row records the upstream caller
 * (e.g. AhgWorkflow\Mail\WorkflowTaskOverdueMail) so we can answer
 * questions like "how many overdue nags did we drop last month?" without
 * trawling logs.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MailSuppressed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public ?string $mailableClass = null,
        public ?string $subject = null,
        public ?string $reason = null,
        public ?int $tenantId = null,
        public ?string $locale = null,
    ) {}
}
