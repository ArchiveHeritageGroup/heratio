<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchBookingsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    // auth-group GET routes: anonymous request redirects to /login,
    // proving the route resolves to the extracted controller and stays
    // inside the auth middleware group (issue #1269 booking extraction).
    // ------------------------------------------------------------------

    public function test_book_form_requires_auth()
    {
        $response = $this->get('/research/book');
        $response->assertRedirect('/login');
    }

    public function test_view_booking_requires_auth()
    {
        $response = $this->get('/research/viewBooking/1');
        $response->assertRedirect('/login');
    }

    // ------------------------------------------------------------------
    // admin-group route: the 'admin' middleware (RequireAdmin) aborts 403
    // for an anonymous request rather than redirecting, so the bookings
    // queue must answer 403 - proving it stays inside the admin group.
    // ------------------------------------------------------------------

    public function test_bookings_queue_requires_admin()
    {
        $response = $this->get('/research/bookings');
        $response->assertForbidden();
    }

    public function test_admin_bookings_alias_requires_admin()
    {
        $response = $this->get('/research/admin/bookings');
        $response->assertForbidden();
    }

    // ------------------------------------------------------------------
    // POST-only lifecycle endpoints (auth group). An anonymous POST is
    // short-circuited by the web group's CSRF middleware (302) before the
    // controller body runs, so we only assert the route resolves to a POST
    // verb (not 404/405) - proving each is wired to the extracted controller.
    // ------------------------------------------------------------------

    public function test_book_store_route_resolves()
    {
        $response = $this->post('/research/book');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_confirm_booking_route_resolves()
    {
        $response = $this->post('/research/bookings/1/confirm');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_check_in_booking_route_resolves()
    {
        $response = $this->post('/research/bookings/1/check-in');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_check_out_booking_route_resolves()
    {
        $response = $this->post('/research/bookings/1/check-out');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_no_show_booking_route_resolves()
    {
        $response = $this->post('/research/bookings/1/no-show');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_cancel_booking_route_resolves()
    {
        $response = $this->post('/research/bookings/1/cancel');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_legacy_check_in_route_resolves()
    {
        $response = $this->post('/research/checkIn/1');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_legacy_check_out_route_resolves()
    {
        $response = $this->post('/research/checkOut/1');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }
}
