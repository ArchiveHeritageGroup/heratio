<?php

/**
 * SpectrumProcedureCatalog - the 21 Museum Workflow procedures, The AHG's own
 * set of museum collection workflows (rewritten November 2026). The class and
 * the spectrum_procedure column keep their historical internal names; the
 * codes are unchanged so existing workflows still match.
 *
 * Used as the source of truth for the procedure dropdown on the workflow edit
 * form, the column label on the workflow admin list, and the procedure badge
 * on workflow diagrams.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgWorkflow\Services;

class SpectrumProcedureCatalog
{
    /**
     * code => human label. Codes match the COMMENT enumeration on the
     * ahg_workflow.spectrum_procedure column.
     */
    public const PROCEDURES = [
        'object_entry' => 'Incoming objects',
        'acquisition' => 'Acquiring an object',
        'inventory' => 'Stocktake',
        'location_movement' => 'Moving objects',
        'cataloguing' => 'Describing an object',
        'object_exit' => 'Outgoing objects',
        'loans_in' => 'Borrowing objects',
        'loans_out' => 'Lending objects',
        'insurance' => 'Insurance cover',
        'damage_loss' => 'Damage or loss',
        'conservation' => 'Care and treatment',
        'audit' => 'Records audit',
        'condition_check' => 'Condition check',
        'valuation' => 'Valuing an object',
        'risk_management' => 'Collection risks',
        'emergency_planning' => 'Emergency readiness',
        'use_of_collections' => 'Access and use',
        'rights_management' => 'Rights',
        'reproduction' => 'Copies and images',
        'deaccessioning' => 'Removing from the collection',
        'retrospective_doc' => 'Documentation backlog',
    ];

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::PROCEDURES;
    }

    /** @return array<int,string> List of codes only. */
    public static function codes(): array
    {
        return array_keys(self::PROCEDURES);
    }

    /**
     * Look up a label for a code. Returns the code itself if not found
     * (defensive - never null), or empty string when no code is supplied.
     */
    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return self::PROCEDURES[$code] ?? $code;
    }

    /**
     * Normalise a code coming from form input. Returns null if blank or
     * unknown - i.e. silently drops invalid codes rather than persisting
     * garbage.
     */
    public static function normalize(?string $code): ?string
    {
        if ($code === null || trim((string) $code) === '') {
            return null;
        }
        $code = trim((string) $code);

        return isset(self::PROCEDURES[$code]) ? $code : null;
    }
}
