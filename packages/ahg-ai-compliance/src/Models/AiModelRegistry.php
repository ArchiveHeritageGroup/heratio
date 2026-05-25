<?php
/**
 * Heratio - AI model registry Eloquent model (Article 11 / Annex IV).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Models;

use Illuminate\Database\Eloquent\Model;

final class AiModelRegistry extends Model
{
    protected $table = 'ai_model_registry';

    protected $guarded = [];

    protected $casts = [
        'deployed_at'           => 'datetime',
        'retired_at'            => 'datetime',
        'accuracy_metrics_json' => 'array',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    /** Convenience scope: rows for a service ordered newest-deployment first. */
    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service)->orderByDesc('deployed_at');
    }

    /** Latest non-retired entry for a service (the model currently in production). */
    public static function current(string $service): ?self
    {
        return static::query()
            ->where('service', $service)
            ->whereNull('retired_at')
            ->orderByDesc('deployed_at')
            ->first();
    }
}
