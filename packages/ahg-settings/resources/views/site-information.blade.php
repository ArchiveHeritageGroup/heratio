@extends('theme::layouts.1col')
@section('title', 'Site Information')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-info-circle me-2"></i>Site Information</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="post" action="{{ route('settings.site-information') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header">Site information settings</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Site title</label>
            <input type="text" name="siteTitle" class="form-control" value="{{ e($settings['siteTitle']) }}">
            <small class="text-muted">The name of the website for display in the header</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Site description</label>
            <textarea name="siteDescription" class="form-control" rows="3">{{ e($settings['siteDescription']) }}</textarea>
            <small class="text-muted">A brief site description or tagline for the header</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Site base URL</label>
            <input type="url" name="siteBaseUrl" class="form-control" value="{{ e($settings['siteBaseUrl']) }}">
            <small class="text-muted">Used to create absolute URLs in XML exports (MODS, EAD)</small>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
