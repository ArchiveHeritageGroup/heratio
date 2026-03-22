@extends('theme::layouts.1col')

@section('title', 'Admin: Software')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Registry Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Software') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-laptop-code me-2"></i>{{ __('Software') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('Admin: Software management.') }}</p>
  </div>
</div>

@endsection
