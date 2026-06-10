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

  {{-- heratio#1199 retention slice: auto-draft a retention schedule from the same scan. --}}
  <div class="card mt-4">
    <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
      <strong><i class="fas fa-clock-rotate-left me-1 text-primary"></i>{{ __('Retention schedule') }}</strong>
      <small class="text-muted">{{ __('auto-draft a defensible retention period per data category, for sign-off') }}</small>
      <button type="button" id="apRetBtn" class="btn btn-sm btn-outline-primary ms-auto"><i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Draft retention schedule') }}</button>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-2">{{ __('Heratio asks the AI gateway to suggest a defensible retention period, a generic legal/policy basis and a disposal action for each category of personal data the scan found. Suggestions are jurisdiction-neutral - confirm the concrete law against your enabled market module. Each proposal is held for a data-protection officer to accept.') }}</p>
      <span id="apRetStatus" class="small text-muted"></span>
      <div class="table-responsive mt-2">
        <table class="table table-sm align-middle mb-0">
          <thead><tr>
            <th>{{ __('Category') }}</th><th class="text-end">{{ __('Records') }}</th>
            <th>{{ __('Retention period') }}</th><th>{{ __('Legal / policy basis') }}</th>
            <th>{{ __('Disposal action') }}</th><th>{{ __('Status') }}</th><th></th>
          </tr></thead>
          <tbody id="apRetRows">
            @forelse(($retentionProposals ?? []) as $p)
              @php $accepted = ($p['status'] ?? '') === 'accepted'; @endphp
              <tr data-id="{{ $p['id'] }}">
                <td>{{ $p['category_label'] }}</td>
                <td class="text-end">{{ $p['records_affected'] }}</td>
                <td class="small">{{ $p['retention_period'] }}</td>
                <td class="small text-muted">{{ $p['legal_basis'] }}</td>
                <td class="small">{{ $p['disposal_action'] }}</td>
                <td>
                  @if($accepted)
                    <span class="badge bg-success">{{ __('Accepted') }}</span>
                  @else
                    <span class="badge bg-warning text-dark">{{ __('Proposed') }}</span>
                  @endif
                </td>
                <td class="text-end">
                  @unless($accepted)
                    <button type="button" class="btn btn-sm btn-success ap-ret-accept" data-id="{{ $p['id'] }}"><i class="fas fa-check me-1"></i>{{ __('Accept') }}</button>
                  @endunless
                </td>
              </tr>
            @empty
              <tr id="apRetEmpty"><td colspan="7" class="text-muted">{{ __('No retention proposals yet. Run "Draft retention schedule" to generate them from a catalogue scan.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- heratio#1199 DPIA slice: screen the scan (DpiaRiskService / WP29) -> auto-draft a DPIA. --}}
  @php
    $apDpia = $autoDpia ?? null;
    $apDpiaStatus = $apDpia['status'] ?? null;
    $apDpiaDraft = $apDpiaStatus === 'draft';
  @endphp
  <div class="card mt-4" id="apDpiaCard">
    <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
      <strong><i class="fas fa-clipboard-check me-1 text-primary"></i>{{ __('Data Protection Impact Assessment') }}</strong>
      <small class="text-muted">{{ __('screen the scan for high-risk processing and auto-draft a DPIA for sign-off') }}</small>
      <button type="button" id="apDpiaBtn" class="btn btn-sm btn-outline-primary ms-auto"><i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Screen and draft DPIA') }}</button>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-2">{{ __('Heratio screens the categories of personal data found against the high-risk triggers (GDPR Article 35(3) / WP29 criteria): special-category data, large-scale profiling, biometric/genetic data, and unsafeguarded cross-border transfers. If a DPIA is required it auto-drafts the risk findings and a recommendation as a draft for a data-protection officer to review and sign off. Jurisdiction-neutral - confirm the concrete obligation against your enabled market module.') }}</p>
      <span id="apDpiaStatus" class="small text-muted"></span>

      <div id="apDpiaVerdict" class="mt-2" @unless($apDpia) style="display:none" @endunless>
        @if($apDpia)
          <div class="alert alert-warning py-2 mb-2" id="apDpiaRequiredMsg">
            <i class="fas fa-triangle-exclamation me-1"></i>{{ __('A DPIA is required for this processing.') }}
          </div>
          <div class="alert alert-success py-2 mb-2" id="apDpiaNotRequiredMsg" style="display:none">
            <i class="fas fa-circle-check me-1"></i>{{ __('No DPIA required on screening grounds.') }}
          </div>
        @else
          <div class="alert alert-warning py-2 mb-2" id="apDpiaRequiredMsg" style="display:none">
            <i class="fas fa-triangle-exclamation me-1"></i>{{ __('A DPIA is required for this processing.') }}
          </div>
          <div class="alert alert-success py-2 mb-2" id="apDpiaNotRequiredMsg" style="display:none">
            <i class="fas fa-circle-check me-1"></i>{{ __('No DPIA required on screening grounds.') }}
          </div>
        @endif

        <div class="mb-2">
          <span class="small text-muted">{{ __('High-risk triggers:') }}</span>
          <span id="apDpiaTriggers"></span>
        </div>

        <div id="apDpiaDraft" @unless($apDpia) style="display:none" @endunless>
          <div class="card border-warning">
            <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
              <strong>{{ __('Draft DPIA') }}</strong>
              <span id="apDpiaBadge" class="badge {{ $apDpiaDraft ? 'bg-warning text-dark' : 'bg-success' }}">{{ ucfirst($apDpiaStatus ?? 'draft') }}</span>
              <span class="ms-auto">
                <a id="apDpiaEdit" href="{{ $apDpia['edit_url'] ?? '#' }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen me-1"></i>{{ __('Open in DPIA register') }}</a>
                <button type="button" id="apDpiaAccept" class="btn btn-sm btn-success" data-id="{{ $apDpia['id'] ?? '' }}" @unless($apDpiaDraft) style="display:none" @endunless><i class="fas fa-check me-1"></i>{{ __('Accept (move to review)') }}</button>
              </span>
            </div>
            <div class="card-body">
              <dl class="row mb-0 small">
                <dt class="col-sm-3">{{ __('Description') }}</dt><dd class="col-sm-9" id="apDpiaDesc">{{ $apDpia['description'] ?? '' }}</dd>
                <dt class="col-sm-3">{{ __('Necessity / proportionality') }}</dt><dd class="col-sm-9" id="apDpiaNec">{{ $apDpia['necessity_proportionality'] ?? '' }}</dd>
                <dt class="col-sm-3">{{ __('Risks to subjects') }}</dt><dd class="col-sm-9" id="apDpiaRisks">{{ $apDpia['risks_to_subjects'] ?? '' }}</dd>
                <dt class="col-sm-3">{{ __('Mitigation measures') }}</dt><dd class="col-sm-9" id="apDpiaMeasures">{{ $apDpia['measures_to_mitigate'] ?? '' }}</dd>
                <dt class="col-sm-3">{{ __('Residual risk') }}</dt><dd class="col-sm-9" id="apDpiaResidual">{{ $apDpia['residual_risks'] ?? '' }}</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
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

  // ---- heratio#1199 retention slice ----
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  var retBtn = document.getElementById('apRetBtn'), retStatus = document.getElementById('apRetStatus'),
      retRows = document.getElementById('apRetRows');

  function acceptUrl(id) { return '{{ url('admin/privacy/autopilot/retention') }}/' + id + '/accept'; }

  function bindAccept(b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-id');
      b.disabled = true;
      fetch(acceptUrl(id), { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) { b.disabled = false; return; }
          var tr = b.closest('tr'), st = tr.querySelector('td:nth-child(6)');
          if (st) st.innerHTML = '<span class="badge bg-success">{{ __('Accepted') }}</span>';
          b.remove();
        })
        .catch(function () { b.disabled = false; });
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('.ap-ret-accept'), bindAccept);

  retBtn.addEventListener('click', function () {
    retBtn.disabled = true; retBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Drafting…') }}';
    retStatus.textContent = '{{ __('Scanning and asking the AI gateway for retention periods…') }}';
    fetch('{{ route('ahgprivacy.autopilot.retention') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        retBtn.disabled = false; retBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Draft retention schedule') }}';
        if (!d || !d.ok) { retStatus.textContent = '{{ __('Draft failed.') }}'; return; }
        var src = d.source === 'llm' ? '{{ __('AI-suggested') }}' : '{{ __('heuristic fallback (gateway unavailable)') }}';
        retStatus.textContent = (d.proposals || []).length + ' {{ __('proposals') }} - ' + src;
        retRows.innerHTML = '';
        if (!(d.proposals || []).length) { retRows.innerHTML = '<tr><td colspan="7" class="text-muted">{{ __('No personal-data categories found to base a retention schedule on.') }}</td></tr>'; return; }
        d.proposals.forEach(function (p) {
          var acc = p.status === 'accepted';
          var tr = document.createElement('tr'); tr.setAttribute('data-id', p.id);
          tr.innerHTML = '<td>' + esc(p.category_label) + '</td>'
            + '<td class="text-end">' + esc(p.records_affected) + '</td>'
            + '<td class="small">' + esc(p.retention_period) + '</td>'
            + '<td class="small text-muted">' + esc(p.legal_basis) + '</td>'
            + '<td class="small">' + esc(p.disposal_action) + '</td>'
            + '<td>' + (acc ? '<span class="badge bg-success">{{ __('Accepted') }}</span>' : '<span class="badge bg-warning text-dark">{{ __('Proposed') }}</span>') + '</td>'
            + '<td class="text-end">' + (acc ? '' : '<button type="button" class="btn btn-sm btn-success ap-ret-accept" data-id="' + esc(p.id) + '"><i class="fas fa-check me-1"></i>{{ __('Accept') }}</button>') + '</td>';
          retRows.appendChild(tr);
          var nb = tr.querySelector('.ap-ret-accept'); if (nb) bindAccept(nb);
        });
      })
      .catch(function () { retBtn.disabled = false; retBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Draft retention schedule') }}'; retStatus.textContent = '{{ __('Draft failed.') }}'; });
  });

  // ---- heratio#1199 DPIA slice ----
  var dpiaBtn = document.getElementById('apDpiaBtn'), dpiaStatus = document.getElementById('apDpiaStatus'),
      dpiaVerdict = document.getElementById('apDpiaVerdict'), dpiaTriggers = document.getElementById('apDpiaTriggers'),
      dpiaDraftBox = document.getElementById('apDpiaDraft'),
      dpiaRequiredMsg = document.getElementById('apDpiaRequiredMsg'), dpiaNotRequiredMsg = document.getElementById('apDpiaNotRequiredMsg'),
      dpiaAcceptBtn = document.getElementById('apDpiaAccept');
  function dpiaAcceptUrl(id) { return '{{ url('admin/privacy/autopilot/dpia') }}/' + id + '/accept'; }
  function setText(id, v) { var el = document.getElementById(id); if (el) el.textContent = v == null ? '' : String(v); }

  function bindDpiaAccept(b) {
    if (!b) return;
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-id');
      if (!id) return;
      b.disabled = true;
      fetch(dpiaAcceptUrl(id), { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) { b.disabled = false; return; }
          var badge = document.getElementById('apDpiaBadge');
          if (badge) { badge.className = 'badge bg-info text-dark'; badge.textContent = 'Review'; }
          b.style.display = 'none';
        })
        .catch(function () { b.disabled = false; });
    });
  }
  bindDpiaAccept(dpiaAcceptBtn);

  dpiaBtn.addEventListener('click', function () {
    dpiaBtn.disabled = true; dpiaBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Screening…') }}';
    dpiaStatus.textContent = '{{ __('Scanning and screening for high-risk processing…') }}';
    fetch('{{ route('ahgprivacy.autopilot.dpia') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        dpiaBtn.disabled = false; dpiaBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Screen and draft DPIA') }}';
        if (!d || !d.ok) { dpiaStatus.textContent = '{{ __('Screen failed.') }}'; return; }
        var src = d.source === 'llm' ? '{{ __('AI-drafted') }}' : (d.source === 'heuristic' ? '{{ __('heuristic fallback (gateway unavailable)') }}' : '{{ __('screen only') }}');
        dpiaStatus.textContent = src;
        dpiaVerdict.style.display = 'block';
        // triggers
        dpiaTriggers.innerHTML = '';
        (d.triggers || []).forEach(function (t) {
          var span = document.createElement('span'); span.className = 'badge bg-warning text-dark me-1';
          span.textContent = t; dpiaTriggers.appendChild(span);
        });
        if (!(d.triggers || []).length) { dpiaTriggers.innerHTML = '<span class="text-muted">{{ __('none') }}</span>'; }

        if (d.required && d.dpia) {
          if (dpiaRequiredMsg) dpiaRequiredMsg.style.display = '';
          if (dpiaNotRequiredMsg) dpiaNotRequiredMsg.style.display = 'none';
          dpiaDraftBox.style.display = 'block';
          var p = d.dpia;
          setText('apDpiaDesc', p.description); setText('apDpiaNec', p.necessity_proportionality);
          setText('apDpiaRisks', p.risks_to_subjects); setText('apDpiaMeasures', p.measures_to_mitigate);
          setText('apDpiaResidual', p.residual_risks);
          var edit = document.getElementById('apDpiaEdit'); if (edit && p.edit_url) edit.setAttribute('href', p.edit_url);
          var badge = document.getElementById('apDpiaBadge');
          var isDraft = p.status === 'draft';
          if (badge) { badge.className = 'badge ' + (isDraft ? 'bg-warning text-dark' : 'bg-info text-dark'); badge.textContent = p.status.charAt(0).toUpperCase() + p.status.slice(1); }
          var acc = document.getElementById('apDpiaAccept');
          if (acc) { acc.setAttribute('data-id', p.id); acc.style.display = isDraft ? '' : 'none'; acc.disabled = false; }
        } else {
          if (dpiaRequiredMsg) dpiaRequiredMsg.style.display = 'none';
          if (dpiaNotRequiredMsg) dpiaNotRequiredMsg.style.display = '';
          dpiaDraftBox.style.display = 'none';
        }
      })
      .catch(function () { dpiaBtn.disabled = false; dpiaBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Screen and draft DPIA') }}'; dpiaStatus.textContent = '{{ __('Screen failed.') }}'; });
  });
})();
</script>
@endsection
