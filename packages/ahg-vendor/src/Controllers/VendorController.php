<?php

namespace AhgVendor\Controllers;

use App\Http\Controllers\Controller;

class VendorController extends Controller
{
    public function add() { return view('icip::add'); }

    public function addTransaction() { return view('icip::add-transaction'); }

    public function edit() { return view('icip::edit'); }

    public function editTransaction() { return view('icip::edit-transaction'); }

    public function index() { return view('icip::index'); }

    public function list() { return view('icip::list'); }

    public function serviceTypes() { return view('icip::service-types'); }

    public function transactions() { return view('icip::transactions'); }

    public function view() { return view('icip::view'); }

    public function viewTransaction() { return view('icip::view-transaction'); }

    public function browse() { return view('icip::browse'); }

}
