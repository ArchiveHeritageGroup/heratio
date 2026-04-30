@extends('theme::layouts.1col')
@section('title', 'Annotation Studio — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-highlighter',
    'featureTitle' => 'Annotation Studio',
    'featureDescription' => 'W3C Web Annotations for this archival description',
  ])

  <div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i> {{ __('No annotations recorded for this description.') }}
  </div>

  <button class="btn atom-btn-outline-success" onclick="alert('Annotation creation form — migration in progress'); return false;">
    <i class="fas fa-plus me-1"></i> {{ __('Add annotation') }}
  </button>
@endsection
