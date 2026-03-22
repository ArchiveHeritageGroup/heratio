@extends('theme::layouts.1col')

@section('title', 'My Instances')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Instances') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-server me-2"></i>{{ __('My Instances') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('My Instances content will be displayed here.') }}</p>
  </div>
</div>

@endsection
