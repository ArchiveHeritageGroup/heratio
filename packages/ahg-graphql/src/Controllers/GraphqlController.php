<?php

namespace AhgGraphql\Controllers;

use App\Http\Controllers\Controller;

class GraphqlController extends Controller
{
    public function playground() { return view('graphql::playground'); }

}
