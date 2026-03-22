@php /**
 * Extended Rights Search Filter
 * Add to advanced search form
 */

$culture = sfContext::getInstance()->user->getCulture();

// Get rights statements for dropdown
$rightsStatements = \Illuminate\Database\Capsule\Manager::table('rights_statement as rs')
    ->leftJoin('rights_statement_i18n as rsi', function ($join) use ($culture) {
        $join->on('rsi.rights_statement_id', '=', 'rs.id')
            ->where('rsi.culture', '=', $culture);
    })
    ->where('rs.is_active', true)
    ->orderBy('rs.sort_order')
    ->select('rs.id', 'rs.code', 'rsi.name')
    ->get();

// Get CC licenses for dropdown
$ccLicenses = \Illuminate\Database\Capsule\Manager::table('rights_cc_license as cc')
    ->leftJoin('rights_cc_license_i18n as cci', function ($join) use ($culture) {
        $join->on('cci.id', '=', 'cc.id')
            ->where('cci.culture', '=', $culture);
    })
    ->where('cc.is_active', true)
    ->orderBy('cc.sort_order')
    ->select('cc.id', 'cc.code', 'cci.name')
    ->get();

$selectedRs = $sf_request->getParameter('rights_statement_id', '');
$selectedCc = $sf_request->getParameter('cc_license_id', '');
$embargoFilter = $sf_request->getParameter('embargo_status', ''); @endphp

<fieldset class="mb-3">
  <legend class="h6">{{ __('Rights') }}</legend>
  
  <div class="row">
    <div class="col-md-4 mb-2">
      <label for="rights_statement_filter" class="form-label small">{{ __('Rights Statement') }} <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="rights_statement_id" id="rights_statement_filter" class="form-select form-select-sm">
        <option value="">{{ __('Any') }}</option>
        <option value="none" @php echo $selectedRs === 'none' ? 'selected' : ''; @endphp>{{ __('No rights statement') }}</option>
        @php foreach ($rightsStatements as $rs): @endphp
        <option value="@php echo $rs->id; @endphp" @php echo $selectedRs == $rs->id ? 'selected' : ''; @endphp>
          [@php echo $rs->code; @endphp] @php echo $rs->name; @endphp
        </option>
        @php endforeach; @endphp
      </select>
    </div>
    
    <div class="col-md-4 mb-2">
      <label for="cc_license_filter" class="form-label small">{{ __('CC License') }} <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="cc_license_id" id="cc_license_filter" class="form-select form-select-sm">
        <option value="">{{ __('Any') }}</option>
        <option value="none" @php echo $selectedCc === 'none' ? 'selected' : ''; @endphp>{{ __('No CC license') }}</option>
        @php foreach ($ccLicenses as $cc): @endphp
        <option value="@php echo $cc->id; @endphp" @php echo $selectedCc == $cc->id ? 'selected' : ''; @endphp>
          [@php echo $cc->code; @endphp] @php echo $cc->name; @endphp
        </option>
        @php endforeach; @endphp
      </select>
    </div>
    
    <div class="col-md-4 mb-2">
      <label for="embargo_status_filter" class="form-label small">{{ __('Embargo Status') }} <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="embargo_status" id="embargo_status_filter" class="form-select form-select-sm">
        <option value="">{{ __('Any') }}</option>
        <option value="active" @php echo $embargoFilter === 'active' ? 'selected' : ''; @endphp>{{ __('Under embargo') }}</option>
        <option value="none" @php echo $embargoFilter === 'none' ? 'selected' : ''; @endphp>{{ __('Not embargoed') }}</option>
        <option value="expiring" @php echo $embargoFilter === 'expiring' ? 'selected' : ''; @endphp>{{ __('Expiring within 30 days') }}</option>
      </select>
    </div>
  </div>
</fieldset>
