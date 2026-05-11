<?php

/**
 * Raised by RestoreService when the user lacks security clearance.
 *
 * @phase J
 */

namespace AhgVersionControl\Services;

class InsufficientClearanceException extends \RuntimeException
{
}
