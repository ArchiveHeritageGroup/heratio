{{--
  AI Services - Suggested Connections (North Star #1210, first slice).

  "AI finds connections no human spotted." Surfaces non-obvious links between
  catalogue records - pairs that share two or more access points but are not
  directly linked - ranked by shared-signal strength, each with an on-demand
  one-paragraph LLM hypothesis (generated through the AHG gateway) of why the
  two records might be connected.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Suggested Connections')
@section('body-class', 'admin ai-services connections')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>{{ __('Suggested Connections') }}</h1>
  <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>{{ __('Back to AI Services') }}
  </a>
</div>
<p class="text-muted mb-4">
  {{ __('Generative scholarship: records that share access points (subjects, places, genres) but are NOT directly linked. The candidate pairs are computed from the catalogue graph; the AI then proposes a hypothesis - grounded only in titles and shared access points - for why each pair might be worth investigating.') }}
</p>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="get" action="{{ route('admin.ai.connections') }}" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label for="object_id" class="form-label small text-muted mb-1">{{ __('Seed record (information object id)') }}</label>
        <input id="object_id" name="object_id" type="number" min="1"
               value="{{ $objectId !== null ? $objectId : '' }}"
               placeholder="{{ __('e.g. 901851') }}" class="form-control form-control-sm">
      </div>
      <div class="col-md-2">
        <label for="min_shared" class="form-label small text-muted mb-1">{{ __('Min shared') }}</label>
        <input id="min_shared" name="min_shared" type="number" min="2" max="20"
               value="{{ $minShared }}" class="form-control form-control-sm">
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-search me-1"></i>{{ __('Find connections for record') }}
        </button>
      </div>
      <div class="col-md-3 text-md-end">
        <a href="{{ route('admin.ai.connections', ['scan' => 1, 'min_shared' => $minShared]) }}"
           class="btn btn-sm btn-outline-primary">
          <i class="bi bi-grid-3x3-gap me-1"></i>{{ __('Scan whole collection') }}
        </a>
      </div>
    </form>
  </div>
</div>

@if($seed)
  <div class="alert alert-light border d-flex align-items-center mb-4">
    <i class="bi bi-bullseye fs-4 me-3 text-primary"></i>
    <div>
      <div class="small text-muted text-uppercase">{{ __('Seed record') }}</div>
      <div class="fw-semibold">
        @if(!empty($seed['slug']))
          <a href="{{ url('/' . $seed['slug']) }}" target="_blank">{{ $seed['title'] ?: __('(untitled)') }}</a>
        @else
          {{ $seed['title'] ?: __('(untitled)') }}
        @endif
      </div>
      @if(!empty($seed['identifier']))<div class="small text-muted">{{ $seed['identifier'] }}</div>@endif
    </div>
  </div>
@endif

@if($scanned)
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <strong><i class="bi bi-lightbulb me-2"></i>{{ __('Candidate connections') }}</strong>
      <span class="badge bg-secondary ms-2">{{ count($pairs) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:42%">{{ __('Record A') }}</th>
            <th style="width:42%">{{ __('Record B') }}</th>
            <th class="text-center">{{ __('Shared') }}</th>
            <th class="text-end">{{ __('Hypothesis') }}</th>
          </tr>
        </thead>
        <tbody>
        @forelse($pairs as $p)
          @php $rowId = $p['object_id_1'] . '_' . $p['object_id_2']; @endphp
          <tr>
            <td>
              @if(!empty($p['slug_1']))
                <a href="{{ url('/' . $p['slug_1']) }}" target="_blank">{{ $p['title_1'] ?: __('(untitled)') }}</a>
              @else
                {{ $p['title_1'] ?: __('(untitled)') }}
              @endif
              <span class="text-muted small d-block">#{{ $p['object_id_1'] }}</span>
            </td>
            <td>
              @if(!empty($p['slug_2']))
                <a href="{{ url('/' . $p['slug_2']) }}" target="_blank">{{ $p['title_2'] ?: __('(untitled)') }}</a>
              @else
                {{ $p['title_2'] ?: __('(untitled)') }}
              @endif
              <span class="text-muted small d-block">#{{ $p['object_id_2'] }}</span>
            </td>
            <td class="text-center">
              <span class="badge bg-primary rounded-pill" title="{{ implode(', ', $p['shared_terms'] ?? []) }}">{{ $p['shared'] }}</span>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-success ahg-explain-btn"
                      data-o1="{{ $p['object_id_1'] }}" data-o2="{{ $p['object_id_2'] }}"
                      data-target="explain_{{ $rowId }}">
                <i class="bi bi-stars me-1"></i>{{ __('Explain') }}
              </button>
            </td>
          </tr>
          <tr class="ahg-explain-row d-none" id="explain_{{ $rowId }}_row">
            <td colspan="4" class="bg-light">
              <div class="d-flex">
                <i class="bi bi-quote fs-4 me-2 text-muted"></i>
                <div>
                  <div class="ahg-explain-body" id="explain_{{ $rowId }}"></div>
                  <div class="small text-muted ahg-explain-meta" id="explain_{{ $rowId }}_meta"></div>
                  @if(!empty($p['shared_terms']))
                    <div class="mt-2">
                      @foreach($p['shared_terms'] as $term)
                        <span class="badge bg-light text-dark border me-1 mb-1">{{ $term }}</span>
                      @endforeach
                    </div>
                  @endif
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">
            {{ __('No non-obvious connections found at this shared-signal threshold. Try lowering "Min shared".') }}
          </td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
@else
  <div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    {{ __('Enter a seed record id to find connections for one record, or scan the whole collection for the strongest non-obvious pairs.') }}
  </div>
@endif

@push('scripts')
<script>
(function () {
  const url = @json(route('admin.ai.connections.explain'));
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  document.querySelectorAll('.ahg-explain-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const target = btn.getAttribute('data-target');
      const row = document.getElementById(target + '_row');
      const body = document.getElementById(target);
      const meta = document.getElementById(target + '_meta');
      if (row) row.classList.remove('d-none');
      if (body && body.dataset.loaded === '1') return;

      btn.disabled = true;
      const original = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __('Thinking...') }}';
      if (body) body.textContent = '';
      if (meta) meta.textContent = '';

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          object_id_1: btn.getAttribute('data-o1'),
          object_id_2: btn.getAttribute('data-o2'),
        }),
      })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok && res.j.success) {
          if (body) { body.textContent = res.j.explanation; body.dataset.loaded = '1'; }
          if (meta) {
            meta.textContent = (res.j.cached ? '{{ __('Cached') }} - ' : '{{ __('Generated') }} - ')
              + ({{ __("'model: '") }}) + (res.j.model || '?');
          }
        } else if (body) {
          body.innerHTML = '<span class="text-danger">' + (res.j.error || '{{ __('Could not generate explanation.') }}') + '</span>';
        }
      })
      .catch(function () {
        if (body) body.innerHTML = '<span class="text-danger">{{ __('Request failed.') }}</span>';
      })
      .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
      });
    });
  });
})();
</script>
@endpush
@endsection
