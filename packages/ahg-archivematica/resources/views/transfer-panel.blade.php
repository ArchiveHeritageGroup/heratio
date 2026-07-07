{{--
  transfer-panel.blade.php — "Send to Archivematica" trigger + live status panel.
  Direction 2 (Heratio -> Archivematica). Include on a record show page:

      @include('ahg-archivematica::transfer-panel', ['objectId' => $io->id])

  Bootstrap 5 admin styling (matches ahg-iiif-collection views). Progressive
  enhancement: the button is a real form POST, JS just adds live status polling.

  Copyright (C) 2026 Johan Pieterse — Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}
@php
    $objectId = $objectId ?? ($io->id ?? null);
    // Prefer named routes when the package registers them; fall back to the
    // documented paths so the panel still works if the names differ.
    $sendUrl = \Route::has('archivematica.transfer.trigger')
        ? route('archivematica.transfer.trigger', $objectId)
        : url('/admin/archivematica/transfer/' . $objectId);
    $statusUrl = \Route::has('archivematica.transfer.status')
        ? route('archivematica.transfer.status', $objectId)
        : url('/admin/archivematica/status/' . $objectId);
@endphp

@if($objectId)
<div class="card mb-3 archivematica-panel" id="am-transfer-panel" data-status-url="{{ $statusUrl }}">
  <div class="card-header bg-primary text-white d-flex align-items-center">
    <i class="fas fa-archive me-2"></i>
    <h5 class="mb-0">{{ __('Archivematica') }}</h5>
  </div>
  <div class="card-body">

    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show py-2">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger alert-dismissible fade show py-2">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <p class="small text-muted mb-3">
      {{ __('Send this record to Archivematica for preservation processing, then track the transfer here.') }}
    </p>

    <div id="am-status" class="mb-3">
      <span class="badge bg-secondary">
        <i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Checking status…') }}
      </span>
    </div>

    <form method="POST" action="{{ $sendUrl }}" id="am-send-form">
      @csrf
      <input type="hidden" name="type" value="standard">
      <button type="submit" class="btn btn-primary" id="am-send-btn">
        <i class="fas fa-paper-plane me-2"></i>{{ __('Send to Archivematica') }}
      </button>
    </form>
  </div>
</div>

<script>
(function () {
  var panel = document.getElementById('am-transfer-panel');
  if (!panel) { return; }
  var statusUrl = panel.getAttribute('data-status-url');
  var statusEl  = document.getElementById('am-status');
  var sendBtn   = document.getElementById('am-send-btn');

  var BADGE = {
    complete:   'bg-success',
    processing: 'bg-info text-dark',
    pending:    'bg-warning text-dark',
    failed:     'bg-danger',
    none:       'bg-secondary'
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }

  function render(data) {
    var job = data.job || null;
    var link = data.link || null;
    var status = (data.status || 'none');
    var badgeClass = BADGE[status] || 'bg-secondary';

    var html = '<span class="badge ' + badgeClass + '">' + esc(status) + '</span>';
    if (job && job.microservice) {
      html += ' <span class="small text-muted ms-1">' + esc(job.microservice) + '</span>';
    }
    if (job && job.error) {
      html += '<div class="text-danger small mt-2">' + esc(job.error) + '</div>';
    }

    var uuids = [];
    if (job && job.am_uuid) { uuids.push(['Transfer', job.am_uuid]); }
    if (link && link.sip_uuid) { uuids.push(['SIP', link.sip_uuid]); }
    if (link && link.aip_uuid) { uuids.push(['AIP', link.aip_uuid]); }
    if (uuids.length) {
      html += '<dl class="row small mt-2 mb-0">';
      uuids.forEach(function (u) {
        html += '<dt class="col-4 text-muted">' + esc(u[0]) + '</dt>'
              + '<dd class="col-8"><code>' + esc(u[1]) + '</code></dd>';
      });
      html += '</dl>';
    }
    statusEl.innerHTML = html;

    // Disable the send button while a transfer is already in flight.
    if (sendBtn) {
      var busy = (status === 'processing' || status === 'pending');
      sendBtn.disabled = busy;
    }
    return status;
  }

  function poll() {
    fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var status = render(data);
        // Keep polling while a transfer is running.
        if (status === 'processing' || status === 'pending') {
          setTimeout(poll, 15000);
        }
      })
      .catch(function () {
        statusEl.innerHTML = '<span class="badge bg-secondary">'
          + 'status unavailable</span>';
      });
  }

  poll();
})();
</script>
@endif
