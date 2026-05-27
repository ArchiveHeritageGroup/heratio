<?php

/**
 * PublishRequestSubmittedNotification - acknowledgement email sent to a
 * submitter when a new request lands in ahg_publish_request (Heratio #745).
 *
 * Stub implementation: ships the receipt URL + token in a MailMessage. Mail
 * driver is whatever MAIL_MAILER resolves to (log/array in tests, smtp in
 * prod). The class is intentionally minimal - templating + branded layout
 * are a follow-up.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author:    Johan Pieterse <johan@plainsailingisystems.co.za>
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgRequestPublish\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublishRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $receiptUrl,
        public ?string $submitterName = null,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $greeting = $this->submitterName
            ? 'Hello '.$this->submitterName.','
            : 'Hello,';

        return (new MailMessage)
            ->subject('Publish request received')
            ->greeting($greeting)
            ->line('Thank you for your request to publish. We have received it and a curator will review it shortly.')
            ->action('View receipt', $this->receiptUrl)
            ->line('Keep this link - it is the only way to check the status of your request.');
    }

    /** @return array<string,mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'token' => $this->token,
            'receipt_url' => $this->receiptUrl,
        ];
    }
}
