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
    <label class="form-label" for="target-language">Target language</label>
    <select class="form-select" id="target-language" style="max-width: 300px;">
      <option value="af">Afrikaans</option>
      <option value="en" selected>English</option>
      <option value="fr">French</option>
      <option value="de">German</option>
      <option value="nl">Dutch</option>
      <option value="pt">Portuguese</option>
      <option value="es">Spanish</option>
      <option value="zu">Zulu</option>
      <option value="xh">Xhosa</option>
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
