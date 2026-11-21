<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgMetadataExport\Services\Exporters\Concerns;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

/**
 * Load a scalar property value (unserialising a stored string/array to text).
 * Byte-identical body in DublinCoreQualifiedSerializer::loadProperty and
 * ModsSerializer::loadFirstScalarProperty; both names provided.
 */
trait LoadsScalarProperty
{
    private function loadProperty(int $ioId, string $name, string $culture): string
    {
        $raw = DB::table('property')
            ->tap(fn ($q) => I18nFallback::join($q, 'property_i18n', 'property.id', $culture, required: true))
            ->where('property.object_id', $ioId)
            ->where('property.name', $name)
            ->value(I18nFallback::col('property_i18n', 'value', 'value'));
        if (! $raw) {
            return '';
        }
        $decoded = @unserialize($raw, ['allowed_classes' => false]);
        if (is_string($decoded)) {
            return $decoded;
        }
        if (is_array($decoded)) {
            return implode("\n\n", array_filter($decoded));
        }

        return (string) $raw;
    }

    /** Alias of loadProperty() - kept for ModsSerializer's call sites. */
    private function loadFirstScalarProperty(int $ioId, string $name, string $culture): string
    {
        return $this->loadProperty($ioId, $name, $culture);
    }
}
