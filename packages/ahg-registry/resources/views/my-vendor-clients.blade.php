@extends('theme::layouts.1col')

@section('title', 'My Clients')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Clients') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-users me-2"></i>{{ __('My Clients') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('My Clients content will be displayed here.') }}</p>
  </div>
</div>

@endsection
