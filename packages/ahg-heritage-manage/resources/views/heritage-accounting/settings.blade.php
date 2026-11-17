@extends('theme::layouts.1col')
@section('title', __('Heritage Accounting Settings'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-cog me-2"></i>{{ __('Heritage Accounting Settings') }}</h1>
      <a href="{{ route('heritage.hadmin.standard-list') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit me-1"></i>{{ __('Manage Standards') }}</a>
    </div>

    <form method="post" action="{{ route('heritage.accounting.settings.save') }}">
      @csrf
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-balance-scale me-2"></i>{{ __('Accounting Standards') }}</div>
        <div class="card-body">
          <p class="text-muted mb-4">{{ __('Enable or disable accounting standards for your organization. At least one standard should be enabled.') }}</p>
          <div class="row">
            @forelse($standards as $std)
              <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 {{ $std->is_active ? 'border-success' : 'border-secondary' }}">
                  <div class="card-body">
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="enabled[]" value="{{ $std->id }}" id="std_{{ $std->id }}" {{ $std->is_active ? 'checked' : '' }}>
                      <label class="form-check-label" for="std_{{ $std->id }}"><strong>{{ $std->code }}</strong></label>
                    </div>
                    <p class="mb-1 mt-2">{{ $std->name }}</p>
                    <small class="text-muted"><i class="fas fa-globe me-1"></i>{{ $std->country }}</small>
                    <div class="mt-2">
                      @if($std->capitalisation_required)
                        <span class="badge bg-warning text-dark">{{ __('Capitalisation Required') }}</span>
                      @else
                        <span class="badge bg-info">{{ __('Capitalisation Optional') }}</span>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            @empty
              <div class="col-12 text-muted">{{ __('No accounting standards installed. Install a region under Heritage Admin > Regions.') }}</div>
            @endforelse
          </div>
          <div class="alert alert-info mt-3 mb-0">
            <i class="fas fa-info-circle me-2"></i>
            <strong>{{ __('Note:') }}</strong> {{ __('Required means capitalisation is mandatory under that accounting standard. Optional means capitalisation is at the organisation\'s discretion. The checkboxes control which standards are available for use in your system.') }}
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>{{ __('Save Settings') }}</button>
          <a href="{{ route('heritage.accounting.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
