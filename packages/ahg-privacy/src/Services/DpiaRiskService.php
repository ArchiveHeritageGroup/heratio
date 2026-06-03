<?php

/**
 * DpiaRiskService - GDPR Article 35(3) / WP29 high-risk screening for ROPA
 * (Article 30) processing activities (#1109).
 *
 * The screen reads the existing Article 30 register fields (categories of data,
 * purpose, cross-border transfer + safeguards) and flags an activity as
 * requiring a DPIA when ANY of the four named high-risk triggers is present:
 *
 *   1. special category data (Art 9 / Art 10 categories)
 *   2. large-scale profiling / systematic monitoring / automated decisions
 *   3. biometric or genetic processing
 *   4. cross-border transfer to a non-adequate jurisdiction (outside EEA with
 *      no documented safeguards)
 *
 * A DPO override (special_category_override etc.) forces a determination the
 * keyword heuristic would otherwise miss; NULL means "let the heuristic decide".
 *
 * screen() is a pure function over a normalised array so it is unit-testable
 * with no database or container. assessAndFlag() wraps it to persist the
 * result on a ProcessingActivity model and log the change.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Services;

use AhgPrivacy\Models\ProcessingActivity;

class DpiaRiskService
{
    /** Art 9/10 special-category keyword set. */
    private const SPECIAL_CATEGORY_TERMS = [
        'health', 'medical', 'racial', 'race', 'ethnic', 'ethnicity',
        'political', 'religion', 'religious', 'philosophical', 'belief',
        'genetic', 'biometric', 'sex life', 'sexual', 'orientation',
        'trade union', 'criminal', 'offence', 'conviction', 'disability',
    ];

    /** Profiling / systematic-monitoring keyword set. */
    private const PROFILING_TERMS = [
        'profil', 'scoring', 'score', 'systematic monitoring', 'monitoring',
        'automated decision', 'automated-decision', 'evaluation', 'evaluat',
        'predict', 'behavioural', 'behavioral', 'tracking', 'surveillance',
    ];

    /** Biometric / genetic keyword set. */
    private const BIOMETRIC_TERMS = [
        'biometric', 'genetic', 'fingerprint', 'facial recognition',
        'face recognition', 'iris', 'voiceprint', 'dna',
    ];

    /**
     * Screen a normalised activity payload for the four high-risk triggers.
     *
     * @param array{
     *     name?:string, purpose?:string,
     *     categories_of_data?:array<int,string>,
     *     categories_of_subjects?:array<int,string>,
     *     transfers_outside_eea?:bool, safeguards?:string,
     *     special_category_override?:?int, large_scale_profiling_override?:?int,
     *     biometric_override?:?int
     * } $a
     * @return array{high_risk:bool,triggers:array<int,string>,note:string}
     */
    public function screen(array $a): array
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($a['name'] ?? ''),
            (string) ($a['purpose'] ?? ''),
            implode(' ', (array) ($a['categories_of_data'] ?? [])),
            implode(' ', (array) ($a['categories_of_subjects'] ?? [])),
        ])));

        $triggers = [];

        if ($this->resolve($a['special_category_override'] ?? null, $haystack, self::SPECIAL_CATEGORY_TERMS)) {
            $triggers[] = 'special_category';
        }
        if ($this->resolve($a['large_scale_profiling_override'] ?? null, $haystack, self::PROFILING_TERMS)) {
            $triggers[] = 'large_scale_profiling';
        }
        if ($this->resolve($a['biometric_override'] ?? null, $haystack, self::BIOMETRIC_TERMS)) {
            $triggers[] = 'biometric';
        }
        if (($a['transfers_outside_eea'] ?? false) && trim((string) ($a['safeguards'] ?? '')) === '') {
            $triggers[] = 'cross_border_non_adequate';
        }

        return [
            'high_risk' => $triggers !== [],
            'triggers'  => $triggers,
            'note'      => $this->describe($triggers),
        ];
    }

    /**
     * Screen a ProcessingActivity model, persist dpia_required + the screening
     * note, and return the screen result. Does not flip dpia_completed (that is
     * set only when a linked DPIA is signed off, via DpiaService).
     *
     * @return array{high_risk:bool,triggers:array<int,string>,note:string}
     */
    public function assessAndFlag(ProcessingActivity $activity): array
    {
        $result = $this->screen([
            'name'                           => (string) $activity->name,
            'purpose'                        => (string) $activity->purpose,
            'categories_of_data'             => (array) ($activity->categories_of_data ?? []),
            'categories_of_subjects'         => (array) ($activity->categories_of_subjects ?? []),
            'transfers_outside_eea'          => (bool) $activity->transfers_outside_eea,
            'safeguards'                     => (string) ($activity->safeguards ?? ''),
            'special_category_override'      => $this->nullableBool($activity->special_category_override),
            'large_scale_profiling_override' => $this->nullableBool($activity->large_scale_profiling_override),
            'biometric_override'             => $this->nullableBool($activity->biometric_override),
        ]);

        $activity->dpia_required = $result['high_risk'];
        $activity->dpia_screening_note = $result['note'];
        $activity->save();

        return $result;
    }

    /**
     * @param int[]|string[]|array<int,string> $terms
     */
    private function resolve(?int $override, string $haystack, array $terms): bool
    {
        if ($override !== null) {
            return $override === 1;
        }
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,string> $triggers */
    private function describe(array $triggers): string
    {
        if ($triggers === []) {
            return 'Auto-screen: no high-risk triggers detected. DPIA not required on screening grounds.';
        }
        $labels = [
            'special_category'          => 'special category data',
            'large_scale_profiling'     => 'large-scale profiling / systematic monitoring',
            'biometric'                 => 'biometric or genetic processing',
            'cross_border_non_adequate' => 'cross-border transfer without documented safeguards',
        ];
        $named = array_map(static fn (string $t): string => $labels[$t] ?? $t, $triggers);
        return 'Auto-screen: DPIA required - ' . implode('; ', $named) . '.';
    }

    /** Treat an unset override (null) distinctly from an explicit 0/1. */
    private function nullableBool($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return ((int) $value) === 1 ? 1 : 0;
    }
}
