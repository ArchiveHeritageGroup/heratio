@extends('theme::layouts.1col')

@section('title', 'Search')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Search') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-search me-2"></i>{{ __('Search') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('Search content will be displayed here.') }}</p>
  </div>
</div>

@endsection
