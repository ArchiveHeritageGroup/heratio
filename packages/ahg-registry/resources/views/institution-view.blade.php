@extends('theme::layouts.1col')

@section('title', 'Institution')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.institutionBrowse') }}">{{ __('Institution') }}</a></li>
    <li class="breadcrumb-item active">{{ e($institution->name ?? __('View')) }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-university me-2"></i>{{ e($institution->name ?? '') }}</h1>
  @auth
  <a href="{{ url()->previous() }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
  @endauth
</div>

<div class="card">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">{{ __('Name') }}</dt>
      <dd class="col-sm-9">{{ e($institution->name ?? '-') }}</dd>
      <dt class="col-sm-3">{{ __('Description') }}</dt>
      <dd class="col-sm-9">{!! nl2br(e($institution->description ?? '-')) !!}</dd>
      <dt class="col-sm-3">{{ __('Created') }}</dt>
      <dd class="col-sm-9">{{ $institution->created_at ?? '-' }}</dd>
      <dt class="col-sm-3">{{ __('Updated') }}</dt>
      <dd class="col-sm-9">{{ $institution->updated_at ?? '-' }}</dd>
    </dl>
  </div>
</div>

@endsection
