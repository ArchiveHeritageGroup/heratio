@extends('theme::layouts.1col')

@section('title', 'Admin: Dashboard')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Registry Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Dashboard') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-tachometer-alt me-2"></i>{{ __('Dashboard') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('Admin: Dashboard management.') }}</p>
  </div>
</div>

@endsection
