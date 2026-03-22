<?php

namespace AhgCdpa\Controllers;

use App\Http\Controllers\Controller;

class CdpaController extends Controller
{
    public function breachCreate() { return view('cdpa::breach-create'); }

    public function breachView() { return view('cdpa::breach-view'); }

    public function breaches() { return view('cdpa::breaches'); }

    public function config() { return view('cdpa::config'); }

    public function consent() { return view('cdpa::consent'); }

    public function dpiaCreate() { return view('cdpa::dpia-create'); }

    public function dpia() { return view('cdpa::dpia'); }

    public function dpiaView() { return view('cdpa::dpia-view'); }

    public function dpoEdit() { return view('cdpa::dpo-edit'); }

    public function dpo() { return view('cdpa::dpo'); }

    public function index() { return view('cdpa::index'); }

    public function licenseEdit() { return view('cdpa::license-edit'); }

    public function license() { return view('cdpa::license'); }

    public function processingCreate() { return view('cdpa::processing-create'); }

    public function processingEdit() { return view('cdpa::processing-edit'); }

    public function processing() { return view('cdpa::processing'); }

    public function reports() { return view('cdpa::reports'); }

    public function requestCreate() { return view('cdpa::request-create'); }

    public function requestView() { return view('cdpa::request-view'); }

    public function requests() { return view('cdpa::requests'); }

}
