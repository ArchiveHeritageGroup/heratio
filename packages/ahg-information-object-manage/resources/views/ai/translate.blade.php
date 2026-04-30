@extends('theme::layouts.1col')
@section('title', 'Translate — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-language',
    'featureTitle' => 'Translate',
    'featureDescription' => 'Translate description text to another language',
  ])

  @if(isset($io->scope_and_content) && $io->scope_and_content)
    <div class="card mb-3">
      <div class="card-header fw-bold">Source text</div>
      <div class="card-body">
        <p>{{ $io->scope_and_content }}</p>
      </div>
    </div>
  @endif

  <div class="mb-3">
    <label class="form-label" for="target-language">Target language <span class="badge bg-secondary ms-1">Optional</span></label>
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
      <p class="text-muted">Click the button below to translate.</p>
    </div>
  </div>

  <button class="btn atom-btn-outline-success" id="translate-btn" data-object-id="{{ $io->id }}">
    <i class="fas fa-language me-1"></i> Translate
  </button>
@endsection
