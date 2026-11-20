<?php

/**
 * DisposalActionTypes - heratio#1740 follow-up.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The disposal action types, read from the Dropdown Manager (taxonomy
 * rm_disposal_action_type). Labels, descriptions, order and which types are
 * offered live there. A type can only be offered when the executor knows what
 * to do with it, so a code added in the Dropdown Manager without code support
 * is not offered (it would fail at execution).
 */
class DisposalActionTypes
{
    public const TAXONOMY = 'rm_disposal_action_type';

    /** Codes DisposalController::execute() and DisposalExecutionService can carry out. */
    public const EXECUTABLE = ['destroy', 'transfer_archives', 'transfer_external', 'retain_permanent', 'review'];

    /** @var array<string, object>|null per-request cache, every row incl. inactive */
    private static ?array $rows = null;

    /** Active, executable types in Dropdown Manager order: code => {code, label, description, badge}. */
    public static function offered(): array
    {
        return array_filter(self::rows(), fn ($t) => $t->is_active && in_array($t->code, self::EXECUTABLE, true));
    }

    /** Codes the initiate form may submit. */
    public static function codes(): array
    {
        return array_keys(self::offered());
    }

    /** code => label for every type, active or not, so older rows still read well. */
    public static function labels(): array
    {
        return array_map(fn ($t) => $t->label, self::rows());
    }

    /** code => Bootstrap badge colour. */
    public static function badges(): array
    {
        return array_map(fn ($t) => $t->badge, self::rows());
    }

    public static function flush(): void
    {
        self::$rows = null;
    }

    private static function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }
        self::$rows = [];
        if (! Schema::hasTable('ahg_dropdown')) {
            return self::$rows;
        }
        foreach (DB::table('ahg_dropdown')->where('taxonomy', self::TAXONOMY)->orderBy('sort_order')->orderBy('label')->get() as $r) {
            $meta = json_decode((string) $r->metadata, true) ?: [];
            self::$rows[$r->code] = (object) [
                'code' => $r->code,
                'label' => $r->label,
                'description' => (string) ($meta['description'] ?? ''),
                'badge' => (string) ($meta['badge'] ?? 'secondary'),
                'is_active' => (bool) $r->is_active,
            ];
        }

        return self::$rows;
    }
}
