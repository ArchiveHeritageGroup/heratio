<?php

/**
 * SpectrumProcedureCatalog - heratio Spectrum#A: canonical list of the 21
 * Spectrum 5.1 primary procedures.
 *
 * Used as the source of truth for the spectrum_procedure dropdown on the
 * workflow edit form, the column label on the workflow admin list, and the
 * Spectrum badge on workflow diagrams.
 *
 * Source: UK Collections Trust, Spectrum 5.1.
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
        'object_entry'        => 'Object entry',
        'acquisition'         => 'Acquisition and accessioning',
        'inventory'           => 'Inventory',
        'location_movement'   => 'Location and movement control',
        'cataloguing'         => 'Cataloguing',
        'object_exit'         => 'Object exit',
        'loans_in'            => 'Loans in (borrowing)',
        'loans_out'           => 'Loans out (lending)',
        'insurance'           => 'Insurance and indemnity',
        'damage_loss'         => 'Damage and loss',
        'conservation'        => 'Conservation and collections care',
        'audit'               => 'Audit',
        'condition_check'     => 'Object condition checking and technical assessment',
        'valuation'           => 'Object valuation',
        'risk_management'     => 'Risk management',
        'emergency_planning'  => 'Emergency planning for collections',
        'use_of_collections'  => 'Use of collections',
        'rights_management'   => 'Rights management',
        'reproduction'        => 'Reproduction',
        'deaccessioning'      => 'Deaccessioning and disposal',
        'retrospective_doc'   => 'Retrospective documentation',
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
     * (defensive — never null), or empty string when no code is supplied.
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
     * unknown — i.e. silently drops invalid codes rather than persisting
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
