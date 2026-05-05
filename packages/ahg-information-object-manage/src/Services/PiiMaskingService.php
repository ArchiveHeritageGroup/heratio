<?php
/*
 * Heratio
 * Copyright (c) 2024-2026 Johan Pieterse / Plain Sailing Information Systems / The Archive and Heritage Group (Pty) Ltd
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PiiMaskingService
{
    private static array $cache = [];

    private static array $maskPatterns = [
        'PERSON'        => '[NAME REDACTED]',
        'SA_ID'         => '[ID REDACTED]',
        'NG_NIN'        => '[ID REDACTED]',
        'PASSPORT'      => '[PASSPORT REDACTED]',
        'EMAIL'         => '[EMAIL REDACTED]',
        'PHONE_SA'      => '[PHONE REDACTED]',
        'PHONE_INTL'    => '[PHONE REDACTED]',
        'BANK_ACCOUNT'  => '[ACCOUNT REDACTED]',
        'TAX_NUMBER'    => '[TAX NUMBER REDACTED]',
        'CREDIT_CARD'   => '[CARD REDACTED]',
        'ORG'           => '[ORG REDACTED]',
        'GPE'           => '[LOCATION REDACTED]',
        'DATE'          => '[DATE REDACTED]',
        'ISAD_PLACE'    => '[LOCATION REDACTED]',
        'ISAD_NAME'     => '[NAME REDACTED]',
        'ISAD_SUBJECT'  => '[SUBJECT REDACTED]',
        'ISAD_DATE'     => '[DATE REDACTED]',
    ];

    public static function getRedactedEntities(int $objectId): array
    {
        if (isset(self::$cache[$objectId])) {
            return self::$cache[$objectId];
        }

        if (!Schema::hasTable('ahg_ner_entity')) {
            return self::$cache[$objectId] = [];
        }

        $rows = DB::table('ahg_ner_entity')
            ->where('object_id', $objectId)
            ->where('status', 'redacted')
            ->select('entity_type', 'entity_value')
            ->get()
            ->all();

        return self::$cache[$objectId] = $rows;
    }

    public static function canBypassMasking(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'isAdministrator')) {
            return (bool) $user->isAdministrator();
        }
        return (bool) ($user->is_admin ?? false);
    }

    public static function mask(int $objectId, ?string $content, bool $forceShow = false): ?string
    {
        if ($content === null || $content === '') {
            return $content;
        }

        if (!$forceShow && self::canBypassMasking()) {
            return $content;
        }

        $entities = self::getRedactedEntities($objectId);
        if (empty($entities)) {
            return $content;
        }

        // Replace longer matches first so substrings don't pre-empt them.
        usort($entities, fn($a, $b) => strlen($b->entity_value) - strlen($a->entity_value));

        foreach ($entities as $entity) {
            $mask = self::$maskPatterns[$entity->entity_type] ?? '[REDACTED]';
            $content = str_ireplace($entity->entity_value, $mask, $content);
        }

        return $content;
    }

    public static function hasRedactedPii(int $objectId): bool
    {
        return count(self::getRedactedEntities($objectId)) > 0;
    }

    public static function getRedactedCount(int $objectId): int
    {
        return count(self::getRedactedEntities($objectId));
    }

    public static function clearCache(?int $objectId = null): void
    {
        if ($objectId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$objectId]);
        }
    }
}
