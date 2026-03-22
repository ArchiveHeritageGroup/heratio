@extends('theme::layouts.1col')

@section('title', 'Client Form')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Client Form') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-user-plus me-2"></i>{{ __('Client Form') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('Client Form content will be displayed here.') }}</p>
  </div>
</div>

@endsection
