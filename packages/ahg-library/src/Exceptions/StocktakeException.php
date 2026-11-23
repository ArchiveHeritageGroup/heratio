<?php

/**
 * StocktakeException - a business-rule refusal whose message is safe to show
 * to staff. Any other failure is logged and reported generically (#1474).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Exceptions;

use RuntimeException;

class StocktakeException extends RuntimeException
{
}
