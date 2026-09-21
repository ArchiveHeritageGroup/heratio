<?php

/**
 * WhatsAppBubbleTest - the landing page's WhatsApp chat bubble.
 *
 * The bubble must render only when switched on AND given a plausible
 * international number, and its link must carry the prefilled message
 * URL-encoded. Settings are written inside a transaction that is rolled back,
 * so nothing persists.
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

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppBubbleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function render(string $enabled, string $number, string $message = ''): string
    {
        foreach (['landing_whatsapp_enabled' => $enabled, 'landing_whatsapp_number' => $number, 'landing_whatsapp_message' => $message] as $key => $value) {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'setting_group' => 'features']
            );
        }
        AhgSettingsService::clearCache();

        return view('ahg-landing-page::_whatsapp-bubble')->render();
    }

    public function test_renders_a_wa_me_link_with_the_encoded_message(): void
    {
        $html = $this->render('1', '+27 82 123-4567', 'Hello, a question & more');

        $this->assertStringContainsString('class="ahg-whatsapp-bubble"', $html);
        // Digits only in the path; the message rawurlencoded, so & arrives as %26.
        $this->assertStringContainsString('href="https://wa.me/27821234567?text=Hello%2C%20a%20question%20%26%20more"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_no_query_string_without_a_message(): void
    {
        $this->assertStringContainsString('href="https://wa.me/27821234567"', $this->render('1', '27821234567'));
    }

    public function test_hidden_when_switched_off(): void
    {
        $this->assertStringNotContainsString('ahg-whatsapp-bubble', $this->render('0', '+27821234567'));
    }

    /** Visitors who are not logged in must see it on the public front page. */
    public function test_guest_sees_it_on_the_home_page(): void
    {
        $this->render('1', '+27821234567');

        $this->assertGuest();
        $this->get('/')->assertOk()->assertSee('https://wa.me/27821234567', false);
    }

    /** heratio.org serves the marketing home rather than the institutional one. */
    public function test_guest_sees_it_on_the_marketing_home_page(): void
    {
        $this->render('1', '+27821234567');
        config(['heratio.homepage_mode' => 'marketing']);

        $this->assertGuest();
        $this->get('/')->assertOk()->assertSee('https://wa.me/27821234567', false);
    }

    public function test_guest_sees_it_on_the_landing_page(): void
    {
        $this->render('1', '+27821234567');

        $this->assertGuest();
        $this->get('/landing/home')->assertOk()->assertSee('https://wa.me/27821234567', false);
    }

    public function test_hidden_without_a_plausible_number(): void
    {
        $this->assertStringNotContainsString('ahg-whatsapp-bubble', $this->render('1', ''));
        $this->assertStringNotContainsString('ahg-whatsapp-bubble', $this->render('1', '1234'));
        $this->assertStringNotContainsString('ahg-whatsapp-bubble', $this->render('1', '1234567890123456'));
    }
}
