@extends('theme::layouts.1col')

@section('title', 'My Vendor Dashboard')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Vendor Dashboard') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-truck me-2"></i>{{ __('My Vendor Dashboard') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('My Vendor Dashboard content will be displayed here.') }}</p>
  </div>
</div>

@endsection
