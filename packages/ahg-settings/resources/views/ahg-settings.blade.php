{{-- AHG Settings landing page — redirects to settings index --}}
@extends('theme::layouts.1col')
@section('title', 'AHG Plugin Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="mb-0"><i class="fas fa-cogs"></i> AHG Plugin Settings</h1>
  <a href="{{ route('settings.global') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Admin Settings</a>
</div>
<p class="text-muted mb-4">Configure AHG theme and plugin settings</p>

<div class="row">
  @foreach($scopeCards ?? [] as $card)
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.section', $card->key) }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas {{ $card->icon ?? 'fa-cog' }} fa-3x text-primary"></i></div>
            <h5 class="card-title text-dark">{{ $card->label }}</h5>
            <p class="card-text text-muted small">{{ $card->description ?? '' }}</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn atom-btn-white"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>
  @endforeach
</div>

<style>
  .settings-tile { transition: transform 0.15s ease, box-shadow 0.15s ease; cursor: pointer; }
  .settings-tile:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important; }
</style>
@endsection
