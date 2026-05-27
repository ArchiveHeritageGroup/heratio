<?php

/**
 * PublishRequestSmokeTest - sanity checks for the new token-anchored
 * publish-request surface (Heratio #745).
 *
 * Does not write to the DB. Confirms the package boots, the new routes are
 * registered (including the anonymous receipt + admin inbox endpoints), and
 * generateToken() emits a 40-char hex string.
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author:    Johan Pieterse <johan@plainsailingisystems.co.za>
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace Tests\Feature;

use AhgRequestPublish\Controllers\PublishRequestController;
use AhgRequestPublish\Notifications\PublishRequestDecisionNotification;
use AhgRequestPublish\Notifications\PublishRequestSubmittedNotification;
use AhgRequestPublish\Providers\AhgRequestPublishServiceProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublishRequestSmokeTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(AhgRequestPublishServiceProvider::class, $providers);
    }

    public function test_generate_token_returns_40_hex_chars(): void
    {
        $a = PublishRequestController::generateToken();
        $b = PublishRequestController::generateToken();
        $this->assertSame(40, strlen($a));
        $this->assertSame(40, strlen($b));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $a);
        $this->assertNotSame($a, $b, 'two consecutive tokens must differ');
    }

    public function test_public_submit_route_is_registered(): void
    {
        $r = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($x) => $x->getName() === 'publish-request.submit');
        $this->assertNotNull($r);
        $this->assertSame(['POST'], array_values(array_filter($r->methods(), fn ($m) => $m !== 'HEAD')));
        $this->assertSame('publish-request', $r->uri());
    }

    public function test_anonymous_receipt_route_has_token_regex(): void
    {
        $r = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($x) => $x->getName() === 'publish-request.receipt');
        $this->assertNotNull($r);
        $this->assertSame('publish-request/receipt/{token}', $r->uri());
        $wheres = $r->wheres;
        $this->assertArrayHasKey('token', $wheres);
        $this->assertSame('[a-f0-9]{40}', $wheres['token']);
    }

    public function test_curator_inbox_route_is_admin_only(): void
    {
        $r = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($x) => $x->getName() === 'publish-requests.inbox');
        $this->assertNotNull($r);
        $this->assertContains('admin', $r->gatherMiddleware());
    }

    public function test_decision_route_exists_with_id_constraint(): void
    {
        $r = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($x) => $x->getName() === 'publish-requests.decision');
        $this->assertNotNull($r);
        $this->assertContains('admin', $r->gatherMiddleware());
        $this->assertSame('[0-9]+', $r->wheres['id'] ?? null);
    }

    public function test_submitted_notification_builds_mail_payload(): void
    {
        $n = new PublishRequestSubmittedNotification(
            token: str_repeat('a', 40),
            receiptUrl: 'https://example.test/publish-request/receipt/'.str_repeat('a', 40),
            submitterName: 'Test Submitter',
        );
        $mail = $n->toMail((object) []);
        $rendered = $mail->render();
        $this->assertStringContainsString('Test Submitter', $rendered);
        $this->assertStringContainsString('receipt', strtolower($rendered));
    }

    public function test_decision_notification_subject_changes_with_status(): void
    {
        $approved = (new PublishRequestDecisionNotification(
            token: str_repeat('b', 40),
            status: 'approved',
            receiptUrl: 'https://example.test/publish-request/receipt/'.str_repeat('b', 40),
            curatorNotes: 'OK to publish',
        ))->toMail((object) []);

        $rejected = (new PublishRequestDecisionNotification(
            token: str_repeat('c', 40),
            status: 'rejected',
            receiptUrl: 'https://example.test/publish-request/receipt/'.str_repeat('c', 40),
        ))->toMail((object) []);

        $this->assertSame('Publish request approved', $approved->subject);
        $this->assertSame('Publish request rejected', $rejected->subject);
        $this->assertStringContainsString('OK to publish', $approved->render());
    }

    public function test_receipt_rejects_malformed_token(): void
    {
        // Token shape guard: receipt() aborts 404 before hitting the DB when
        // the token isn't 40 hex chars. Anything outside the regex never
        // even resolves to this route (the where('token', ...) constraint),
        // so we hit a routing-layer 404.
        $this->get('/publish-request/receipt/not-a-token')
            ->assertStatus(404);
    }
}
