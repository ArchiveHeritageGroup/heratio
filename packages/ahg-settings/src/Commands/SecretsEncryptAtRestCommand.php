<?php

/**
 * SecretsEncryptAtRestCommand — #1395(D) one-off (idempotent) backfill that
 * encrypts existing plaintext integration secrets in place, so a DB dump of the
 * settings tables never exposes a usable key. Complements the encrypt-on-write
 * now in the settings save paths and SecretCrypto::reveal() at every consumer.
 *
 * Scans the three settings stores for rows whose key is a known secret
 * (SettingsService::secretKeys()):
 *   - setting / setting_i18n  (AtoM settings — value in setting_i18n.value)
 *   - ahg_settings            (value in setting_value)
 *   - icip_config             (value in config_value)
 *
 * For each such row with a non-empty value that is not already Crypt ciphertext,
 * the value is replaced with SecretCrypto::conceal(). Already-encrypted rows are
 * skipped (isEncrypted), so the command is safe to run repeatedly and safe to
 * run before or after any given key's write path is deployed.
 *
 *   php artisan ahg:secrets-encrypt-at-rest            # apply
 *   php artisan ahg:secrets-encrypt-at-rest --dry-run  # report only, no writes
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgSettings\Commands;

use AhgCore\Services\SecretCrypto;
use AhgSettings\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecretsEncryptAtRestCommand extends Command
{
    protected $signature = 'ahg:secrets-encrypt-at-rest
        {--dry-run : Report what would change without writing}';

    protected $description = '#1395(D) Encrypt existing plaintext integration secrets at rest (idempotent backfill)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $keys = SettingsService::secretKeys();
        $encrypted = 0;
        $skipped = 0;

        $this->info($dry ? 'Dry run — no changes will be written.' : 'Encrypting plaintext secrets at rest…');

        // ── setting / setting_i18n (keyed by setting.name) ──
        if (Schema::hasTable('setting') && Schema::hasTable('setting_i18n')) {
            $rows = DB::table('setting')
                ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
                ->whereIn('setting.name', $keys)
                ->select('setting_i18n.id', 'setting_i18n.culture', 'setting.name', 'setting_i18n.value')
                ->get();
            foreach ($rows as $r) {
                [$did, $reason] = $this->process(
                    'setting_i18n', $r->name, (string) ($r->value ?? ''), $dry,
                    fn (string $ct) => DB::table('setting_i18n')
                        ->where('id', $r->id)->where('culture', $r->culture)
                        ->update(['value' => $ct])
                );
                $did ? $encrypted++ : $skipped++;
            }
        }

        // ── ahg_settings (keyed by setting_key) ──
        if (Schema::hasTable('ahg_settings')) {
            $rows = DB::table('ahg_settings')
                ->whereIn('setting_key', $keys)
                ->select('id', 'setting_key', 'setting_value')
                ->get();
            foreach ($rows as $r) {
                [$did] = $this->process(
                    'ahg_settings', $r->setting_key, (string) ($r->setting_value ?? ''), $dry,
                    fn (string $ct) => DB::table('ahg_settings')->where('id', $r->id)->update(['setting_value' => $ct])
                );
                $did ? $encrypted++ : $skipped++;
            }
        }

        // ── icip_config (keyed by config_key) ──
        if (Schema::hasTable('icip_config')) {
            $rows = DB::table('icip_config')
                ->whereIn('config_key', $keys)
                ->select('id', 'config_key', 'config_value')
                ->get();
            foreach ($rows as $r) {
                [$did] = $this->process(
                    'icip_config', $r->config_key, (string) ($r->config_value ?? ''), $dry,
                    fn (string $ct) => DB::table('icip_config')->where('id', $r->id)->update(['config_value' => $ct])
                );
                $did ? $encrypted++ : $skipped++;
            }
        }

        $this->line('');
        $this->info(sprintf('%s %d value(s); %d already-encrypted/empty skipped.',
            $dry ? 'Would encrypt' : 'Encrypted', $encrypted, $skipped));

        return self::SUCCESS;
    }

    /**
     * Decide + (optionally) apply encryption for one row. Returns [didEncrypt, reason].
     */
    private function process(string $store, string $key, string $value, bool $dry, callable $write): array
    {
        if ($value === '') {
            return [false, 'empty'];
        }
        if (SecretCrypto::isEncrypted($value)) {
            return [false, 'already-encrypted'];
        }

        $this->line(sprintf('  %s %s.%s', $dry ? '[dry]' : '[enc]', $store, $key));

        if (! $dry) {
            $write(SecretCrypto::conceal($value));
        }

        return [true, 'encrypted'];
    }
}
