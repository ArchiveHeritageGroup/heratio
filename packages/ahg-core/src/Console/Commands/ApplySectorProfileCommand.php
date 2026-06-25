<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Console\Commands;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Support\SectorProfiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apply a sector site profile (heratio#1331) - the single engine behind
 * `bin/install --sector=`, the admin "Apply sector profile" action, and direct
 * CLI use. Idempotent + re-applicable: sets the `sector_default` marker, the
 * sector theme palette (ahg_settings), and the sector identifier mask
 * (setting/setting_i18n, read by SectorIdentifierService). All defaults only -
 * no package removal, jurisdiction-neutral.
 */
class ApplySectorProfileCommand extends Command
{
    protected $signature = 'ahg:apply-sector-profile
        {sector : Sector code: archive|museum|gallery|library|dam|research}
        {--with-sample : Also load sample content for the sector (not yet implemented)}';

    protected $description = 'Apply a sector site profile (theme + identifier mask + sector default) over the install';

    public function handle(): int
    {
        $sector = strtolower(trim((string) $this->argument('sector')));

        if (! SectorProfiles::has($sector)) {
            $this->error("Unknown sector '{$sector}'. Valid: ".implode(', ', SectorProfiles::codes()));

            return self::FAILURE;
        }

        $profile = SectorProfiles::get($sector);
        $this->info("Applying sector profile: {$profile['label']} ({$sector})");

        // 1. The canonical per-install sector marker.
        AhgSettingsService::set('sector_default', $sector, 'general');

        // 2. Theme palette (ahg_settings, group 'theme').
        foreach ($profile['theme'] as $key => $value) {
            AhgSettingsService::set($key, $value, 'theme');
        }
        $this->line('  theme: '.count($profile['theme']).' settings applied');

        // 3. Identifier mask (setting/setting_i18n; single-underscore variant wins
        //    in SectorIdentifierService::settingEither over any legacy __ mask).
        $this->putSetting("sector_{$sector}_identifier_mask", $profile['mask']);
        $this->putSetting("sector_{$sector}_identifier_mask_enabled", '1');
        $this->line("  identifier mask: {$profile['mask']} (enabled)");

        AhgSettingsService::clearCache();

        if ($this->option('with-sample')) {
            $this->warn('  --with-sample: sample content is not implemented yet (planned follow-up slice).');
        }

        $this->info("Sector profile '{$sector}' applied. Re-run any time to switch sectors.");

        return self::SUCCESS;
    }

    /**
     * Upsert an AtoM-style setting (setting + setting_i18n, scope NULL, culture en).
     */
    private function putSetting(string $name, string $value): void
    {
        if (! Schema::hasTable('setting') || ! Schema::hasTable('setting_i18n')) {
            $this->warn("  setting/setting_i18n missing; skipped {$name}");

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
