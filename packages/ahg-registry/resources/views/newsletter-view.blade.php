@extends('theme::layouts.1col')

@section('title', 'Newsletter')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Newsletter') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-newspaper me-2"></i>{{ __('Newsletter') }}</h1>

<div class="card">
  <div class="card-body">
    <p class="text-muted mb-0">{{ __('Newsletter content will be displayed here.') }}</p>
  </div>
</div>

@endsection
