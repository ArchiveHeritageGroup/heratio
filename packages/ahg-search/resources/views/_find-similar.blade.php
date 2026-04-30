{{--
  Find-similar widget — calls /api/search/semantic/similar/{ioId} via fetch
  and renders top-N nearest neighbours by cosine similarity.

  Usage: @include('ahg-search::_find-similar', ['ioId' => $io->id])

  Gracefully no-ops when the AI / Qdrant stack is unreachable (panel renders
  with a "service unavailable" hint).

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@php
  $ioId = (int) ($ioId ?? 0);
@endphp

@if($ioId > 0)
<section class="card mt-3" id="ahg-find-similar">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="fas fa-magnifying-glass me-1"></i> {{ __('Find Similar Records') }}</span>
    <button type="button" id="ahg-find-similar-trigger" class="btn btn-sm btn-outline-primary">
      <i class="fas fa-bolt"></i> {{ __('Run') }}
    </button>
  </div>
  <div class="card-body small" id="ahg-find-similar-body">
    <p class="text-muted mb-0">Click <em>Run</em> to fetch records semantically similar to this one (powered by Qdrant + sentence embeddings).</p>
  </div>
</section>

<script>
(function () {
  const ioId = {{ $ioId }};
  const trigger = document.getElementById('ahg-find-similar-trigger');
  const body = document.getElementById('ahg-find-similar-body');
  if (! trigger || ! body) return;

  function escape(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }

  trigger.addEventListener('click', async function () {
    trigger.disabled = true;
    body.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i> Searching…</div>';
    try {
      const r = await fetch('/api/search/semantic/similar/' + ioId + '?limit=10', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if (r.status === 503) {
        const data = await r.json().catch(() => ({}));
        body.innerHTML = '<div class="alert alert-warning small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Service degraded: ' + escape(data.reason || 'unavailable') + '</div>';
        return;
      }
      if (! r.ok) {
        body.innerHTML = '<div class="alert alert-danger small mb-0">Search failed (' + r.status + ').</div>';
        return;
      }
      const data = await r.json();
      if (! data.ok || ! Array.isArray(data.hits) || data.hits.length === 0) {
        body.innerHTML = '<div class="text-muted mb-0">No similar records found.</div>';
        return;
      }
      const rows = data.hits.map(h => {
        const slug = h.slug ? '<a href="/' + escape(h.slug) + '">' + escape(h.title || '[Untitled]') + '</a>'
                            : escape(h.title || ('IO #' + h.id));
        const score = h.score !== undefined ? '<span class="badge bg-secondary ms-1">' + h.score.toFixed(3) + '</span>' : '';
        return '<li class="list-group-item d-flex justify-content-between align-items-center small py-1">' + slug + score + '</li>';
      }).join('');
      body.innerHTML = '<ul class="list-group list-group-flush mb-0">' + rows + '</ul>';
    } catch (e) {
      body.innerHTML = '<div class="alert alert-danger small mb-0">Network error: ' + escape(e.message) + '</div>';
    } finally {
      trigger.disabled = false;
    }
  });
})();
</script>
</section>
@endif
