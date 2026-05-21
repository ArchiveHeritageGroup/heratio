@extends('theme::layouts.1col')
@section('title', 'Generate Summary — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-file-alt',
    'featureTitle' => 'Generate Summary',
    'featureDescription' => 'AI-generated summary of the digitised document (PDF), falling back to the catalogue description',
  ])

  @if(isset($io->scope_and_content) && $io->scope_and_content)
    <div class="card mb-3">
      <div class="card-header fw-bold">Current description</div>
      <div class="card-body">
        <p>{{ $io->scope_and_content }}</p>
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header fw-bold">Generated summary</div>
    <div class="card-body" id="summary-result">
      <p class="text-muted">Click the button below to generate a summary from the document.</p>
    </div>
  </div>

  <button class="btn atom-btn-outline-success" id="generate-summary-btn" data-object-id="{{ $io->id }}">
    <i class="fas fa-magic me-1"></i> {{ __('Generate summary from document') }}
  </button>
@endsection

@push('js')
<script>
document.getElementById('generate-summary-btn')?.addEventListener('click', function () {
    const btn = this;
    const id  = btn.dataset.objectId;
    const out = document.getElementById('summary-result');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating from document…';
    out.innerHTML = '<p class="text-muted">Working… this can take a moment for a large document.</p>';

    fetch('/admin/ai/summarize/' + encodeURIComponent(id), {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = original;
            if (!data.success) {
                out.innerHTML = '<p class="text-danger mb-0">' + (data.error || 'Summarization failed') + '</p>';
                return;
            }
            const src = data.source === 'pdf' ? 'document (PDF)' : 'catalogue metadata';
            const summary = (data.summary || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
            out.innerHTML =
                '<p style="white-space:pre-wrap">' + summary + '</p>' +
                '<p class="text-muted small mb-0">Source: ' + src +
                ' · ' + (data.processing_time_ms || 0) + ' ms' +
                (data.saved ? ' · saved to description' : '') + '</p>';
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = original;
            out.innerHTML = '<p class="text-danger mb-0">' + err.message + '</p>';
        });
});
</script>
@endpush
