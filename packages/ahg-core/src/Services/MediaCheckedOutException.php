<?php

/**
 * MediaCheckedOutException - Heratio ahg-core
 *
 * heratio#1757: thrown when a master file is checked out to someone else and
 * a change to it is refused.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

class MediaCheckedOutException extends \RuntimeException
{
    public function __construct(public readonly object $checkout, string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('This file is checked out to :user since :when, so it cannot be changed.', [
            'user' => $checkout->username ?? ('#'.$checkout->user_id),
            'when' => (string) $checkout->checked_out_at,
        ]));
    }
}
