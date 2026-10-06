<?php

/**
 * CronFailureConfirmationTest - a frequent job's first failure waits one run.
 *
 * llm-health and services-check run every five minutes and fail whenever the
 * AI gateway blips. Each blip failed ahg:cron-run and wrote an error-log entry
 * (676 in a week). Now a job that runs at least every 15 minutes is only
 * reported once two runs in a row fail; infrequent jobs still fail at once,
 * because their next run can be a day away.
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

namespace Tests\Unit;

use AhgCore\Services\CronSchedulerService;
use Tests\TestCase;

class CronFailureConfirmationTest extends TestCase
{
    private CronSchedulerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new CronSchedulerService();
    }

    public function test_frequency_comes_from_the_cron_expression(): void
    {
        $this->assertTrue($this->svc->isFrequent('*/5 * * * *'));
        $this->assertTrue($this->svc->isFrequent('* * * * *'));
        $this->assertTrue($this->svc->isFrequent('*/15 * * * *'));
        $this->assertFalse($this->svc->isFrequent('*/30 * * * *'));
        $this->assertFalse($this->svc->isFrequent('0 2 * * *'));
        $this->assertFalse($this->svc->isFrequent('not a cron'), 'unparseable falls back to strict');
        $this->assertFalse($this->svc->isFrequent(null));
    }

    public function test_first_failure_of_a_frequent_job_waits_for_confirmation(): void
    {
        $this->assertFalse($this->svc->failureConfirmed('failed', 'success', '*/5 * * * *'));
        $this->assertFalse($this->svc->failureConfirmed('failed', null, '*/5 * * * *'), 'a first-ever run counts as a first failure');
    }

    public function test_second_failure_in_a_row_is_reported(): void
    {
        $this->assertTrue($this->svc->failureConfirmed('failed', 'failed', '*/5 * * * *'));
    }

    public function test_infrequent_job_fails_at_once(): void
    {
        $this->assertTrue($this->svc->failureConfirmed('failed', 'success', '0 1 * * *'));
    }

    public function test_success_is_never_held_back(): void
    {
        $this->assertTrue($this->svc->failureConfirmed('success', 'failed', '*/5 * * * *'));
    }
}
