@extends('theme::layouts.1col')
@section('title', 'Translate — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-language',
    'featureTitle' => 'Translate',
    'featureDescription' => 'Translate the digitised document (PDF) text, falling back to the catalogue description',
  ])

  @if(isset($io->scope_and_content) && $io->scope_and_content)
    <div class="card mb-3">
      <div class="card-header fw-bold">Catalogue description</div>
      <div class="card-body">
        <p>{{ $io->scope_and_content }}</p>
      </div>
    </div>
  @endif

  <div class="mb-3">
    <label class="form-label" for="target-language">{{ __('Target language') }}</label>
    <select class="form-select" id="target-language" style="max-width: 300px;">
      <option value="af">{{ __('Afrikaans') }}</option>
      <option value="en" selected>{{ __('English') }}</option>
      <option value="fr">{{ __('French') }}</option>
      <option value="de">{{ __('German') }}</option>
      <option value="nl">{{ __('Dutch') }}</option>
      <option value="pt">{{ __('Portuguese') }}</option>
      <option value="es">{{ __('Spanish') }}</option>
      <option value="zu">{{ __('Zulu') }}</option>
      <option value="xh">{{ __('Xhosa') }}</option>
    </select>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-bold">Translation</div>
    <div class="card-body" id="translation-result">
      <p class="text-muted">Choose a language and click the button below to translate the document.</p>
    </div>
  </div>

  <button class="btn atom-btn-outline-success" id="translate-btn" data-object-id="{{ $io->id }}">
    <i class="fas fa-language me-1"></i> {{ __('Translate document') }}
  </button>
@endsection

@push('js')
<script nonce="{{ csp_nonce() }}">
document.getElementById('translate-btn')?.addEventListener('click', function () {
    const btn = this;
    const id  = btn.dataset.objectId;
    const out = document.getElementById('translation-result');
    const tgt = document.getElementById('target-language').value;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Translating…';
    out.innerHTML = '<p class="text-muted">Working… this can take a moment for a large document.</p>';

    fetch('/admin/ai/translate/' + encodeURIComponent(id) + '?target_lang=' + encodeURIComponent(tgt), {
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
                out.innerHTML = '<p class="text-danger mb-0">' + (data.error || 'Translation failed') + '</p>';
                return;
            }
            const src = data.document_source === 'pdf' ? 'document (PDF)' : 'catalogue description';
            const translation = (data.translation || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
            out.innerHTML =
                '<p style="white-space:pre-wrap">' + translation + '</p>' +
                '<p class="text-muted small mb-0">Source: ' + src + '</p>';
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = original;
            out.innerHTML = '<p class="text-danger mb-0">' + err.message + '</p>';
        });
});
</script>
@endpush
