<?php
/**
 * Heratio - AI Compliance routes (Article 12 record-keeping).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

use AhgAiCompliance\Controllers\PublicKeyController;
use Illuminate\Support\Facades\Route;

// Public verifier endpoint. Stable URL - external auditors / regulators
// fetch this once and pin the key against their copy of the chain.
Route::get('/.well-known/ai-inference-pubkey', [PublicKeyController::class, 'show'])
    ->name('ai-compliance.pubkey');
