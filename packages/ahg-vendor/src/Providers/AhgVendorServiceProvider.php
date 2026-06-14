<?php

namespace AhgVendor\Providers;

use AhgVendor\Console\VendorEncryptBackfillCommand;
use AhgVendor\Services\VendorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgVendorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VendorService::class, fn () => new VendorService);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'vendor');

        if ($this->app->runningInConsole()) {
            $this->commands([
                VendorEncryptBackfillCommand::class,
            ]);
        }

        // #1264: register the vendor PII columns in ahg_encrypted_fields so the
        // core daily ahg:encryption-bulk-apply sweep also covers them (belt and
        // braces on top of the encrypt-on-write path). Idempotent; gated on the
        // registry table existing.
        $this->app->booted(function () {
            try {
                $this->widenPiiColumns();
            } catch (\Throwable $e) {
                \Log::warning('[ahg-vendor] PII column widen failed: '.$e->getMessage());
            }
            try {
                $this->seedEncryptedFieldRegistry();
            } catch (\Throwable $e) {
                \Log::warning('[ahg-vendor] encrypted-field registry seed failed: '.$e->getMessage());
            }
        });
    }

    /**
     * #1264: ENC2: field-level ciphertext is ~205-233 chars, which overflows
     * the original narrow VARCHAR widths on the PII columns. Widen them to
     * VARCHAR(512) on existing installs (fresh installs already get the wide
     * type from install.sql). Idempotent: only ALTERs columns still narrower
     * than 512.
     */
    private function widenPiiColumns(): void
    {
        $targets = [
            'ahg_vendors' => ['email', 'phone', 'phone_alt', 'fax', 'bank_name', 'bank_branch', 'bank_account_number', 'bank_branch_code', 'bank_account_type'],
            'ahg_vendor_contacts' => ['phone', 'mobile', 'email'],
        ];

        foreach ($targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    continue;
                }
                $meta = DB::table('information_schema.columns')
                    ->where('table_schema', DB::raw('DATABASE()'))
                    ->where('table_name', $table)
                    ->where('column_name', $col)
                    ->first(['CHARACTER_MAXIMUM_LENGTH as len', 'DATA_TYPE as type']);
                // Already TEXT or already >= 512 -> nothing to do.
                if (! $meta) {
                    continue;
                }
                if ($meta->type === 'text' || $meta->type === 'mediumtext' || $meta->type === 'longtext') {
                    continue;
                }
                if ($meta->len !== null && (int) $meta->len >= 512) {
                    continue;
                }
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$col}` VARCHAR(512) DEFAULT NULL");
            }
        }
    }

    /**
     * Insert (idempotently) the vendor PII columns into ahg_encrypted_fields.
     */
    private function seedEncryptedFieldRegistry(): void
    {
        if (! Schema::hasTable('ahg_encrypted_fields')) {
            return;
        }

        $rows = [
            ['ahg_vendors', 'email', 'contact_details'],
            ['ahg_vendors', 'phone', 'contact_details'],
            ['ahg_vendors', 'phone_alt', 'contact_details'],
            ['ahg_vendors', 'fax', 'contact_details'],
            ['ahg_vendors', 'bank_name', 'financial_data'],
            ['ahg_vendors', 'bank_branch', 'financial_data'],
            ['ahg_vendors', 'bank_account_number', 'financial_data'],
            ['ahg_vendors', 'bank_branch_code', 'financial_data'],
            ['ahg_vendors', 'bank_account_type', 'financial_data'],
            ['ahg_vendor_contacts', 'phone', 'contact_details'],
            ['ahg_vendor_contacts', 'mobile', 'contact_details'],
            ['ahg_vendor_contacts', 'email', 'contact_details'],
        ];

        foreach ($rows as [$table, $column, $category]) {
            $exists = DB::table('ahg_encrypted_fields')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->exists();
            if (! $exists) {
                DB::table('ahg_encrypted_fields')->insert([
                    'table_name' => $table,
                    'column_name' => $column,
                    'category' => $category,
                    'is_encrypted' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
