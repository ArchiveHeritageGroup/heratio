{{-- heratio#1202 Storytelling: theme -> AI narrative woven from catalogue objects. --}}
@extends('theme::layouts.1col')
@section('title', __('Story Generator'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-feather-pointed me-2 text-primary"></i>{{ __('Story Generator') }}</h1>
    <span class="text-muted small">{{ __('Turn the collection into a story') }}</span>
  </div>
  <p class="text-muted small">{{ __('Give a theme and Heratio writes a short, engaging public story that weaves together real objects from the collection - for a website post, a newsletter, a school pack or a label. Review and edit before you publish.') }}</p>

  <div class="input-group mb-2" style="max-width:680px">
    <input type="text" id="stTheme" class="form-control" placeholder="{{ __('e.g. the river that built the town, women at work, our oldest treasures') }}" maxlength="200">
    <button type="button" id="stGo" class="btn btn-primary"><i class="fas fa-feather-pointed me-1"></i>{{ __('Write the story') }}</button>
  </div>
  <div class="d-flex flex-wrap gap-1 mb-3" id="stChips"></div>

  <div id="stErr" class="alert alert-warning" style="display:none"></div>

  <div class="row g-3" id="stResult" style="display:none">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('The story') }}</strong> <small class="text-muted">{{ __('editable') }}</small></div>
        <div class="card-body">
          <h5 id="stTitle" class="mb-2"></h5>
          <textarea id="stStory" class="form-control" rows="11" style="line-height:1.6"></textarea>
          <button type="button" id="stCopy" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-copy me-1"></i>{{ __('Copy') }}</button>
          <span class="small text-muted ms-2">{{ __('Saving / publishing comes next - copy it out for now.') }}</span>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('Objects featured') }}</strong></div>
        <div class="card-body p-2"><div id="stObjects"></div></div>
      </div>
    </div>
  </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URL = '{{ route('stories.generate') }}';
  var themeEl = document.getElementById('stTheme'), goBtn = document.getElementById('stGo'),
      errEl = document.getElementById('stErr'), res = document.getElementById('stResult');
  ['{{ __('our oldest treasures') }}', '{{ __('women at work') }}', '{{ __('the sea and the harbour') }}'].forEach(function (s) {
    var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary'; b.textContent = s;
    b.addEventListener('click', function () { themeEl.value = s; run(); });
    document.getElementById('stChips').appendChild(b);
  });
  function esc(t) { var d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
  function run() {
    var theme = themeEl.value.trim(); if (!theme) { themeEl.focus(); return; }
    errEl.style.display = 'none'; res.style.display = 'none';
    goBtn.disabled = true; goBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Writing…') }}';
    var fd = new FormData(); fd.append('theme', theme); fd.append('_token', CSRF);
    fetch(URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-feather-pointed me-1"></i>{{ __('Write the story') }}';
        if (!d || !d.ok) {
          errEl.style.display = 'block';
          errEl.textContent = (d && (!d.objects || !d.objects.length))
            ? '{{ __('No catalogue objects matched that theme. Try different or broader words.') }}'
            : '{{ __('Could not write a story for that theme. Try again or rephrase.') }}';
          return;
        }
        document.getElementById('stTitle').textContent = d.theme;
        document.getElementById('stStory').value = d.story;
        var ob = document.getElementById('stObjects'); ob.innerHTML = '';
        (d.objects || []).forEach(function (o) {
          var div = document.createElement('div'); div.className = 'small border-bottom py-1';
          div.innerHTML = '<i class="fas fa-cube text-muted me-1"></i>' + esc(o.title);
          ob.appendChild(div);
        });
        res.style.display = 'flex';
      })
      .catch(function () {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-feather-pointed me-1"></i>{{ __('Write the story') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong. Please try again.') }}';
      });
  }
  goBtn.addEventListener('click', run);
  themeEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
  document.getElementById('stCopy').addEventListener('click', function () {
    var t = document.getElementById('stStory'); t.select();
    try { document.execCommand('copy'); this.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Copied') }}'; } catch (e) {}
  });
})();
</script>
@endsection
