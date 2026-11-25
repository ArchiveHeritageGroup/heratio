<?php

/**
 * AuditTriggerInstallTest - the boot-time audit trigger install must create only
 * the missing trigger and never drop one that exists.
 *
 * On ANC (25 Nov 2026) the app's DB user could not create triggers (error 1419:
 * binlog on, not SUPER). The old install dropped and recreated both triggers
 * whenever one was missing, so a working trigger was lost and every request
 * queued on the ahg_audit_log metadata lock until the site stopped answering.
 * The statements are checked here rather than run, so the test needs no
 * trigger rights.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgAuditTrail\Providers\AhgAuditTrailServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTriggerInstallTest extends TestCase
{
    private function install(array $only): void
    {
        $p = new AhgAuditTrailServiceProvider($this->app);
        (new \ReflectionMethod($p, 'runSqlFileUnprepared'))
            ->invoke($p, base_path('packages/ahg-audit-trail/database/install-trigger.sql'), $only);
    }

    public function test_only_the_missing_trigger_is_created_and_nothing_is_dropped(): void
    {
        $sent = [];
        DB::shouldReceive('unprepared')->andReturnUsing(function ($sql) use (&$sent) {
            $sent[] = $sql;

            return true;
        });

        $this->install(['ahg_audit_log_no_update_chained']);

        $this->assertCount(1, $sent);
        $this->assertStringStartsWith('CREATE TRIGGER `ahg_audit_log_no_update_chained`', $sent[0]);
        $this->assertStringContainsString('END IF;', $sent[0], 'trigger body kept whole');
    }

    public function test_nothing_runs_when_nothing_is_missing(): void
    {
        DB::shouldReceive('unprepared')->never();

        $this->install([]);

        $this->assertTrue(true);
    }
}
