<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Services;

use AhgCore\Support\SectorProfiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies a sector site profile (heratio#1331) - the shared engine behind the
 * `ahg:apply-sector-profile` command (fresh install via bin/install --sector)
 * AND the admin "Apply sector profile" UI (existing installs). Idempotent +
 * re-applicable; defaults only (no package removal), jurisdiction-neutral.
 */
class SectorProfileService
{
    /**
     * @return array{sector:string,label:string,theme_count:int,mask:string}
     *
     * @throws \InvalidArgumentException on an unknown sector
     */
    public function apply(string $sector): array
    {
        $sector = strtolower(trim($sector));
        if (! SectorProfiles::has($sector)) {
            throw new \InvalidArgumentException("Unknown sector '{$sector}'. Valid: ".implode(', ', SectorProfiles::codes()));
        }

        $profile = SectorProfiles::get($sector);

        // 1. Canonical per-install sector marker.
        AhgSettingsService::set('sector_default', $sector, 'general');

        // 2. Theme palette (ahg_settings, group 'theme').
        foreach ($profile['theme'] as $key => $value) {
            AhgSettingsService::set($key, $value, 'theme');
        }

        // 3. Identifier mask (setting/setting_i18n; single-underscore variant wins
        //    in SectorIdentifierService::settingEither over any legacy __ mask).
        $this->putSetting("sector_{$sector}_identifier_mask", $profile['mask']);
        $this->putSetting("sector_{$sector}_identifier_mask_enabled", '1');

        AhgSettingsService::clearCache();

        return [
            'sector'      => $sector,
            'label'       => $profile['label'],
            'theme_count' => count($profile['theme']),
            'mask'        => $profile['mask'],
        ];
    }

    /** The currently-applied sector code (or null if none). */
    public function current(): ?string
    {
        return AhgSettingsService::get('sector_default');
    }

    /**
     * Upsert an AtoM-style setting (setting + setting_i18n, scope NULL, culture en).
     */
    private function putSetting(string $name, string $value): void
    {
        if (! Schema::hasTable('setting') || ! Schema::hasTable('setting_i18n')) {
            return;
        }

        $id = DB::table('setting')->whereNull('scope')->where('name', $name)->value('id');
        if (! $id) {
            $id = DB::table('setting')->insertGetId([
                'name'           => $name,
                'scope'          => null,
                'editable'       => 1,
                'deleteable'     => 0,
                'source_culture' => 'en',
                'serial_number'  => 0,
            ]);
        }

        DB::table('setting_i18n')->updateOrInsert(
            ['id' => $id, 'culture' => 'en'],
            ['value' => $value]
        );
    }
}
