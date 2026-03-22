<?php

namespace AhgNaz\Controllers;

use App\Http\Controllers\Controller;

class NazController extends Controller
{
    public function closureCreate() { return view('cdpa::closure-create'); }

    public function closureEdit() { return view('cdpa::closure-edit'); }

    public function closures() { return view('cdpa::closures'); }

    public function config() { return view('cdpa::config'); }

    public function index() { return view('cdpa::index'); }

    public function permitCreate() { return view('cdpa::permit-create'); }

    public function permitView() { return view('cdpa::permit-view'); }

    public function permits() { return view('cdpa::permits'); }

    public function protectedRecords() { return view('cdpa::protected-records'); }

    public function reports() { return view('cdpa::reports'); }

    public function researcherCreate() { return view('cdpa::researcher-create'); }

    public function researcherView() { return view('cdpa::researcher-view'); }

    public function researchers() { return view('cdpa::researchers'); }

    public function scheduleCreate() { return view('cdpa::schedule-create'); }

    public function scheduleView() { return view('cdpa::schedule-view'); }

    public function schedules() { return view('cdpa::schedules'); }

    public function transferCreate() { return view('cdpa::transfer-create'); }

    public function transferView() { return view('cdpa::transfer-view'); }

    public function transfers() { return view('cdpa::transfers'); }

}
