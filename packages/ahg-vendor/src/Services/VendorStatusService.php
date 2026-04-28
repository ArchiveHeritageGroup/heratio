<?php

/**
 * VendorStatusService — resolve vendor transaction status display config.
 *
 * Source of truth: ahg_dropdown taxonomy 'vendor_transaction_status'
 * (label + color come from the DB row; icon comes from the row when set,
 * else from metadata JSON, else from a small UX fallback map).
 *
 * Why a fallback map: the icon column is currently NULL across rows. Once
 * the dropdowns admin populates metadata->>'$.icon' or the icon column,
 * those values win and this map becomes dead weight (acceptable tradeoff
 * for sane defaults today).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgVendor\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorStatusService
{
    /** UX fallback icons — overridden by ahg_dropdown.icon or metadata.icon when set. */
    protected const ICON_FALLBACK = [
        'pending_approval'     => 'clock',
        'approved'             => 'check',
        'dispatched'           => 'truck',
        'received_by_vendor'   => 'building',
        'in_progress'          => 'spinner',
        'completed'            => 'check-circle',
        'ready_for_collection' => 'box',
        'returned'             => 'undo',
        'cancelled'            => 'times',
    ];

    /**
     * Return display config for one status code:
     *   ['code' => ..., 'label' => ..., 'color_hex' => ..., 'color' => bs5_class, 'icon' => fa_name]
     */
    public function display(string $code, string $taxonomy = 'vendor_transaction_status'): array
    {
        $row = $this->row($code, $taxonomy);
        $hex = $row->color ?? null;
        $meta = $row && ! empty($row->metadata) ? json_decode((string) $row->metadata, true) : null;
        $icon = $row->icon
            ?? ($meta['icon'] ?? null)
            ?? (self::ICON_FALLBACK[$code] ?? 'question');

        return [
            'code'      => $code,
            'label'     => $row->label ?? ucfirst(str_replace('_', ' ', $code)),
            'color_hex' => $hex,
            'color'     => $this->bootstrapClass($hex),
            'icon'      => $icon,
        ];
    }

    /** All active rows of the given taxonomy, keyed by code. */
    public function options(string $taxonomy = 'vendor_transaction_status'): array
    {
        if (! Schema::hasTable('ahg_dropdown')) return [];
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'label'])
            ->mapWithKeys(fn ($r) => [$r->code => $r->label])
            ->toArray();
    }

    protected function row(string $code, string $taxonomy): ?object
    {
        if (! Schema::hasTable('ahg_dropdown')) return null;
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('code', $code)
            ->first();
    }

    /** Map a hex colour to the closest Bootstrap-5 contextual class. */
    protected function bootstrapClass(?string $hex): string
    {
        if (! $hex) return 'secondary';
        $hex = strtolower(ltrim($hex, '#'));
        return match ($hex) {
            '28a745', '20c997', '8bc34a', '4caf50' => 'success',
            'dc3545', 'f44336', 'ff5722'           => 'danger',
            'ffc107', 'fd7e14', 'ff9800'           => 'warning',
            '17a2b8', '6f42c1'                     => 'info',
            '007bff', '0d6efd'                     => 'primary',
            '343a40', '212529'                     => 'dark',
            default                                => 'secondary',
        };
    }
}
