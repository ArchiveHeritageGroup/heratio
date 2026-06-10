{{-- heratio#1186 Generative exhibitions: theme -> AI-curated draft (rooms + objects + labels). --}}
@extends('theme::layouts.1col')
@section('title', __('AI Exhibition Designer'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-wand-magic-sparkles me-2 text-primary"></i>{{ __('AI Exhibition Designer') }}</h1>
    <span class="text-muted small">{{ __('A theme in, a draft exhibition out') }}</span>
    <a href="{{ route('exhibition-space.browse') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-arrow-left me-1"></i>{{ __('Exhibition spaces') }}</a>
  </div>
  <p class="text-muted small">{{ __('Describe a theme. Heratio searches the catalogue and the AI curates a draft exhibition - rooms, a selection of objects, and a one-line label for each. Review it here; building a real space from the draft comes next.') }}</p>

  <div class="input-group mb-2" style="max-width:680px">
    <input type="text" id="geTheme" class="form-control" placeholder="{{ __('e.g. women in the liberation struggle, Victorian furniture, WWI letters') }}" maxlength="200">
    <button type="button" id="geGo" class="btn btn-primary"><i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Design it') }}</button>
  </div>
  <div class="d-flex flex-wrap gap-1 mb-3" id="geChips"></div>

  <div id="geErr" class="alert alert-warning" style="display:none"></div>
  <div id="geResult"></div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URL = '{{ route('exhibition-space.generate.suggest') }}';
  var BUILD_URL = '{{ route('exhibition-space.generate.build') }}';
  var themeEl = document.getElementById('geTheme'), goBtn = document.getElementById('geGo'),
      errEl = document.getElementById('geErr'), res = document.getElementById('geResult');
  var lastDraft = null;
  var samples = ['{{ __('women in the liberation struggle') }}', '{{ __('Victorian furniture') }}', '{{ __('maritime history') }}', '{{ __('colonial-era photography') }}'];
  var chips = document.getElementById('geChips');
  samples.forEach(function (s) {
    var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary'; b.textContent = s;
    b.addEventListener('click', function () { themeEl.value = s; run(); });
    chips.appendChild(b);
  });
  function esc(t) { var d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
  function run() {
    var theme = themeEl.value.trim();
    if (!theme) { themeEl.focus(); return; }
    errEl.style.display = 'none'; res.innerHTML = '';
    goBtn.disabled = true; goBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Curating…') }}';
    var fd = new FormData(); fd.append('theme', theme); fd.append('_token', CSRF);
    fetch(URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Design it') }}';
        if (!d || !d.ok || !d.rooms || !d.rooms.length) {
          errEl.style.display = 'block';
          errEl.textContent = (d && d.candidate_count === 0)
            ? '{{ __('No catalogue objects matched that theme. Try different or broader words.') }}'
            : '{{ __('Could not draft an exhibition for that theme. Try again or rephrase.') }}';
          return;
        }
        var html = '<div class="row g-3">';
        d.rooms.forEach(function (rm, i) {
          html += '<div class="col-md-6 col-xl-4"><div class="card h-100">'
            + '<div class="card-header py-2"><i class="fas fa-door-open me-1 text-primary"></i><strong>' + esc(rm.room) + '</strong> <span class="badge bg-secondary ms-1">' + rm.objects.length + '</span></div>'
            + '<div class="card-body p-2">';
          rm.objects.forEach(function (o) {
            html += '<div class="border-bottom py-1"><div class="small fw-bold">' + esc(o.title) + '</div>'
              + '<div class="small text-muted">' + esc(o.label) + '</div></div>';
          });
          html += '</div></div></div>';
        });
        html += '</div>';
        html += '<div class="d-flex flex-wrap align-items-center gap-2 mt-3">'
          + '<button type="button" id="geBuild" class="btn btn-success"><i class="fas fa-cubes me-1"></i>{{ __('Build this exhibition') }}</button>'
          + '<span class="small text-muted">{{ __('Creates a real Exhibition Space - one room per card above, each object placed - then opens it in the builder.') }}</span></div>';
        res.innerHTML = html;
        lastDraft = { theme: theme, rooms: d.rooms };
        var buildBtn = document.getElementById('geBuild');
        if (buildBtn) { buildBtn.addEventListener('click', build); }
      })
      .catch(function () {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Design it') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong. Please try again.') }}';
      });
  }
  function build() {
    if (!lastDraft || !lastDraft.rooms || !lastDraft.rooms.length) { return; }
    var btn = document.getElementById('geBuild');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Building…') }}';
    errEl.style.display = 'none';
    var payload = { theme: lastDraft.theme || '', rooms: lastDraft.rooms.map(function (rm) {
      return { room: rm.room, objects: rm.objects.map(function (o) { return { id: o.id }; }) };
    }) };
    fetch(BUILD_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok && d.builder_url) { window.location.href = d.builder_url; return; }
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-cubes me-1"></i>{{ __('Build this exhibition') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Could not build the exhibition from this draft. Please try again.') }}';
      })
      .catch(function () {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-cubes me-1"></i>{{ __('Build this exhibition') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong while building. Please try again.') }}';
      });
  }
  goBtn.addEventListener('click', run);
  themeEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
})();
</script>
@endsection
