<?php

/**
 * DonorAgreementReminderMail — donor agreement reminder notification.
 *
 * Issue #1262. Sent by ahg:donor-reminders for each due
 * donor_agreement_reminder row. Recipient resolution and the due query
 * live in DonorRemindersCommand; this class only renders + addresses the
 * message. Content is built inline (no Blade dependency in ahg-core).
 *
 * Expected $context shape:
 *   - subject           (string)  reminder subject line
 *   - reminder_type     (string)  e.g. expiry_warning, review_due
 *   - description       (string|null) reminder body / action notes
 *   - agreement_title   (string)
 *   - agreement_number  (string|null)
 *   - reminder_date     (string|null) ISO date the reminder is due
 *   - expiry_date       (string|null) agreement expiry, if any
 *   - priority          (string|null)
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class DonorAgreementReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $context)
    {
    }

    public function envelope(): Envelope
    {
        $subject = trim((string) ($this->context['subject'] ?? ''));
        if ($subject === '') {
            $title = (string) ($this->context['agreement_title'] ?? 'donor agreement');
            $subject = 'Reminder: '.$title;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function buildHtml(): string
    {
        $c = $this->context;
        $rows = [];
        $add = function (string $label, $value) use (&$rows) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                $rows[] = '<tr><td style="padding:4px 12px 4px 0;font-weight:bold;vertical-align:top;">'
                    .e($label).'</td><td style="padding:4px 0;">'.nl2br(e($value)).'</td></tr>';
            }
        };

        $add('Agreement', $c['agreement_title'] ?? null);
        $add('Agreement number', $c['agreement_number'] ?? null);
        $add('Reminder type', $c['reminder_type'] ?? null);
        $add('Priority', $c['priority'] ?? null);
        $add('Reminder date', $c['reminder_date'] ?? null);
        $add('Agreement expiry', $c['expiry_date'] ?? null);

        $intro = trim((string) ($c['subject'] ?? '')) !== ''
            ? e((string) $c['subject'])
            : 'You have a donor agreement reminder that is now due.';

        $desc = trim((string) ($c['description'] ?? ''));
        $descBlock = $desc !== ''
            ? '<p style="margin:16px 0 0;">'.nl2br(e($desc)).'</p>'
            : '';

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">'
            .'<p style="margin:0 0 12px;">'.$intro.'</p>'
            .'<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'
            .implode('', $rows)
            .'</table>'
            .$descBlock
            .'<p style="margin:24px 0 0;color:#777;font-size:12px;">'
            .'This is an automated reminder from the archive management system.</p>'
            .'</div>';

        return new HtmlString($html);
    }
}
