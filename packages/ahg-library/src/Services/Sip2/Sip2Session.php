<?php

/**
 * Sip2Session - the state of one terminal connection (#1475).
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Services\Sip2;

final class Sip2Session
{
    public ?object $account = null;

    public ?string $lastRequest = null;

    public ?string $lastResponse = null;
}
