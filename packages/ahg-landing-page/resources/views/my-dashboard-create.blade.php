{{-- Create User Dashboard - migrated from ahgLandingPagePlugin/templates/myDashboardCreateSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Create Personal Dashboard')

@section('content')
<div class="container py-4" style="max-width: 600px;">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="{{ route('landing-page.myDashboard.list') }}">My Dashboards</a>
      </li>
      <li class="breadcrumb-item active">Create New</li>
    </ol>
  </nav>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create Personal Dashboard</h5>
    </div>
    <div class="card-body">
      @if ($errors->any())
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle"></i>
          @foreach ($errors->all() as $error)
            {{ $error }}
          @endforeach
        </div>
      @endif

      @php
        $hasDashboards = isset($hasDashboards) ? $hasDashboards : false;
      @endphp

      @if (!$hasDashboards)
        <div class="alert alert-info">
          <i class="bi bi-info-circle"></i>
          This will be your first dashboard. It will be set as your default.
        </div>
      @endif

      <form method="POST" action="{{ route('landing-page.myDashboard.create') }}">
        @csrf

        <div class="mb-3">
          <label class="form-label" for="name">Dashboard Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="name" class="form-control" required
                 value="{{ old('name', 'My Dashboard') }}"
                 placeholder="{{ __('e.g., My Dashboard, Research View') }}">
          <div class="form-text">Give your dashboard a name</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="description">{{ __('Description') }}</label>
          <textarea name="description" id="description" class="form-control" rows="2"
                    placeholder="{{ __('Optional description') }}">{{ old('description', '') }}</textarea>
        </div>

        @if ($hasDashboards)
        <div class="mb-4">
          <div class="form-check">
            <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1"
                   {{ old('is_default') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_default">
              Set as my default dashboard
            </label>
          </div>
        </div>
        @else
        <input type="hidden" name="is_default" value="1">
        @endif

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> {{ __('Create Dashboard') }}
          </button>
          <a href="{{ route('landing-page.myDashboard.list') }}" class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
