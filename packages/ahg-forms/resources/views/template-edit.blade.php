{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+

  Wraps the render-form partial in the standard 1-col theme layout.
--}}
@extends('theme::layouts.1col')

@section('title', 'Edit ' . str_replace('_', ' ', $entityType) . ' — ' . ($template->name ?? 'Template'))

@section('content')
<div class="container my-3">
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <h2 class="h4 mb-3">
    <i class="fas fa-edit me-2"></i>
    {{ __('Template-driven edit') }}
  </h2>

  @include('ahg-forms::partials.render-form', [
    'template'   => $template,
    'entityType' => $entityType,
    'entityId'   => $entityId,
    'values'     => $values,
    'action'     => $action,
    'cancelUrl'  => $cancelUrl,
  ])
</div>
@endsection
