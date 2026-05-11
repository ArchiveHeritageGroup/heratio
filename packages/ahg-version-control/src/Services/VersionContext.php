<?php

/**
 * VersionContext — request-scoped flags that govern automatic version capture.
 *
 * Mirror of the AtoM-side service at
 *   atom-ahg-plugins/ahgVersionControlPlugin/lib/Services/VersionContext.php
 *
 * Identical static API: skip(), enable(), isSkipped(),
 * setSummary()/takeSummary(), setUserId()/takeUserId(), reset().
 *
 * @phase D
 */

namespace AhgVersionControl\Services;

final class VersionContext
{
    private static bool $skipped = false;
    private static ?string $pendingSummary = null;
    private static ?int $pendingUserId = null;

    public static function skip(): void
    {
        self::$skipped = true;
    }

    public static function enable(): void
    {
        self::$skipped = false;
    }

    public static function isSkipped(): bool
    {
        return self::$skipped;
    }

    public static function setSummary(?string $summary): void
    {
        self::$pendingSummary = $summary;
    }

    public static function takeSummary(): ?string
    {
        $s = self::$pendingSummary;
        self::$pendingSummary = null;
        return $s;
    }

    public static function setUserId(?int $userId): void
    {
        self::$pendingUserId = $userId;
    }

    public static function takeUserId(): ?int
    {
        $u = self::$pendingUserId;
        self::$pendingUserId = null;
        return $u;
    }

    public static function reset(): void
    {
        self::$skipped = false;
        self::$pendingSummary = null;
        self::$pendingUserId = null;
    }
}
