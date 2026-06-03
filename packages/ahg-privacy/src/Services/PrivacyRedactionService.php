<?php

/**
 * PrivacyRedactionService - field-level structured redaction for archival
 * description metadata (#1108). Applies per-field redaction to an IO's
 * metadata for non-privileged viewers, leaves it intact for admins, and logs
 * every decision/view with field + legal basis.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Services;

use AhgPrivacy\Models\InformationObjectPrivacy;
use AhgPrivacy\Models\InformationObjectPrivacyLog;
use Illuminate\Database\Eloquent\Model;

class PrivacyRedactionService
{
    public const FULL_PLACEHOLDER = '[REDACTED — personal data removed]';

    public const PARTIAL_PLACEHOLDER = '[PARTIALLY REDACTED]';

    /** Privacy profile (+ fields, reason) for an IO id, or null if none. */
    public function getPrivacyProfile(int $informationObjectId): ?InformationObjectPrivacy
    {
        try {
            return InformationObjectPrivacy::with(['fields', 'reason'])
                ->where('information_object_id', $informationObjectId)
                ->first();
        } catch (\Throwable $e) {
            return null; // schema not installed yet — no redaction
        }
    }

    /**
     * Whether this user may see the unredacted record. Administrators always
     * may. (Researcher-with-active-agreement access plugs in here when that
     * mechanism lands; until then only admins bypass — fail closed.)
     */
    public function canViewUnredacted(?int $userId): bool
    {
        if ($userId === null) {
            return false; // anonymous / public
        }
        if (class_exists(\AhgCore\Services\AclService::class)) {
            return \AhgCore\Services\AclService::canAdmin($userId);
        }

        return false;
    }

    /**
     * Return the IO with per-field redaction applied for the given viewer.
     * Privileged viewers get the original instance; everyone else gets a
     * redacted clone (the persisted record is never mutated).
     */
    public function applyRedaction(Model $io, ?int $userId): Model
    {
        $profile = $this->getPrivacyProfile((int) $io->getKey());
        if (! $profile || $profile->fields->isEmpty()) {
            return $io;
        }
        if ($this->canViewUnredacted($userId)) {
            return $io;
        }

        $redacted = $io->replicate();
        $redacted->setRawAttributes($io->getAttributes(), true);
        if ($io->getKey() !== null) {
            $redacted->forceFill([$io->getKeyName() => $io->getKey()]);
        }

        foreach ($profile->fields as $field) {
            $value = $redacted->getAttribute($field->field_name);
            if ($value === null || $value === '') {
                continue;
            }
            $redacted->setAttribute(
                $field->field_name,
                $this->redactValue((string) $value, $field->redaction_type, $field->redaction_pattern)
            );
        }

        return $redacted;
    }

    /**
     * Redact a single value by type. Public + pure so it's directly testable.
     */
    public function redactValue(string $value, string $type, ?string $pattern = null): string
    {
        return match ($type) {
            'partial' => $pattern ? $this->applyPattern($value, $pattern) : self::PARTIAL_PLACEHOLDER,
            'pseudonymised' => $this->pseudonymise($value),
            default => self::FULL_PLACEHOLDER, // 'full'
        };
    }

    public function applyPattern(string $value, string $pattern): string
    {
        return match ($pattern) {
            'email_partial' => preg_replace('/^(.).*@.*$/', '$1***@***', $value) ?: self::PARTIAL_PLACEHOLDER,
            'phone_partial' => preg_replace('/\d(?=\d{4})/', '*', $value) ?: self::PARTIAL_PLACEHOLDER,
            'id_last4' => strlen($value) > 4
                ? str_repeat('*', strlen($value) - 4) . substr($value, -4)
                : str_repeat('*', strlen($value)),
            'year_only' => preg_match('/(\d{4})/', $value, $m) ? $m[1] : self::PARTIAL_PLACEHOLDER,
            default => self::PARTIAL_PLACEHOLDER,
        };
    }

    /** Stable, non-reversible pseudonym (e.g. "Subject-4f9a2c"). */
    public function pseudonymise(string $value): string
    {
        return 'Subject-' . substr(hash('sha256', $value), 0, 6);
    }

    /** Append an audit row (who/when/what field/legal basis). Best-effort. */
    public function logAccess(int $informationObjectId, ?int $userId, string $action, ?string $fieldName = null, ?string $legalBasis = null, ?int $privacyFieldId = null): void
    {
        try {
            InformationObjectPrivacyLog::create([
                'information_object_id' => $informationObjectId,
                'privacy_field_id' => $privacyFieldId,
                'user_id' => $userId,
                'action' => $action,
                'field_name' => $fieldName,
                'legal_basis' => $legalBasis,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // never let audit-logging break the read/write path
        }
    }
}
