{{--
  Compliance — regulatory compliance settings (Heratio extra)

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Compliance')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-clipboard-check me-2"></i>{{ __('Compliance') }}</h1>
<p class="text-muted">Regulatory compliance settings</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.compliance') }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Regulatory Compliance') }}</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Configure regulatory compliance features. For detailed compliance management (DSARs, breaches, ROPA), see the @if(\Illuminate\Support\Facades\Route::has('privacy.dashboard'))<a href="{{ route('privacy.dashboard') }}">Privacy Compliance module</a>@else Privacy Compliance module @endif.</p>

        @php
          // Hide internal {audit_last_pruned_*} stamps from the form — they're
          // derived state written by the pruner, not user-editable.
          $internal = ['audit_last_pruned_at', 'audit_last_pruned_rows'];
          $lastPrunedAt = $settings['audit_last_pruned_at'] ?? null;
          $lastPrunedRows = $settings['audit_last_pruned_rows'] ?? null;
        @endphp
        @if(array_key_exists('audit_retention_days', $settings))
          <div class="alert alert-info small d-flex justify-content-between align-items-center mb-3">
            <div>
              <i class="fas fa-clock me-1"></i>
              Audit log pruner runs daily at 03:30. 0 disables pruning.
              @if($lastPrunedAt)
                <br><span class="text-muted">Last run: {{ $lastPrunedAt }} — {{ (int) $lastPrunedRows }} row(s) removed.</span>
              @else
                <br><span class="text-muted">Has not run yet.</span>
              @endif
            </div>
            @if(\Illuminate\Support\Facades\Route::has('audit.prune'))
              <form method="POST" action="{{ route('audit.prune') }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary"
                        onclick="return confirm('Run the audit-log pruner now? This deletes rows older than the configured retention from every audit table.')">
                  <i class="fas fa-broom me-1"></i> {{ __('Run prune now') }}
                </button>
              </form>
            @endif
          </div>
        @endif

        @forelse($settings as $key => $val)
          @if(in_array($key, $internal, true))
            @continue
          @endif
          @php
            $label = ucfirst(str_replace('_', ' ', preg_replace('/^compliance_/', '', $key)));
            $isCheckbox = in_array($val, ['true','false','1','0']);
          @endphp
          @if($isCheckbox)
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="{{ $key }}"
                     name="settings[{{ $key }}]" value="true"
                     {{ in_array($val, ['true','1']) ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}"><strong>{{ $label }}</strong></label>
            </div>
          @elseif(is_numeric($val) && $val !== '')
            <div class="mb-3">
              <label for="{{ $key }}" class="form-label"><strong>{{ $label }}</strong></label>
              <input type="number" class="form-control" id="{{ $key }}"
                     name="settings[{{ $key }}]" value="{{ e($val) }}" style="max-width:300px">
            </div>
          @else
            <div class="mb-3">
              <label for="{{ $key }}" class="form-label"><strong>{{ $label }}</strong></label>
              <input type="text" class="form-control" id="{{ $key }}"
                     name="settings[{{ $key }}]" value="{{ e($val) }}">
            </div>
          @endif
        @empty
          <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle me-1"></i>No compliance settings configured yet. Settings will appear here once they are added to the <code>ahg_settings</code> table with group <code>compliance</code>.
          </div>
        @endforelse
      </div>
    </div>

    @if(!empty($settings))
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
    @else
    <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
    </a>
    @endif
  </form>
@endsection
