@extends('theme::layouts.1col')
@section('title', 'Visual Redaction — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-eraser',
    'featureTitle' => 'Visual Redaction',
    'featureDescription' => 'Redact sensitive information from digital object images',
  ])

  @if($digitalObject)
    <div class="card mb-3">
      <div class="card-body text-center">
        @php $url = \AhgCore\Services\DigitalObjectService::getUrl($digitalObject); @endphp
        <img src="{{ $url }}" alt="{{ $io->title }}" class="img-fluid" style="max-height:600px;">
      </div>
    </div>
    <p class="text-muted">Visual redaction tool — select areas on the image above to redact.</p>
  @else
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No digital object available for redaction.
    </div>
  @endif
@endsection
