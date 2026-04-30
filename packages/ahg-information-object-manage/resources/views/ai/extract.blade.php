@extends('theme::layouts.1col')

@section('title', 'Extract Entities (NER) — ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container py-4">

  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-brain',
    'featureTitle' => 'Extract Entities (NER)',
    'featureDescription' => 'Named Entity Recognition — extract persons, organizations, places, dates',
  ])

  {{-- IO Title Header --}}
  <h5 class="mb-3">
    <a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? 'Untitled' }}</a>
  </h5>

  {{-- Extract Button --}}
  <div class="ner-extract-section mb-3">
    <button type="button" class="btn atom-btn-white w-100" id="nerExtractBtn" onclick="extractEntities({{ $io->id }})">
      <i class="bi bi-cpu me-1"></i>Extract Entities (NER)
    </button>

    {{-- Results Container (hidden by default) --}}
    <div id="nerResults" class="mt-3" style="display: none;">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <span><i class="bi bi-list-check me-1"></i>Extracted Entities</span>
          <button class="btn btn-sm atom-btn-outline-success" onclick="approveAll()">{{ __('Approve All') }}</button>
        </div>
        <div class="card-body" id="nerResultsBody"></div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('js')
<script>
function extractEntities(objectId) {
    const btn = document.getElementById('nerExtractBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Extracting...';

    fetch(`/admin/ai/ner/extract/${objectId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
        }
    })
        .then(r => {
            if (!r.ok) throw new Error('Server returned HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cpu me-1"></i>Extract Entities (NER)';

            if (!data.success) {
                alert('Error: ' + (data.error || 'Extraction failed'));
                return;
            }

            displayResults(data.entities, data.entity_count, data.processing_time_ms);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cpu me-1"></i>Extract Entities (NER)';
            alert('Error: ' + err.message);
        });
}

function displayResults(entities, count, time) {
    const container = document.getElementById('nerResults');
    const body = document.getElementById('nerResultsBody');

    if (count === 0) {
        body.innerHTML = '<div class="text-muted text-center">No entities found</div>';
        container.style.display = 'block';
        return;
    }

    let html = `<p class="text-muted small">Found ${count} entities in ${time}ms</p>`;

    const icons = { PERSON: 'bi-person', ORG: 'bi-building', GPE: 'bi-geo-alt', DATE: 'bi-calendar' };
    const colors = { PERSON: 'primary', ORG: 'success', GPE: 'info', DATE: 'warning' };

    for (const [type, items] of Object.entries(entities)) {
        if (!items.length) continue;

        html += `<div class="mb-2"><strong><i class="${icons[type]} me-1"></i>${type}</strong><br>`;
        html += items.map(i => `<span class="badge bg-${colors[type]} me-1 mb-1">${i}</span>`).join('');
        html += '</div>';
    }

    html += '<hr><a href="{{ route('io.ai.review') }}" class="btn atom-btn-outline-light btn-sm">Review & Link Entities</a>';

    body.innerHTML = html;
    container.style.display = 'block';
}

function approveAll() {
    alert('Approve All — will be processed on the Review Dashboard');
    window.location.href = '{{ route('io.ai.review') }}';
}
</script>
@endpush
