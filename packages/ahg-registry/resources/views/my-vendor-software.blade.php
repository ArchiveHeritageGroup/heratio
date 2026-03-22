@extends('theme::layouts.1col')

@section('title', 'My Software')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Software') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-laptop-code me-2"></i>{{ __('My Software') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('My Software content will be displayed here.') }}</p>
  </div>
</div>

@endsection
