<?php

/**
 * AclIdentityResetTest - a queued job never runs as a leftover identity.
 *
 * heratio#1515: AclService caches the signed-in user in a static. That is safe
 * under PHP-FPM, where the static dies with the request, but a queue worker is
 * one long-lived process, so the identity cached while one job ran would still
 * be the answer for the next. The cache is now cleared before every job.
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

use AhgCore\Services\AclService;
use Tests\TestCase;

class AclIdentityResetTest extends TestCase
{
    /** What the job saw; static because a queued closure is serialised. */
    public static mixed $seen = 'not run';

    public function test_a_queued_job_starts_without_the_previous_identity(): void
    {
        // An identity cached by earlier work in the same process.
        AclService::setUser((object) ['id' => 999999, 'username' => 'leftover']);
        $this->assertSame(999999, AclService::getUser()->id ?? null);

        dispatch(function () {
            AclIdentityResetTest::$seen = AclService::getUser();
        })->onConnection('sync');

        $this->assertNull(self::$seen, 'the job ran as the previous identity');
    }
}
