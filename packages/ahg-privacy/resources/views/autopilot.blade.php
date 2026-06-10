{{-- heratio#1199 Compliance autopilot: scan catalogue for PII -> auto-draft a ROPA entry. --}}
@extends('theme::layouts.1col')
@section('title', __('Compliance Autopilot'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-robot me-2 text-primary"></i>{{ __('Compliance Autopilot') }}</h1>
    <span class="text-muted small">{{ __('Scan for personal data, auto-draft a Record of Processing (Article 30)') }}</span>
    <a href="{{ route('ahgprivacy.article-30.index') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-list me-1"></i>{{ __('ROPA register') }}</a>
  </div>
  <p class="text-muted small">{{ __('Heratio scans your catalogue descriptions for personal data (emails, phone numbers, identifiers, dates of birth, etc.), summarises what categories it finds, and pre-fills a Records of Processing Activities entry for you to review and save. Always confirm against the regime that applies to your institution.') }}</p>

  <button type="button" id="apScan" class="btn btn-primary mb-3"><i class="fas fa-magnifying-glass-chart me-1"></i>{{ __('Scan the catalogue') }}</button>
  <span id="apStatus" class="ms-2 small text-muted"></span>

  <div id="apResult" style="display:none">
    <div class="card mb-3">
      <div class="card-header py-2"><strong>{{ __('Personal data found') }}</strong> <small class="text-muted" id="apSummary"></small></div>
      <div class="card-body p-2"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Records') }}</th><th class="text-end">{{ __('Occurrences') }}</th><th>{{ __('Examples') }}</th></tr></thead>
        <tbody id="apCats"></tbody>
      </table></div></div>
    </div>

    <form method="POST" action="{{ route('ahgprivacy.autopilot.create') }}">
      @csrf
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('Draft ROPA entry') }}</strong> <small class="text-muted">{{ __('review, edit, then create') }}</small></div>
        <div class="card-body">
          <div class="mb-2"><label class="form-label small mb-1">{{ __('Name') }}</label><input type="text" name="name" id="apName" class="form-control form-control-sm" required></div>
          <div class="mb-2"><label class="form-label small mb-1">{{ __('Purpose') }}</label><textarea name="purpose" id="apPurpose" class="form-control form-control-sm" rows="2"></textarea></div>
          <div class="mb-2"><label class="form-label small mb-1">{{ __('Lawful basis') }}</label><input type="text" name="lawful_basis" id="apBasis" class="form-control form-control-sm"></div>
          <div class="row g-2">
            <div class="col-md-6 mb-2"><label class="form-label small mb-1">{{ __('Categories of personal data') }} <span class="text-muted">({{ __('one per line') }})</span></label><textarea name="categories_of_data" id="apData" class="form-control form-control-sm" rows="4"></textarea></div>
            <div class="col-md-6 mb-2"><label class="form-label small mb-1">{{ __('Categories of data subjects') }}</label><textarea name="categories_of_subjects" id="apSubjects" class="form-control form-control-sm" rows="4"></textarea></div>
            <div class="col-md-6 mb-2"><label class="form-label small mb-1">{{ __('Recipients') }}</label><textarea name="recipients" id="apRecipients" class="form-control form-control-sm" rows="2"></textarea></div>
            <div class="col-md-6 mb-2"><label class="form-label small mb-1">{{ __('Retention period') }}</label><input type="text" name="retention_period" id="apRetention" class="form-control form-control-sm"></div>
          </div>
          <div class="mb-2"><label class="form-label small mb-1">{{ __('Security measures') }}</label><textarea name="security_measures" id="apSecurity" class="form-control form-control-sm" rows="2"></textarea></div>
          <button type="submit" class="btn btn-success"><i class="fas fa-plus me-1"></i>{{ __('Create ROPA entry') }}</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var CSRF = '{{ csrf_token() }}';
  var btn = document.getElementById('apScan'), status = document.getElementById('apStatus'),
      result = document.getElementById('apResult');
  function setVal(id, v) { var el = document.getElementById(id); if (el) el.value = v; }
  btn.addEventListener('click', function () {
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Scanning…') }}';
    status.textContent = '';
    fetch('{{ route('ahgprivacy.autopilot.scan') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-magnifying-glass-chart me-1"></i>{{ __('Scan the catalogue') }}';
        if (!d || !d.ok) { status.textContent = '{{ __('Scan failed.') }}'; return; }
        var s = d.scan, cats = s.categories || [];
        document.getElementById('apSummary').textContent = s.records_with_pii + ' {{ __('of') }} ' + s.scanned + ' {{ __('records contain personal data') }}';
        var tb = document.getElementById('apCats'); tb.innerHTML = '';
        if (!cats.length) { tb.innerHTML = '<tr><td colspan="4" class="text-muted">{{ __('No personal data detected in the sampled records.') }}</td></tr>'; }
        cats.forEach(function (c) {
          var tr = document.createElement('tr');
          tr.innerHTML = '<td>' + c.label + '</td><td class="text-end">' + c.records + '</td><td class="text-end">' + c.count + '</td>'
            + '<td class="small text-muted">' + (c.samples || []).map(function (id) { return '#' + id; }).join(', ') + '</td>';
          tb.appendChild(tr);
        });
        var dr = d.draft;
        setVal('apName', dr.name); setVal('apPurpose', dr.purpose); setVal('apBasis', dr.lawful_basis);
        setVal('apData', (dr.categories_of_data || []).join('\n'));
        setVal('apSubjects', (dr.categories_of_subjects || []).join('\n'));
        setVal('apRecipients', (dr.recipients || []).join('\n'));
        setVal('apRetention', dr.retention_period); setVal('apSecurity', dr.security_measures);
        result.style.display = 'block';
      })
      .catch(function () { btn.disabled = false; btn.innerHTML = '<i class="fas fa-magnifying-glass-chart me-1"></i>{{ __('Scan the catalogue') }}'; status.textContent = '{{ __('Scan failed.') }}'; });
  });
})();
</script>
@endsection
