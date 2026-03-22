@extends('theme::layouts.1col')

@section('title', 'My Institution')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Institution') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-university me-2"></i>{{ __('My Institution') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('My Institution content will be displayed here.') }}</p>
  </div>
</div>

@endsection
