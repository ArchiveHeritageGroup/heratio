<?php

namespace AhgMultiTenant\Controllers;

use App\Http\Controllers\Controller;

class MultiTenantController extends Controller
{
    public function create() { return view('statistics::create'); }

    public function editTenant() { return view('statistics::edit-tenant'); }

    public function index() { return view('statistics::index'); }

    public function superUsers() { return view('statistics::super-users'); }

    public function unknownDomain() { return view('statistics::unknown-domain'); }

    public function unknownTenant() { return view('statistics::unknown-tenant'); }

}
