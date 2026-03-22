<?php

namespace AhgApiPlugin\Controllers;

use App\Http\Controllers\Controller;

class ApiPluginController extends Controller
{
    public function searchInformationObjects() { return view('rad-manage::search-information-objects'); }

}
