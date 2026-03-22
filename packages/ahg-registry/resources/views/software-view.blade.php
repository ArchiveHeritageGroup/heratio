@extends('theme::layouts.1col')

@section('title', 'Software')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.softwareBrowse') }}">{{ __('Software') }}</a></li>
    <li class="breadcrumb-item active">{{ e($software->name ?? __('View')) }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-laptop-code me-2"></i>{{ e($software->name ?? '') }}</h1>
  @auth
  <a href="{{ url()->previous() }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
  @endauth
</div>

<div class="card">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">{{ __('Name') }}</dt>
      <dd class="col-sm-9">{{ e($software->name ?? '-') }}</dd>
      <dt class="col-sm-3">{{ __('Description') }}</dt>
      <dd class="col-sm-9">{!! nl2br(e($software->description ?? '-')) !!}</dd>
      <dt class="col-sm-3">{{ __('Created') }}</dt>
      <dd class="col-sm-9">{{ $software->created_at ?? '-' }}</dd>
      <dt class="col-sm-3">{{ __('Updated') }}</dt>
      <dd class="col-sm-9">{{ $software->updated_at ?? '-' }}</dd>
    </dl>
  </div>
</div>

@endsection
