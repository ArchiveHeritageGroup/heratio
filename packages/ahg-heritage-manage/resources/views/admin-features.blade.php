@extends('theme::layouts.1col')
@section('title', 'Feature Toggles')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-toggle-on me-2"></i>{{ __('Feature Toggles') }}</h1>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Platform Features') }}</h5></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Feature') }}</th>
                <th>{{ __('Code') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
                <th class="text-center">{{ __('Action') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($features ?? [] as $feature)
              <tr>
                <td>
                  <strong>{{ $feature->feature_name ?? $feature->feature_code }}</strong>
                  @if(!empty($feature->config_json))
                  <br><small class="text-muted">{{ __('Has configuration') }}</small>
                  @endif
                </td>
                <td><code>{{ $feature->feature_code }}</code></td>
                <td class="text-center">
                  @if($feature->is_enabled)
                  <span class="badge bg-success">{{ __('Enabled') }}</span>
                  @else
                  <span class="badge bg-secondary">{{ __('Disabled') }}</span>
                  @endif
                </td>
                <td class="text-center">
                  <form method="post" class="d-inline" action="{{ route('heritage.admin-features') }}">
                    @csrf
                    <input type="hidden" name="feature_code" value="{{ $feature->feature_code }}">
                    <input type="hidden" name="toggle_action" value="toggle">
                    <button type="submit" class="btn btn-sm btn-outline-{{ $feature->is_enabled ? 'secondary' : 'success' }}">
                      {{ $feature->is_enabled ? 'Disable' : 'Enable' }}
                    </button>
                  </form>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">No features configured.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="alert alert-info mt-4">
      <i class="fas fa-info-circle me-2"></i>
      {{ __('Feature toggles control platform functionality. Disabled features will not be available to users.') }}
    </div>
  </div>
</div>
@endsection
