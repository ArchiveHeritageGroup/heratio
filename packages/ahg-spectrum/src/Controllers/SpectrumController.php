<?php

namespace AhgSpectrum\Controllers;

use App\Http\Controllers\Controller;

class SpectrumController extends Controller
{
    public function conditionAdmin() { return view('privacy::condition-admin'); }

    public function conditionPhotos() { return view('privacy::condition-photos'); }

    public function conditionRisk() { return view('privacy::condition-risk'); }

    public function dashboard() { return view('privacy::dashboard'); }

    public function dataQuality() { return view('privacy::data-quality'); }

    public function export() { return view('privacy::export'); }

    public function general() { return view('privacy::general'); }

    public function generalWorkflow() { return view('privacy::general-workflow'); }

    public function grapDashboard() { return view('privacy::grap-dashboard'); }

    public function index() { return view('privacy::index'); }

    public function label() { return view('privacy::label'); }

    public function myTasks() { return view('privacy::my-tasks'); }

    public function privacyAdmin() { return view('privacy::privacy-admin'); }

    public function privacyBreaches() { return view('privacy::privacy-breaches'); }

    public function privacyCompliance() { return view('privacy::privacy-compliance'); }

    public function privacyDsar() { return view('privacy::privacy-dsar'); }

    public function privacyRopa() { return view('privacy::privacy-ropa'); }

    public function privacyTemplates() { return view('privacy::privacy-templates'); }

    public function securityCompliance() { return view('privacy::security-compliance'); }

    public function spectrumExport() { return view('privacy::spectrum-export'); }

    public function workflow() { return view('privacy::workflow'); }

    public function acquisitions() { return view('privacy::acquisitions'); }

    public function conditions() { return view('privacy::conditions'); }

    public function conservation() { return view('privacy::conservation'); }

    public function loans() { return view('privacy::loans'); }

    public function movements() { return view('privacy::movements'); }

    public function objectEntry() { return view('privacy::object-entry'); }

    public function valuations() { return view('privacy::valuations'); }

}
