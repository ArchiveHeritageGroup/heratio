<?php

/**
 * FusekiSyncDefaultTest - ahg:ric:fuseki-sync-default writes the RiC sync
 * switch only when it is missing (on if Fuseki answers, off if not), never
 * changes an existing row, and --setup turns sync on only once the endpoint
 * answers (heratio#1598). The Fuseki ping is faked, so no triple store is
 * needed.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Feature;

use AhgRic\Support\FusekiEndpoint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FusekiSyncDefaultTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_settings')) {
            $this->markTestSkipped('ahg_settings not present.');
        }
        DB::table('ahg_settings')->where('setting_key', 'fuseki_sync_enabled')->delete();
    }

    private function fuseki(?string $url, bool $answers): void
    {
        $this->app->instance(FusekiEndpoint::class, new class($url, $answers) extends FusekiEndpoint
        {
            public function __construct(private ?string $url, private bool $answers) {}

            public function resolve(): array
            {
                return ['url' => $this->url, 'dataset' => 'ric', 'user' => null, 'pass' => null];
            }

            public function reachable(string $baseUrl): bool
            {
                return $this->answers;
            }
        });
    }

    private function value(): ?string
    {
        return DB::table('ahg_settings')->where('setting_key', 'fuseki_sync_enabled')->value('setting_value');
    }

    public function test_install_without_fuseki_seeds_sync_off(): void
    {
        $this->fuseki('http://localhost:3030', false);

        $this->artisan('ahg:ric:fuseki-sync-default')->assertExitCode(0);

        $this->assertSame('0', $this->value());
    }

    public function test_install_with_fuseki_answering_seeds_sync_on(): void
    {
        $this->fuseki('http://localhost:3030', true);

        $this->artisan('ahg:ric:fuseki-sync-default')->assertExitCode(0);

        $this->assertSame('1', $this->value());
    }

    public function test_no_endpoint_configured_seeds_sync_off(): void
    {
        $this->fuseki(null, true);

        $this->artisan('ahg:ric:fuseki-sync-default')->assertExitCode(0);

        $this->assertSame('0', $this->value());
    }

    public function test_an_existing_setting_is_never_changed_by_the_install_default(): void
    {
        DB::table('ahg_settings')->insert(['setting_key' => 'fuseki_sync_enabled', 'setting_value' => '1', 'setting_group' => 'fuseki']);
        $this->fuseki('http://localhost:3030', false);

        $this->artisan('ahg:ric:fuseki-sync-default')->assertExitCode(0);

        $this->assertSame('1', $this->value());
    }

    public function test_setup_turns_sync_on_once_fuseki_answers(): void
    {
        DB::table('ahg_settings')->insert(['setting_key' => 'fuseki_sync_enabled', 'setting_value' => '0', 'setting_group' => 'fuseki']);
        $this->fuseki('http://localhost:3030', true);

        $this->artisan('ahg:ric:fuseki-sync-default', ['--setup' => true])->assertExitCode(0);

        $this->assertSame('1', $this->value());
    }

    public function test_setup_fails_and_changes_nothing_when_fuseki_does_not_answer(): void
    {
        DB::table('ahg_settings')->insert(['setting_key' => 'fuseki_sync_enabled', 'setting_value' => '0', 'setting_group' => 'fuseki']);
        $this->fuseki('http://localhost:3030', false);

        $this->artisan('ahg:ric:fuseki-sync-default', ['--setup' => true])->assertExitCode(1);

        $this->assertSame('0', $this->value());
    }
}
