{{-- heratio#1198 Researcher copilot: question -> grounded, cited synthesis from the catalogue. --}}
@extends('theme::layouts.1col')
@section('title', __('Research Copilot'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-user-graduate me-2 text-primary"></i>{{ __('Research Copilot') }}</h1>
    <span class="text-muted small">{{ __('Ask a question, get a cited answer from the collection') }}</span>
    <a href="{{ url('/research/dashboard') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-arrow-left me-1"></i>{{ __('Research dashboard') }}</a>
  </div>
  <p class="text-muted small">{{ __('Ask a research question. Heratio finds the most relevant records in the collection and the AI writes a concise answer that cites them by number. It only uses what is in those records - always verify against the originals.') }}</p>

  <div class="input-group mb-3" style="max-width:760px">
    <input type="text" id="rcQ" class="form-control" placeholder="{{ __('e.g. What does the collection hold about the harbour expansion?') }}" maxlength="300">
    <button type="button" id="rcGo" class="btn btn-primary"><i class="fas fa-magnifying-glass me-1"></i>{{ __('Ask') }}</button>
  </div>

  <div id="rcErr" class="alert alert-warning" style="display:none"></div>

  <div class="row g-3" id="rcResult" style="display:none">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('Answer') }}</strong> <small class="text-muted">{{ __('cited - verify against the records') }}</small></div>
        <div class="card-body"><div id="rcAnswer" style="white-space:pre-wrap;line-height:1.6"></div></div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('Sources') }}</strong></div>
        <div class="card-body p-2"><ol id="rcSources" class="mb-0 small" style="padding-left:1.2rem"></ol></div>
      </div>
    </div>
  </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URL = '{{ route('research.copilot.ask') }}';
  var qEl = document.getElementById('rcQ'), goBtn = document.getElementById('rcGo'),
      errEl = document.getElementById('rcErr'), res = document.getElementById('rcResult');
  function esc(t) { var d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
  function run() {
    var q = qEl.value.trim(); if (!q) { qEl.focus(); return; }
    errEl.style.display = 'none'; res.style.display = 'none';
    goBtn.disabled = true; goBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Searching & writing…') }}';
    var fd = new FormData(); fd.append('question', q); fd.append('_token', CSRF);
    fetch(URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-magnifying-glass me-1"></i>{{ __('Ask') }}';
        if (!d || !d.ok) {
          errEl.style.display = 'block';
          errEl.textContent = (d && (!d.sources || !d.sources.length))
            ? '{{ __('No records in the collection matched that question. Try different or broader terms.') }}'
            : '{{ __('Could not answer that from the collection. Try rephrasing.') }}';
          return;
        }
        document.getElementById('rcAnswer').textContent = d.answer;
        var ol = document.getElementById('rcSources'); ol.innerHTML = '';
        (d.sources || []).forEach(function (s) {
          var li = document.createElement('li');
          var label = esc(s.title);
          li.innerHTML = s.slug ? ('<a href="/' + encodeURIComponent(s.slug) + '" target="_blank" rel="noopener">' + label + '</a>') : label;
          if (s.scope) { li.innerHTML += '<div class="text-muted">' + esc(s.scope) + '</div>'; }
          ol.appendChild(li);
        });
        res.style.display = 'flex';
      })
      .catch(function () {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-magnifying-glass me-1"></i>{{ __('Ask') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong. Please try again.') }}';
      });
  }
  goBtn.addEventListener('click', run);
  qEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
})();
</script>
@endsection
