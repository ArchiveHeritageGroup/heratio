<?php

namespace AhgSpectrum\Controllers;

use App\Http\Controllers\Controller;

class SpectrumController extends Controller
{
    public function conditionAdmin() { return view('spectrum::condition-admin'); }

    public function conditionPhotos() { return view('spectrum::condition-photos'); }

    public function conditionRisk() { return view('spectrum::condition-risk'); }

    public function dashboard() { return view('spectrum::dashboard'); }

    public function dataQuality() { return view('spectrum::data-quality'); }

    public function export() { return view('spectrum::export'); }

    public function general() { return view('spectrum::general'); }

    public function generalWorkflow() { return view('spectrum::general-workflow'); }

    public function grapDashboard() { return view('spectrum::grap-dashboard'); }

    public function index() { return view('spectrum::index'); }

    public function label() { return view('spectrum::label'); }

    public function myTasks() { return view('spectrum::my-tasks'); }

    public function privacyAdmin() { return view('spectrum::privacy-admin'); }

    public function privacyBreaches() { return view('spectrum::privacy-breaches'); }

    public function privacyCompliance() { return view('spectrum::privacy-compliance'); }

    public function privacyDsar() { return view('spectrum::privacy-dsar'); }

    public function privacyRopa() { return view('spectrum::privacy-ropa'); }

    public function privacyTemplates() { return view('spectrum::privacy-templates'); }

    public function securityCompliance() { return view('spectrum::security-compliance'); }

    public function spectrumExport() { return view('spectrum::spectrum-export'); }

    public function workflow() { return view('spectrum::workflow'); }

    public function acquisitions() { return view('spectrum::acquisitions'); }

    public function conditions() { return view('spectrum::conditions'); }

    public function conservation() { return view('spectrum::conservation'); }

    public function loans() { return view('spectrum::loans'); }

    public function movements() { return view('spectrum::movements'); }

    public function objectEntry() { return view('spectrum::object-entry'); }

    public function valuations() { return view('spectrum::valuations'); }

}
