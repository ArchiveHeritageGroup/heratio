<?php

/**
 * DemoTourTest - H06: the guided demo tour exists only on the demo hosts, its
 * page lists every step and states the reset and the sample-data rules, and the
 * floating card is never sent to client installs.
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

use App\Support\DemoTour;
use Tests\TestCase;

class DemoTourTest extends TestCase
{
    public function test_a_client_install_has_no_tour_and_no_card(): void
    {
        config(['heratio.homepage_mode' => 'institutional']);

        $this->get('/solutions/tour')->assertNotFound();
        $this->get('/solutions')->assertOk()->assertDontSee('demo-tour-card', false);
    }

    public function test_the_demo_host_tour_page_lists_every_step_and_the_rules(): void
    {
        config(['heratio.homepage_mode' => 'marketing']);

        $res = $this->get('/solutions/tour')->assertOk()
            ->assertSee('data-demo-tour-start', false)
            ->assertSee('demo-tour-card', false)
            ->assertSee('02:20')
            ->assertSee('Please do not enter real personal information.');
        foreach (DemoTour::steps() as $step) {
            $res->assertSee($step['title']);
        }
    }

    public function test_every_step_is_a_site_path_with_text(): void
    {
        $steps = DemoTour::steps();
        $this->assertGreaterThanOrEqual(5, count($steps));
        foreach ($steps as $step) {
            $this->assertStringStartsWith('/', $step['path']);
            $this->assertNotSame('', $step['title']);
            $this->assertNotSame('', $step['text']);
        }
        $this->assertSame('/login', $steps[array_search('/login', array_column($steps, 'path'), true)]['path'], 'the tour includes signing in with the demo login');
    }
}
