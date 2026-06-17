<?php
/**
 * Heratio - EU AI Act system inventory row (Art. 6 classification / Art. 52 tiers).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;

final class AiSystem extends Model
{
    protected $table = 'ai_system';

    protected $guarded = [];

    protected $casts = [
        'is_active'        => 'boolean',
        'last_review_date' => 'date',
        'next_review_date' => 'date',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /** EU AI Act actor roles (Art. 3). */
    public const ROLES = ['provider', 'deployer', 'importer', 'distributor'];

    /** EU AI Act risk tiers (Art. 5 / 6 / 52). */
    public const RISK_CLASSIFICATIONS = ['prohibited', 'high', 'limited', 'minimal'];

    /** Operational lifecycle states. */
    public const LIFECYCLE_STATUSES = ['development', 'deployed', 'suspended', 'retired'];
}
