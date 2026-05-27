<?php

/**
 * PublishRequestDecisionNotification - sent to a submitter when a curator
 * records a decision (approved / rejected / edited) on their publish
 * request (Heratio #745).
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author:    Johan Pieterse <johan@plainsailingisystems.co.za>
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgRequestPublish\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublishRequestDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $status,
        public string $receiptUrl,
        public ?string $curatorNotes = null,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = match ($this->status) {
            'approved' => 'Publish request approved',
            'rejected' => 'Publish request rejected',
            'edited'   => 'Publish request updated',
            default    => 'Publish request status changed',
        };

        $msg = (new MailMessage)
            ->subject($subject)
            ->line('A curator has updated the status of your publish request.')
            ->line('New status: '.ucfirst($this->status));

        if ($this->curatorNotes) {
            $msg->line('Curator notes: '.$this->curatorNotes);
        }

        return $msg->action('View receipt', $this->receiptUrl);
    }

    /** @return array<string,mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'token' => $this->token,
            'status' => $this->status,
            'receipt_url' => $this->receiptUrl,
        ];
    }
}
