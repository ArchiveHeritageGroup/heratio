{{-- heratio#1208 "Ask the collection": public page. A plain-language question gets a concise
     answer grounded ONLY in matching PUBLISHED catalogue records, cited [n] with links. --}}
@extends('theme::layouts.1col')
@section('title', __('Ask the collection'))

@section('content')
<div class="container py-4" style="max-width:820px">
  <header class="mb-3">
    <h1 class="mb-1"><i class="fas fa-comments me-2 text-muted"></i>{{ __('Ask the collection') }}</h1>
    <p class="text-muted mb-0">{{ __('Ask a plain-language question. The answer is drawn only from our published catalogue records, and cites them so you can read more.') }}</p>
  </header>

  <form id="askForm" method="GET" action="{{ route('ask.collection') }}" class="mb-4">
    <div class="input-group input-group-lg">
      <input type="text" name="q" id="askQ" class="form-control"
             value="{{ $question }}" maxlength="500" autocomplete="off"
             placeholder="{{ __('e.g. What do you have about the railway?') }}" aria-label="{{ __('Your question') }}">
      <button class="btn btn-primary" type="submit" id="askBtn">
        <i class="fas fa-search me-1"></i>{{ __('Ask') }}
      </button>
    </div>
  </form>

  <div id="askSpinner" class="text-center text-muted py-4 d-none">
    <div class="spinner-border" role="status"></div>
    <div class="mt-2 small">{{ __('Searching the published collection...') }}</div>
  </div>

  <div id="askResult">
    @if($result !== null)
      @if(!$result['ok'] && trim($result['answer']) === '')
        <div class="alert alert-warning">{{ __('Sorry, the assistant could not answer right now. Please try again.') }}</div>
      @else
        <div class="card mb-3">
          <div class="card-body fs-5" style="line-height:1.7">
            {{-- Linkify [n] citations to the matching source below. --}}
            {!! preg_replace('/\[(\d+)\]/', '<a href="#ask-src-$1" class="badge bg-secondary text-decoration-none">[$1]</a>', e($result['answer'])) !!}
          </div>
        </div>
        @if(!empty($result['sources']))
          <h2 class="h6 text-muted mb-2">{{ __('Records this answer draws on') }}</h2>
          <ol class="ps-3">
            @foreach($result['sources'] as $i => $s)
              <li id="ask-src-{{ $i + 1 }}" class="mb-2">
                <i class="fas fa-cube text-muted me-1"></i>
                @if(!empty($s['slug']))
                  <a href="{{ url('/'.$s['slug']) }}">{{ $s['title'] }}</a>
                @else
                  {{ $s['title'] }}
                @endif
                @if(!empty($s['scope']))
                  <div class="small text-muted">{{ $s['scope'] }}</div>
                @endif
              </li>
            @endforeach
          </ol>
        @else
          <p class="text-muted small">{{ __('No published records matched, so there is nothing in the collection to answer from yet.') }}</p>
        @endif
      @endif
    @endif
  </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var form = document.getElementById('askForm');
  var q = document.getElementById('askQ');
  var box = document.getElementById('askResult');
  var spin = document.getElementById('askSpinner');
  var btn = document.getElementById('askBtn');
  if (!form) { return; }

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function render(r) {
    if (!r) { box.innerHTML = ''; return; }
    var html = '';
    if (!r.ok && !(r.answer)) {
      html = '<div class="alert alert-warning">' + esc('Sorry, the assistant could not answer right now. Please try again.') + '</div>';
      box.innerHTML = html;
      return;
    }
    // Answer text: linkify [n] citations to the matching source below.
    var ans = esc(r.answer).replace(/\[(\d+)\]/g, function (m, n) {
      return '<a href="#ask-src-' + esc(n) + '" class="badge bg-secondary text-decoration-none">[' + esc(n) + ']</a>';
    });
    html += '<div class="card mb-3"><div class="card-body fs-5" style="line-height:1.7">' + ans + '</div></div>';
    if (r.sources && r.sources.length) {
      html += '<h2 class="h6 text-muted mb-2">' + esc('Records this answer draws on') + '</h2><ol class="ps-3">';
      r.sources.forEach(function (s, i) {
        var n = i + 1;
        var title = s.slug
          ? '<a href="/' + encodeURIComponent(s.slug) + '">' + esc(s.title) + '</a>'
          : esc(s.title);
        html += '<li id="ask-src-' + n + '" class="mb-2"><i class="fas fa-cube text-muted me-1"></i>' + title;
        if (s.scope) { html += '<div class="small text-muted">' + esc(s.scope) + '</div>'; }
        html += '</li>';
      });
      html += '</ol>';
    } else {
      html += '<p class="text-muted small">' + esc('No published records matched, so there is nothing in the collection to answer from yet.') + '</p>';
    }
    box.innerHTML = html;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var question = (q.value || '').trim();
    if (!question) { return; }
    // Reflect the question in the URL so the page is shareable / refresh-safe.
    try { history.replaceState(null, '', form.action + '?q=' + encodeURIComponent(question)); } catch (err) {}
    box.innerHTML = '';
    spin.classList.remove('d-none');
    btn.disabled = true;
    fetch('{{ route('ask.collection.answer') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({ q: question })
    })
      .then(function (res) { return res.json(); })
      .then(function (r) { render(r); })
      .catch(function () { box.innerHTML = '<div class="alert alert-danger">' + esc('Something went wrong. Please try again.') + '</div>'; })
      .finally(function () { spin.classList.add('d-none'); btn.disabled = false; });
  });
})();
</script>
@endsection
