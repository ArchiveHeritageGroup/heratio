<?php

/**
 * PrivacyController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgPrivacy\Controllers;

use App\Http\Controllers\Controller;

class PrivacyController extends Controller
{
    public function complaintConfirmation() { return view('privacy::complaint-confirmation'); }

    public function complaint() { return view('privacy::complaint'); }

    public function dashboard() { return view('privacy::dashboard'); }

    public function dsarConfirmation() { return view('privacy::dsar-confirmation'); }

    public function dsarRequest() { return view('privacy::dsar-request'); }

    public function dsarStatus() { return view('privacy::dsar-status'); }

    public function index() { return view('privacy::index'); }

    public function breachAdd() { return view('privacy::breach-add'); }

    public function breachEdit() { return view('privacy::breach-edit'); }

    public function breachList() { return view('privacy::breach-list'); }

    public function breachView() { return view('privacy::breach-view'); }

    public function complaintAdd() { return view('privacy::complaint-add'); }

    public function complaintEdit() { return view('privacy::complaint-edit'); }

    public function complaintList() { return view('privacy::complaint-list'); }

    public function complaintView() { return view('privacy::complaint-view'); }

    public function config() { return view('privacy::config'); }

    public function consentAdd() { return view('privacy::consent-add'); }

    public function consentEdit() { return view('privacy::consent-edit'); }

    public function consentList() { return view('privacy::consent-list'); }

    public function consentView() { return view('privacy::consent-view'); }

    public function dsarAdd() { return view('privacy::dsar-add'); }

    public function dsarEdit() { return view('privacy::dsar-edit'); }

    public function dsarList() { return view('privacy::dsar-list'); }

    public function dsarView() { return view('privacy::dsar-view'); }

    public function jurisdictionAdd() { return view('privacy::jurisdiction-add'); }

    public function jurisdictionEdit() { return view('privacy::jurisdiction-edit'); }

    public function jurisdictionInfo() { return view('privacy::jurisdiction-info'); }

    public function jurisdictionList() { return view('privacy::jurisdiction-list'); }

    public function jurisdictions() { return view('privacy::jurisdictions'); }

    public function notifications() { return view('privacy::notifications'); }

    public function officerAdd() { return view('privacy::officer-add'); }

    public function officerEdit() { return view('privacy::officer-edit'); }

    public function officerList() { return view('privacy::officer-list'); }

    public function paiaAdd() { return view('privacy::paia-add'); }

    public function paiaList() { return view('privacy::paia-list'); }

    public function piiReview() { return view('privacy::pii-review'); }

    public function piiScanObject() { return view('privacy::pii-scan-object'); }

    public function piiScan() { return view('privacy::pii-scan'); }

    public function report() { return view('privacy::report'); }

    public function ropaAdd() { return view('privacy::ropa-add'); }

    public function ropaEdit() { return view('privacy::ropa-edit'); }

    public function ropaList() { return view('privacy::ropa-list'); }

    public function ropaView() { return view('privacy::ropa-view'); }

    public function visualRedactionEditor() { return view('privacy::visual-redaction-editor'); }

}
