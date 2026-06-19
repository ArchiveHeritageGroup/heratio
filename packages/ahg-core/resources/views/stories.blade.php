{{-- heratio#1202 Storytelling: theme -> AI narrative woven from catalogue objects. --}}
@extends('theme::layouts.1col')
@section('title', __('Story Generator'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-feather-pointed me-2 text-primary"></i>{{ __('Story Generator') }}</h1>
    <span class="text-muted small">{{ __('Turn the collection into a story') }}</span>
    @if(Route::has('exhibition-space.browse'))
      <a href="{{ route('exhibition-space.browse') }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-arrow-left me-1"></i>{{ __('Exhibition spaces') }}</a>
    @endif
  </div>
  <p class="text-muted small">{{ __('Give a theme and Heratio writes a short, engaging public story that weaves together real objects from the collection - for a website post, a newsletter, a school pack or a label. Review and edit before you publish.') }}</p>

  <div class="input-group mb-2" style="max-width:680px">
    <input type="text" id="stTheme" class="form-control" placeholder="{{ __('e.g. the river that built the town, women at work, our oldest treasures') }}" maxlength="200">
    <button type="button" id="stGo" class="btn btn-primary"><i class="fas fa-feather-pointed me-1"></i>{{ __('Write the story') }}</button>
    <button type="button" id="stToday" class="btn btn-outline-primary" title="{{ __('A story from records dated today (or this month)') }}"><i class="fas fa-calendar-day me-1"></i>{{ __('On this day') }}</button>
  </div>
  <div class="d-flex flex-wrap gap-1 mb-2" id="stChips"></div>

  <details class="mb-3" style="max-width:680px">
    <summary class="small text-primary" style="cursor:pointer"><i class="fas fa-layer-group me-1"></i>{{ __('Add sources to ground the story (optional)') }}</summary>
    <div class="border rounded p-3 mt-2">
      <label class="form-label small mb-1">{{ __('Background notes') }}</label>
      <textarea id="srcNotes" class="form-control form-control-sm mb-3" rows="3" placeholder="{{ __('Paste any background, context or facts the story should draw on…') }}"></textarea>

      <label class="form-label small mb-1">{{ __('Source web pages (URLs)') }}</label>
      <div class="input-group input-group-sm mb-1">
        <input type="url" id="srcUrl" class="form-control" placeholder="{{ __('https://…') }}">
        <button type="button" id="srcUrlAdd" class="btn btn-outline-secondary"><i class="fas fa-plus"></i></button>
      </div>
      <div id="srcUrlChips" class="d-flex flex-wrap gap-1 mb-3"></div>

      <label class="form-label small mb-1">{{ __('Upload documents') }} <span class="text-muted">{{ __('(PDF, text or image - max 8 MB each, up to 5)') }}</span></label>
      <input type="file" id="srcFile" class="form-control form-control-sm mb-3" accept=".pdf,.txt,.png,.jpg,.jpeg" multiple>

      <label class="form-label small mb-1">{{ __('Include specific records') }}</label>
      <div class="position-relative">
        <input type="text" id="srcRecSearch" class="form-control form-control-sm" placeholder="{{ __('Search records by title…') }}" autocomplete="off">
        <div id="srcRecSuggest" class="list-group position-absolute w-100 shadow" style="z-index:20;display:none;max-height:240px;overflow:auto"></div>
      </div>
      <div id="srcRecChips" class="d-flex flex-wrap gap-1 mt-2"></div>
    </div>
  </details>

  <div id="stErr" class="alert alert-warning" style="display:none"></div>

  <div class="row g-3" id="stResult" style="display:none">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header py-2"><strong>{{ __('The story') }}</strong> <small class="text-muted">{{ __('editable') }}</small></div>
        <div class="card-body">
          <input type="text" id="stTitle" class="form-control fw-bold mb-2" placeholder="{{ __('Story title') }}">
          <textarea id="stStory" class="form-control" rows="11" style="line-height:1.6"></textarea>
          <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <button type="button" id="stCopy" class="btn btn-sm btn-outline-secondary"><i class="fas fa-copy me-1"></i>{{ __('Copy') }}</button>
            <button type="button" id="stSaveDraft" class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i>{{ __('Save draft') }}</button>
            <button type="button" id="stPublish" class="btn btn-sm btn-success"><i class="fas fa-globe me-1"></i>{{ __('Publish') }}</button>
            <span id="stSaveMsg" class="small"></span>
          </div>
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

  @if(!empty($saved))
    <hr class="my-4">
    <h2 class="h6 text-muted mb-2"><i class="fas fa-book-open me-1"></i>{{ __('Saved stories') }}</h2>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr><th>{{ __('Title') }}</th><th>{{ __('Theme') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th><th></th></tr></thead>
        <tbody>
          @foreach($saved as $s)
            <tr>
              <td><a href="{{ route('stories.show', ['slug' => $s->slug]) }}" target="_blank" rel="noopener">{{ $s->title }}</a></td>
              <td class="small text-muted">{{ $s->theme }}</td>
              <td>
                @if($s->status === 'published')
                  <span class="badge bg-success">{{ __('Published') }}</span>
                @else
                  <span class="badge bg-secondary">{{ __('Draft') }}</span>
                @endif
              </td>
              <td class="small text-muted">{{ $s->updated_at }}</td>
              <td class="text-end"><a href="{{ route('stories.show', ['slug' => $s->slug]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-external-link-alt"></i></a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URL = '{{ route('stories.generate') }}';
  var SAVE_URL = '{{ route('stories.save') }}';
  var SEARCH_URL = '{{ route('stories.search') }}';
  var themeEl = document.getElementById('stTheme'), goBtn = document.getElementById('stGo'),
      errEl = document.getElementById('stErr'), res = document.getElementById('stResult');
  var curObjects = [], curId = 0, curSources = [], pickedRecords = [], urls = [];
  ['{{ __('our oldest treasures') }}', '{{ __('women at work') }}', '{{ __('the sea and the harbour') }}'].forEach(function (s) {
    var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary'; b.textContent = s;
    b.addEventListener('click', function () { themeEl.value = s; run(); });
    document.getElementById('stChips').appendChild(b);
  });
  function esc(t) { var d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
  function run() {
    var theme = themeEl.value.trim();
    var notes = document.getElementById('srcNotes').value.trim();
    var pendingUrl = document.getElementById('srcUrl').value.trim();
    if (pendingUrl) { addUrl(pendingUrl); }   // forgive a typed-but-not-added URL
    var fileEl = document.getElementById('srcFile');
    var nFiles = fileEl.files ? fileEl.files.length : 0;
    var hasExtra = notes || urls.length || nFiles || pickedRecords.length;
    if (!theme && !hasExtra) { themeEl.focus(); return; }
    errEl.style.display = 'none'; errEl.className = 'alert alert-warning'; res.style.display = 'none';
    goBtn.disabled = true; goBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Writing…') }}';
    var fd = new FormData(); fd.append('theme', theme); fd.append('_token', CSRF);
    if (notes) { fd.append('notes', notes); }
    urls.forEach(function (u) { fd.append('urls[]', u); });
    for (var i = 0; i < nFiles; i++) { fd.append('documents[]', fileEl.files[i]); }
    pickedRecords.forEach(function (r) { fd.append('record_ids[]', r.id); });
    fetch(URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-feather-pointed me-1"></i>{{ __('Write the story') }}';
        applyResponse(d, false);
      })
      .catch(function () {
        goBtn.disabled = false; goBtn.innerHTML = '<i class="fas fa-feather-pointed me-1"></i>{{ __('Write the story') }}';
        errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong. Please try again.') }}';
      });
  }

  // Shared rendering for both "Write the story" and "On this day".
  function applyResponse(d, isToday) {
    var warns = (d && d.source_warnings) ? d.source_warnings : [];
    if (!d || !d.ok) {
      errEl.style.display = 'block';
      errEl.innerHTML = (warns.length
          ? '{{ __('Some sources could not be used:') }}<ul class="mb-0">' + warns.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul>'
          : '')
        + (isToday
          ? '{{ __('Nothing in the collection is dated to today or this month yet. Add dates to records, or write a themed story instead.') }}'
          : ((!d || !d.objects || !d.objects.length)
            ? '{{ __('No catalogue objects matched that theme, and no usable sources were given. Try a different theme or add a source.') }}'
            : '{{ __('Could not write a story. Try again or rephrase.') }}'));
      return;
    }
    curId = 0; curObjects = d.objects || []; curSources = d.sources || [];
    document.getElementById('stTitle').value = d.theme;
    document.getElementById('stStory').value = d.story;
    document.getElementById('stSaveMsg').innerHTML = '';
    if (warns.length) {
      errEl.style.display = 'block';
      errEl.innerHTML = '{{ __('Story written. Some sources could not be used:') }}<ul class="mb-0">' + warns.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul>';
    } else if (isToday && d.scope === 'month') {
      errEl.style.display = 'block';
      errEl.className = 'alert alert-info';
      errEl.textContent = '{{ __('Nothing was dated to today exactly, so this is a story for the whole month.') }}';
    }
    var ob = document.getElementById('stObjects'); ob.innerHTML = '';
    curObjects.forEach(function (o) {
      var div = document.createElement('div'); div.className = 'small border-bottom py-1';
      div.innerHTML = '<i class="fas fa-cube text-muted me-1"></i>' + esc(o.title);
      ob.appendChild(div);
    });
    res.style.display = 'flex';
  }

  function onThisDay() {
    errEl.style.display = 'none'; errEl.className = 'alert alert-warning'; res.style.display = 'none';
    var btn = document.getElementById('stToday'); var orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-1"></i>{{ __('Looking…') }}';
    fetch('{{ route('stories.on-this-day') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { btn.disabled = false; btn.innerHTML = orig; applyResponse(d, true); })
      .catch(function () { btn.disabled = false; btn.innerHTML = orig; errEl.style.display = 'block'; errEl.textContent = '{{ __('Something went wrong. Please try again.') }}'; });
  }

  goBtn.addEventListener('click', run);
  document.getElementById('stToday').addEventListener('click', onThisDay);
  themeEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });

  // Multiple source URLs (add to a chip list).
  var urlInput = document.getElementById('srcUrl'), urlChips = document.getElementById('srcUrlChips');
  function renderUrlChips() {
    urlChips.innerHTML = '';
    urls.forEach(function (u, i) {
      var span = document.createElement('span'); span.className = 'badge bg-light text-dark border text-truncate'; span.style.maxWidth = '100%';
      span.innerHTML = '<i class="fas fa-link text-muted me-1"></i>' + esc(u) + ' <a href="#" class="text-danger ms-1" data-i="' + i + '">&times;</a>';
      urlChips.appendChild(span);
    });
  }
  function addUrl(u) {
    u = (u || '').trim();
    if (!u) { return; }
    if (!/^https?:\/\//i.test(u)) { u = 'https://' + u; }
    if (urls.length >= 5) { return; }
    if (urls.indexOf(u) === -1) { urls.push(u); renderUrlChips(); }
    urlInput.value = '';
  }
  document.getElementById('srcUrlAdd').addEventListener('click', function () { addUrl(urlInput.value); });
  urlInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addUrl(urlInput.value); } });
  urlChips.addEventListener('click', function (e) {
    if (e.target.matches('a[data-i]')) { e.preventDefault(); urls.splice(+e.target.getAttribute('data-i'), 1); renderUrlChips(); }
  });

  // Record picker (hand-pick catalogue records to weave in for certain).
  var recSearch = document.getElementById('srcRecSearch'), recSug = document.getElementById('srcRecSuggest'), recChips = document.getElementById('srcRecChips');
  function renderRecChips() {
    recChips.innerHTML = '';
    pickedRecords.forEach(function (r, i) {
      var span = document.createElement('span'); span.className = 'badge bg-light text-dark border';
      span.innerHTML = '<i class="fas fa-cube text-muted me-1"></i>' + esc(r.title) + ' <a href="#" class="text-danger ms-1" data-i="' + i + '">&times;</a>';
      recChips.appendChild(span);
    });
  }
  recChips.addEventListener('click', function (e) {
    if (e.target.matches('a[data-i]')) { e.preventDefault(); pickedRecords.splice(+e.target.getAttribute('data-i'), 1); renderRecChips(); }
  });
  var recTmr = null;
  recSearch.addEventListener('input', function () {
    var q = recSearch.value.trim(); clearTimeout(recTmr);
    if (q.length < 2) { recSug.style.display = 'none'; return; }
    recTmr = setTimeout(function () {
      fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
          recSug.innerHTML = '';
          (rows || []).forEach(function (row) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'list-group-item list-group-item-action small';
            b.textContent = row.title;
            b.addEventListener('click', function () {
              if (!pickedRecords.some(function (p) { return p.id === row.id; })) { pickedRecords.push(row); renderRecChips(); }
              recSug.style.display = 'none'; recSearch.value = '';
            });
            recSug.appendChild(b);
          });
          recSug.style.display = rows && rows.length ? 'block' : 'none';
        }).catch(function () { recSug.style.display = 'none'; });
    }, 250);
  });
  document.addEventListener('click', function (e) { if (!recSug.contains(e.target) && e.target !== recSearch) recSug.style.display = 'none'; });

  document.getElementById('stCopy').addEventListener('click', function () {
    var t = document.getElementById('stStory'); t.select();
    try { document.execCommand('copy'); this.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Copied') }}'; } catch (e) {}
  });

  function save(status, btn) {
    var title = document.getElementById('stTitle').value.trim();
    var body = document.getElementById('stStory').value.trim();
    var msg = document.getElementById('stSaveMsg');
    if (!title || !body) { msg.className = 'small text-danger'; msg.textContent = '{{ __('Add a title and some text first.') }}'; return; }
    var orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
    var payload = { id: curId || null, title: title, theme: themeEl.value.trim(), body: body, status: status,
      objects: curObjects.map(function (o) { return { id: o.id }; }), sources: curSources };
    fetch(SAVE_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false; btn.innerHTML = orig;
        if (!d || !d.ok) { msg.className = 'small text-danger'; msg.textContent = '{{ __('Could not save. Please try again.') }}'; return; }
        curId = d.id;
        msg.className = 'small text-success';
        if (d.status === 'published' && d.url) {
          msg.innerHTML = '{{ __('Published') }} - <a href="' + d.url + '" target="_blank" rel="noopener">{{ __('view public page') }}</a>';
        } else {
          msg.textContent = '{{ __('Saved as draft.') }}';
        }
      })
      .catch(function () { btn.disabled = false; btn.innerHTML = orig; msg.className = 'small text-danger'; msg.textContent = '{{ __('Something went wrong. Please try again.') }}'; });
  }
  document.getElementById('stSaveDraft').addEventListener('click', function () { save('draft', this); });
  document.getElementById('stPublish').addEventListener('click', function () { save('published', this); });
})();
</script>
@endsection
