@extends('theme::layouts.1col')

@section('title', 'Vendor')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.vendorBrowse') }}">{{ __('Vendor') }}</a></li>
    <li class="breadcrumb-item active">{{ e($vendor->name ?? __('View')) }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-handshake me-2"></i>{{ e($vendor->name ?? '') }}</h1>
  @auth
  <a href="{{ url()->previous() }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
  @endauth
</div>

<div class="card">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">{{ __('Name') }}</dt>
      <dd class="col-sm-9">{{ e($vendor->name ?? '-') }}</dd>
      <dt class="col-sm-3">{{ __('Description') }}</dt>
      <dd class="col-sm-9">{!! nl2br(e($vendor->description ?? '-')) !!}</dd>
      <dt class="col-sm-3">{{ __('Created') }}</dt>
      <dd class="col-sm-9">{{ $vendor->created_at ?? '-' }}</dd>
      <dt class="col-sm-3">{{ __('Updated') }}</dt>
      <dd class="col-sm-9">{{ $vendor->updated_at ?? '-' }}</dd>
    </dl>
  </div>
</div>

@endsection
