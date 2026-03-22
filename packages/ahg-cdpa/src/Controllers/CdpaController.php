<?php

namespace AhgCdpa\Controllers;

use App\Http\Controllers\Controller;

class CdpaController extends Controller
{
    public function breachCreate() { return view('vendor::breach-create'); }

    public function breachView() { return view('vendor::breach-view'); }

    public function breaches() { return view('vendor::breaches'); }

    public function config() { return view('vendor::config'); }

    public function consent() { return view('vendor::consent'); }

    public function dpiaCreate() { return view('vendor::dpia-create'); }

    public function dpia() { return view('vendor::dpia'); }

    public function dpiaView() { return view('vendor::dpia-view'); }

    public function dpoEdit() { return view('vendor::dpo-edit'); }

    public function dpo() { return view('vendor::dpo'); }

    public function index() { return view('vendor::index'); }

    public function licenseEdit() { return view('vendor::license-edit'); }

    public function license() { return view('vendor::license'); }

    public function processingCreate() { return view('vendor::processing-create'); }

    public function processingEdit() { return view('vendor::processing-edit'); }

    public function processing() { return view('vendor::processing'); }

    public function reports() { return view('vendor::reports'); }

    public function requestCreate() { return view('vendor::request-create'); }

    public function requestView() { return view('vendor::request-view'); }

    public function requests() { return view('vendor::requests'); }

}
