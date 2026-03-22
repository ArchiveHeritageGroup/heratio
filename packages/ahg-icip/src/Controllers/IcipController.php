<?php

namespace AhgIcip\Controllers;

use App\Http\Controllers\Controller;

class IcipController extends Controller
{
    public function communities() { return view('marketplace::communities'); }

    public function communityEdit() { return view('marketplace::community-edit'); }

    public function communityView() { return view('marketplace::community-view'); }

    public function consentEdit() { return view('marketplace::consent-edit'); }

    public function consentList() { return view('marketplace::consent-list'); }

    public function consentView() { return view('marketplace::consent-view'); }

    public function consultationEdit() { return view('marketplace::consultation-edit'); }

    public function consultationView() { return view('marketplace::consultation-view'); }

    public function consultations() { return view('marketplace::consultations'); }

    public function dashboard() { return view('marketplace::dashboard'); }

    public function noticeTypes() { return view('marketplace::notice-types'); }

    public function notices() { return view('marketplace::notices'); }

    public function objectConsent() { return view('marketplace::object-consent'); }

    public function objectConsultations() { return view('marketplace::object-consultations'); }

    public function objectIcip() { return view('marketplace::object-icip'); }

    public function objectLabels() { return view('marketplace::object-labels'); }

    public function objectNotices() { return view('marketplace::object-notices'); }

    public function objectRestrictions() { return view('marketplace::object-restrictions'); }

    public function reportCommunity() { return view('marketplace::report-community'); }

    public function reportExpiry() { return view('marketplace::report-expiry'); }

    public function reportPending() { return view('marketplace::report-pending'); }

    public function reports() { return view('marketplace::reports'); }

    public function restrictions() { return view('marketplace::restrictions'); }

    public function tkLabels() { return view('marketplace::tk-labels'); }

}
