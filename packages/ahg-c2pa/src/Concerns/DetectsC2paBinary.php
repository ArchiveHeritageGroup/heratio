<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgC2pa\Concerns;

/**
 * Locate the c2patool binary (config-first, then well-known paths, then PATH).
 * Byte-identical body in C2paService::autodetectBinary (static) and
 * ProvenanceRecordService::detectC2paTool (instance); provided as static with
 * both names. $this->detectC2paTool() still resolves the static alias.
 */
trait DetectsC2paBinary
{
    private static function autodetectBinary(): ?string
    {
        // Config-first: an explicit, env-overridable host path.
        if (function_exists('config')) {
            $configured = config('heratio.c2patool_bin');
            if (is_string($configured) && $configured !== '' && is_executable($configured)) {
                return $configured;
            }
        }

        foreach (['/usr/local/bin/c2patool', '/usr/bin/c2patool'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        $which = @shell_exec('command -v c2patool 2>/dev/null');
        if (is_string($which) && trim($which) !== '') {
            return trim($which);
        }
        return null;
    }

    /** Alias of autodetectBinary() - kept for ProvenanceRecordService's call sites. */
    private static function detectC2paTool(): ?string
    {
        return self::autodetectBinary();
    }
}
