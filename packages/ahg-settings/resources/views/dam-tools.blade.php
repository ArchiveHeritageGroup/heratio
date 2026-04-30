@extends('theme::layouts.2col')
@section('title', 'Digital Asset Management Tools')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('content')
<div class="mb-3">
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to AHG Settings') }}</a>
    </div>

    <h1><i class="fas fa-photo-video text-info"></i> Digital Asset Management Tools</h1>
    <p class="text-muted">Tools for managing digital assets, images, and documents</p>

    @include('ahg-settings::_tiff-pdf-merge-settings')

    <div class="row mt-4">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center">
            <i class="fas fa-images fa-3x text-success mb-3"></i>
            <h5>{{ __('Digital Objects') }}</h5>
            <p class="text-muted small">Browse and manage all digital objects in the system.</p>
            <a href="{{ url('/digitalobject/browse') }}" class="btn atom-btn-outline-success"><i class="fas fa-search me-1"></i>{{ __('Browse') }}</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center">
            <i class="fas fa-tasks fa-3x text-info mb-3"></i>
            <h5>{{ __('Background Jobs') }}</h5>
            <p class="text-muted small">View status of all processing jobs.</p>
            <a href="{{ url('/admin/jobs') }}" class="btn atom-btn-outline-info"><i class="fas fa-list me-1"></i>{{ __('View Jobs') }}</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body text-center">
            <i class="fas fa-cube fa-3x text-warning mb-3"></i>
            <h5>{{ __('3D Objects') }}</h5>
            <p class="text-muted small">Manage 3D models and viewer settings.</p>
            <a href="#" class="btn atom-btn-outline-warning"><i class="fas fa-cog me-1"></i>{{ __('Settings') }}</a>
          </div>
        </div>
      </div>
    </div>
@endsection
