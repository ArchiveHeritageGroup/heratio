{{--
  heratio#1208 "Ask the collection": public, collection-wide chat. A plain-language
  question gets an answer grounded in this institution's own corpus (its catalogue +
  knowledge base) via the KM RAG service, with cited sources, and an honest "I don't
  have enough in the collection to answer that" when the corpus does not cover it.
  International / jurisdiction-neutral copy. Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Ask the collection'))

@section('content')
<div class="container py-4" style="max-width:820px">
  <header class="mb-3">
    <h1 class="mb-1"><i class="fas fa-comments me-2 text-muted"></i>{{ __('Ask the collection') }}</h1>
    <p class="text-muted mb-0">{{ __('Ask a plain-language question about this collection and get an answer drawn from our own catalogue and knowledge base.') }}</p>
  </header>

  <div class="alert alert-info d-flex align-items-start" role="note">
    <i class="fas fa-info-circle me-2 mt-1"></i>
    <div>{{ __('Answers are drawn from this collection\'s catalogue and knowledge base. It will tell you plainly when it does not have enough to answer, and it will not invent facts. Cited sources are listed beneath each answer.') }}</div>
  </div>

  <form id="askForm" method="GET" action="{{ route('ask.collection') }}" class="mb-4">
    <div class="input-group input-group-lg">
      <input type="text" name="q" id="askQ" class="form-control"
             value="{{ $question }}" maxlength="500" autocomplete="off"
             placeholder="{{ __('e.g. What does the collection hold about local trade?') }}" aria-label="{{ __('Your question') }}">
      <button class="btn btn-primary" type="submit" id="askBtn">
        <i class="fas fa-search me-1"></i>{{ __('Ask') }}
      </button>
    </div>
  </form>

  <div id="askSpinner" class="text-center text-muted py-4 d-none">
    <div class="spinner-border" role="status"></div>
    <div class="mt-2 small">{{ __('Searching the collection...') }}</div>
  </div>

  <div id="askResult">
    @if($result !== null)
      @php
        $grounded = ! empty($result['grounded']);
        $answer = trim((string) ($result['answer'] ?? ''));
        $sources = $result['sources'] ?? [];
      @endphp
      <div class="card mb-3 {{ $grounded ? '' : 'border-warning' }}">
        <div class="card-body">
          @unless($grounded)
            <div class="d-flex align-items-center text-warning small mb-2">
              <i class="fas fa-exclamation-triangle me-1"></i>{{ __('Not confidently answered from the collection') }}
            </div>
          @endunless
          <div class="fs-5" style="line-height:1.7">{{ $answer }}</div>
        </div>
      </div>
      @if(!empty($sources))
        <h2 class="h6 text-muted mb-2">{{ __('Sources') }}</h2>
        <ol class="ps-3">
          @foreach($sources as $s)
            <li class="mb-2">
              <i class="fas fa-book text-muted me-1"></i>
              @if(!empty($s['url']))
                <a href="{{ $s['url'] }}" rel="noopener noreferrer">{{ $s['title'] }}</a>
              @else
                {{ $s['title'] }}
              @endif
            </li>
          @endforeach
        </ol>
      @elseif($grounded)
        <p class="text-muted small">{{ __('No specific sources were returned for this answer.') }}</p>
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
    var grounded = !!r.grounded;
    var answer = (r.answer == null ? '' : String(r.answer)).trim();
    var sources = r.sources || [];
    var html = '';

    html += '<div class="card mb-3' + (grounded ? '' : ' border-warning') + '"><div class="card-body">';
    if (!grounded) {
      html += '<div class="d-flex align-items-center text-warning small mb-2"><i class="fas fa-exclamation-triangle me-1"></i>'
        + esc('{{ __('Not confidently answered from the collection') }}') + '</div>';
    }
    html += '<div class="fs-5" style="line-height:1.7">' + esc(answer) + '</div></div></div>';

    if (sources.length) {
      html += '<h2 class="h6 text-muted mb-2">' + esc('{{ __('Sources') }}') + '</h2><ol class="ps-3">';
      sources.forEach(function (s) {
        var title = s.url
          ? '<a href="' + esc(s.url) + '" rel="noopener noreferrer">' + esc(s.title) + '</a>'
          : esc(s.title);
        html += '<li class="mb-2"><i class="fas fa-book text-muted me-1"></i>' + title + '</li>';
      });
      html += '</ol>';
    } else if (grounded) {
      html += '<p class="text-muted small">' + esc('{{ __('No specific sources were returned for this answer.') }}') + '</p>';
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
    fetch('{{ route('ask.collection.ask') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({ q: question })
    })
      .then(function (res) { return res.json(); })
      .then(function (r) { render(r); })
      .catch(function () { box.innerHTML = '<div class="alert alert-danger">' + esc('{{ __('Something went wrong. Please try again.') }}') + '</div>'; })
      .finally(function () { spin.classList.add('d-none'); btn.disabled = false; });
  });
})();
</script>
@endsection
