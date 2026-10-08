<?php

/**
 * SessionTimeoutWarningTest - warn before an idle session ends (heratio#1543).
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

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SessionTimeoutWarningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_keepalive_renews_for_a_signed_in_user_only(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $this->markTestSkipped('no user');
        }
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $this->post(route('session.keepalive'))->assertRedirect(route('login'));
        $this->actingAs($user)->post(route('session.keepalive'))->assertNoContent();
    }

    public function test_the_warning_fires_five_minutes_before_the_lifetime(): void
    {
        config(['session.lifetime' => 30]);
        $html = view('theme::partials.session-timeout-warning')->render();

        $this->assertStringContainsString('id="sessionTimeoutWarning"', $html);
        $this->assertStringContainsString('var warnMs = '.(25 * 60000), $html);
    }

    public function test_a_short_lifetime_warns_at_half_time(): void
    {
        config(['session.lifetime' => 4]);

        $this->assertStringContainsString('var warnMs = '.(2 * 60000), view('theme::partials.session-timeout-warning')->render());
    }
}
