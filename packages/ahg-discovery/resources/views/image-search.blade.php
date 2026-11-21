@extends('ahg-theme-b5::layout')

@section('title', __('Image search'))

@section('content')
{{-- heratio#1272: search by picture or by description. Results come from ahgdiscovery.image.search. --}}
<div class="container-fluid mt-3">
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-images me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Image search') }}</h1>
      <span class="text-muted">{{ __('Find images that look like a picture you have, or describe what you are looking for') }}</span>
    </div>
  </div>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <form id="image-text-form" class="row g-2 mb-3" role="search">
        <div class="col-md-9">
          <label for="image-q" class="form-label">{{ __('Describe the picture') }}</label>
          <input type="text" id="image-q" name="q" maxlength="500" class="form-control" placeholder="{{ __('For example: a steam train crossing a bridge') }}">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">{{ __('Search') }}</button>
        </div>
      </form>
      <form id="image-file-form" class="row g-2" enctype="multipart/form-data">
        @csrf
        <div class="col-md-9">
          <label for="image-file" class="form-label">{{ __('Or upload a picture (up to 10 MB)') }}</label>
          <input type="file" id="image-file" name="image" accept="image/*" class="form-control" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-primary w-100">{{ __('Find similar images') }}</button>
        </div>
      </form>
      <p class="form-text mb-0 mt-2">{{ __('An uploaded picture is used for this search only and is not stored.') }}</p>
    </div>
  </div>

  <div id="image-status" class="text-muted mb-2" role="status" aria-live="polite"></div>
  <div id="image-results" class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-3"></div>
</div>
@endsection

@push('js')
<script>
(function () {
  var endpoint = @json(route('ahgdiscovery.image.search'));
  var status = document.getElementById('image-status');
  var grid = document.getElementById('image-results');
  var t = {
    searching: @json(__('Searching...')),
    none: @json(__('No matching images found.')),
    found: @json(__('Images found:')),
    failed: @json(__('Image search is not available right now. Please try again later.')),
    similar: @json(__('More like this'))
  };

  function show(resp) {
    return resp.json().then(function (data) {
      grid.textContent = '';
      if (!data.success) { status.textContent = data.message || t.failed; return; }
      status.textContent = data.total ? (t.found + ' ' + data.total) : t.none;
      data.results.forEach(function (r) {
        var col = document.createElement('div'); col.className = 'col';
        var card = document.createElement('div'); card.className = 'card h-100';
        if (r.thumbnail) {
          var img = document.createElement('img'); img.className = 'card-img-top'; img.loading = 'lazy';
          img.src = r.thumbnail; img.alt = r.title || ''; card.appendChild(img);
        }
        var body = document.createElement('div'); body.className = 'card-body p-2 small';
        var a = document.createElement('a'); a.href = r.url || '#'; a.textContent = r.title || ('#' + r.object_id);
        body.appendChild(a);
        var more = document.createElement('button'); more.type = 'button'; more.className = 'btn btn-link btn-sm p-0 d-block';
        more.textContent = t.similar;
        more.addEventListener('click', function () { run(fetch(endpoint + '?similar=' + r.object_id, { headers: { Accept: 'application/json' } })); });
        body.appendChild(more);
        card.appendChild(body); col.appendChild(card); grid.appendChild(col);
      });
    });
  }
  function run(request) {
    status.textContent = t.searching;
    request.then(show).catch(function () { status.textContent = t.failed; });
  }

  document.getElementById('image-text-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var q = document.getElementById('image-q').value.trim();
    if (q) { run(fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })); }
  });
  document.getElementById('image-file-form').addEventListener('submit', function (e) {
    e.preventDefault();
    run(fetch(endpoint, { method: 'POST', body: new FormData(this), headers: { Accept: 'application/json' } }));
  });
})();
</script>
@endpush
