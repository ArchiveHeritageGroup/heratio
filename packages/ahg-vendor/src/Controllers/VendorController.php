<?php

namespace AhgVendor\Controllers;

use App\Http\Controllers\Controller;

class VendorController extends Controller
{
    public function add() { return view('vendor::add'); }

    public function addTransaction() { return view('vendor::add-transaction'); }

    public function edit() { return view('vendor::edit'); }

    public function editTransaction() { return view('vendor::edit-transaction'); }

    public function index() { return view('vendor::index'); }

    public function list() { return view('vendor::list'); }

    public function serviceTypes() { return view('vendor::service-types'); }

    public function transactions() { return view('vendor::transactions'); }

    public function view() { return view('vendor::view'); }

    public function viewTransaction() { return view('vendor::view-transaction'); }

    public function browse() { return view('vendor::browse'); }

}
