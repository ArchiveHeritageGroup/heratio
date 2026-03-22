<?php

namespace AhgIcip\Controllers;

use App\Http\Controllers\Controller;

class IcipController extends Controller
{
    public function communities() { return view('icip::communities'); }

    public function communityEdit() { return view('icip::community-edit'); }

    public function communityView() { return view('icip::community-view'); }

    public function consentEdit() { return view('icip::consent-edit'); }

    public function consentList() { return view('icip::consent-list'); }

    public function consentView() { return view('icip::consent-view'); }

    public function consultationEdit() { return view('icip::consultation-edit'); }

    public function consultationView() { return view('icip::consultation-view'); }

    public function consultations() { return view('icip::consultations'); }

    public function dashboard() { return view('icip::dashboard'); }

    public function noticeTypes() { return view('icip::notice-types'); }

    public function notices() { return view('icip::notices'); }

    public function objectConsent() { return view('icip::object-consent'); }

    public function objectConsultations() { return view('icip::object-consultations'); }

    public function objectIcip() { return view('icip::object-icip'); }

    public function objectLabels() { return view('icip::object-labels'); }

    public function objectNotices() { return view('icip::object-notices'); }

    public function objectRestrictions() { return view('icip::object-restrictions'); }

    public function reportCommunity() { return view('icip::report-community'); }

    public function reportExpiry() { return view('icip::report-expiry'); }

    public function reportPending() { return view('icip::report-pending'); }

    public function reports() { return view('icip::reports'); }

    public function restrictions() { return view('icip::restrictions'); }

    public function tkLabels() { return view('icip::tk-labels'); }

}
