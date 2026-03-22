<?php

namespace AhgDiscovery\Controllers;

use App\Http\Controllers\Controller;

class DiscoveryController extends Controller
{
    public function index() { return view('graphql::index'); }

}
