<?php

namespace AhgNaz\Controllers;

use App\Http\Controllers\Controller;

class NazController extends Controller
{
    public function closureCreate() { return view('naz::closure-create'); }

    public function closureEdit() { return view('naz::closure-edit'); }

    public function closures() { return view('naz::closures'); }

    public function config() { return view('naz::config'); }

    public function index() { return view('naz::index'); }

    public function permitCreate() { return view('naz::permit-create'); }

    public function permitView() { return view('naz::permit-view'); }

    public function permits() { return view('naz::permits'); }

    public function protectedRecords() { return view('naz::protected-records'); }

    public function reports() { return view('naz::reports'); }

    public function researcherCreate() { return view('naz::researcher-create'); }

    public function researcherView() { return view('naz::researcher-view'); }

    public function researchers() { return view('naz::researchers'); }

    public function scheduleCreate() { return view('naz::schedule-create'); }

    public function scheduleView() { return view('naz::schedule-view'); }

    public function schedules() { return view('naz::schedules'); }

    public function transferCreate() { return view('naz::transfer-create'); }

    public function transferView() { return view('naz::transfer-view'); }

    public function transfers() { return view('naz::transfers'); }

}
