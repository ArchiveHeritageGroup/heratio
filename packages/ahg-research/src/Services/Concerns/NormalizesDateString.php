<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Services\Concerns;

/**
 * Parse a value to a Y-m-d date string, or null on empty/unparseable.
 * Byte-identical dateOrNull() in Ethics/Funding/Output/Team services.
 */
trait NormalizesDateString
{
    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
