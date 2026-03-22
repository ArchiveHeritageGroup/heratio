<?php

namespace AhgPrivacy\Controllers;

use App\Http\Controllers\Controller;

class PrivacyController extends Controller
{
    public function complaintConfirmation() { return view('::complaint-confirmation'); }

    public function complaint() { return view('::complaint'); }

    public function dashboard() { return view('::dashboard'); }

    public function dsarConfirmation() { return view('::dsar-confirmation'); }

    public function dsarRequest() { return view('::dsar-request'); }

    public function dsarStatus() { return view('::dsar-status'); }

    public function index() { return view('::index'); }

    public function breachAdd() { return view('::breach-add'); }

    public function breachEdit() { return view('::breach-edit'); }

    public function breachList() { return view('::breach-list'); }

    public function breachView() { return view('::breach-view'); }

    public function complaintAdd() { return view('::complaint-add'); }

    public function complaintEdit() { return view('::complaint-edit'); }

    public function complaintList() { return view('::complaint-list'); }

    public function complaintView() { return view('::complaint-view'); }

    public function config() { return view('::config'); }

    public function consentAdd() { return view('::consent-add'); }

    public function consentEdit() { return view('::consent-edit'); }

    public function consentList() { return view('::consent-list'); }

    public function consentView() { return view('::consent-view'); }

    public function dsarAdd() { return view('::dsar-add'); }

    public function dsarEdit() { return view('::dsar-edit'); }

    public function dsarList() { return view('::dsar-list'); }

    public function dsarView() { return view('::dsar-view'); }

    public function jurisdictionAdd() { return view('::jurisdiction-add'); }

    public function jurisdictionEdit() { return view('::jurisdiction-edit'); }

    public function jurisdictionInfo() { return view('::jurisdiction-info'); }

    public function jurisdictionList() { return view('::jurisdiction-list'); }

    public function jurisdictions() { return view('::jurisdictions'); }

    public function notifications() { return view('::notifications'); }

    public function officerAdd() { return view('::officer-add'); }

    public function officerEdit() { return view('::officer-edit'); }

    public function officerList() { return view('::officer-list'); }

    public function paiaAdd() { return view('::paia-add'); }

    public function paiaList() { return view('::paia-list'); }

    public function piiReview() { return view('::pii-review'); }

    public function piiScanObject() { return view('::pii-scan-object'); }

    public function piiScan() { return view('::pii-scan'); }

    public function report() { return view('::report'); }

    public function ropaAdd() { return view('::ropa-add'); }

    public function ropaEdit() { return view('::ropa-edit'); }

    public function ropaList() { return view('::ropa-list'); }

    public function ropaView() { return view('::ropa-view'); }

    public function visualRedactionEditor() { return view('::visual-redaction-editor'); }

}
