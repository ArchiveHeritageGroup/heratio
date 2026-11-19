<?php

use AhgCore\Services\CronSchedulerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1734: ahg_trash - deleted digital objects kept for restore.
 * heratio#1759: ahg_file_version - numbered versions of a replaced master.
 * heratio#1760: record_declaration.archived_at - the automated archive tier.
 * heratio#1735: the nightly fixity schedule verifies and alerts (it ran with
 *   --report, which only prints statistics, so nothing was ever checked).
 * Plus the new default schedules and their settings (Settings > Integrity).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_trash')) {
            Schema::create('ahg_trash', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('digital_object_id')->index();
                $t->unsignedInteger('information_object_id')->nullable()->index();
                $t->string('name', 500)->nullable();
                $t->longText('rows_json');
                $t->longText('files_json');
                $t->string('status', 20)->default('trashed')->index();
                $t->unsignedInteger('deleted_by')->nullable();
                $t->dateTime('deleted_at');
                $t->dateTime('purge_after')->index();
                $t->dateTime('restored_at')->nullable();
                $t->unsignedInteger('restored_by')->nullable();
                $t->dateTime('purged_at')->nullable();
            });
        }

        if (! Schema::hasTable('ahg_file_version')) {
            Schema::create('ahg_file_version', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('digital_object_id')->index();
                $t->unsignedInteger('version_major');
                $t->unsignedInteger('version_minor');
                $t->string('name', 500);
                $t->string('mime_type', 255)->nullable();
                $t->unsignedBigInteger('byte_size')->nullable();
                $t->string('sha256', 64)->nullable();
                $t->string('stored_path', 1000)->nullable();
                $t->boolean('is_current')->default(false);
                $t->string('note', 1000)->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->dateTime('created_at')->nullable();
            });
        }

        if (Schema::hasTable('record_declaration') && ! Schema::hasColumn('record_declaration', 'archived_at')) {
            Schema::table('record_declaration', fn (Blueprint $t) => $t->dateTime('archived_at')->nullable());
        }

        if (Schema::hasTable('cron_schedule')) {
            // The old default only printed a report; an operator's own command is left alone.
            DB::table('cron_schedule')->where('slug', 'preservation-fixity')
                ->where('artisan_command', 'ahg:preservation-fixity --age=30 --report')
                ->update(['artisan_command' => 'ahg:preservation-fixity --age=30 --limit=500 --notify', 'updated_at' => now()]);
            $svc = new CronSchedulerService;
            foreach (['trash-purge', 'records-archive'] as $slug) {
                $row = collect($svc->getDefaultSchedules())->firstWhere('slug', $slug);
                if ($row && ! DB::table('cron_schedule')->where('slug', $slug)->exists()) {
                    DB::table('cron_schedule')->insert(array_merge($row, [
                        'next_run_at' => $svc->computeNextRun($row['cron_expression']),
                        'created_at' => now(), 'updated_at' => now(),
                    ]));
                }
            }
        }

        if (Schema::hasTable('ahg_settings')) {
            foreach ([
                'trash_retention_days' => ['30', 'Days a deleted digital object stays in the trash (Admin > Deleted files) before the purge removes its files. 0 deletes at once.'],
                'preservation_alert_email' => ['', 'Addresses (comma-separated) told when the nightly fixity check finds files missing or changed. Administrators are always notified in Heratio.'],
                'records_archive_after_days' => ['0', 'Days a declared record must be inactive before it moves to the archive tier on its own. 0 is off.'],
            ] as $key => [$value, $desc]) {
                if (! DB::table('ahg_settings')->where('setting_key', $key)->exists()) {
                    $row = ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'integrity'];
                    if (Schema::hasColumn('ahg_settings', 'description')) {
                        $row['description'] = $desc;
                    }
                    DB::table('ahg_settings')->insert($row);
                }
            }
        }
    }

    public function down(): void
    {
        // Additive only; nothing to undo safely.
    }
};
