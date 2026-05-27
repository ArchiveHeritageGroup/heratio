{{-- Spectrum Phase C2 — cross-procedure chain rules admin --}}
@extends('theme::layouts.1col')

@section('title', __('Spectrum chain rules'))
@section('body-class', 'spectrum chain-rules')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-3 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-link me-2"></i>{{ __('Spectrum chain rules') }}
    </h1>
    <a href="{{ route('workflow.spectrum.dashboard') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Compliance dashboard') }}
    </a>
  </div>

  <p class="text-muted">
    {{ __('When a procedure completes, automatically spawn a task on a downstream procedure for the same object. Useful for sequences like Acquisition → Cataloguing → Location.') }}
  </p>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-triangle me-1"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
  @endif

  {{-- Add new rule --}}
  <div class="card mb-4">
    <div class="card-header"><strong>{{ __('Add a chain rule') }}</strong></div>
    <div class="card-body">
      <form method="POST" action="{{ route('workflow.spectrum.chain.save') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-4">
          <label for="from_procedure" class="form-label small">{{ __('When THIS completes') }}</label>
          <select name="from_procedure" id="from_procedure" class="form-select form-select-sm" required>
            @foreach($procedures as $code => $label)
              <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label for="to_procedure" class="form-label small">{{ __('Spawn a task on THIS') }}</label>
          <select name="to_procedure" id="to_procedure" class="form-select form-select-sm" required>
            @foreach($procedures as $code => $label)
              <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="trigger_event" class="form-label small">{{ __('Trigger') }}</label>
          <select name="trigger_event" id="trigger_event" class="form-select form-select-sm">
            <option value="on_complete" selected>{{ __('On complete') }}</option>
            <option value="on_approve">{{ __('On approve') }}</option>
            <option value="on_first_step">{{ __('On first step') }}</option>
          </select>
        </div>
        <div class="col-md-1">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
            <label class="form-check-label small" for="is_active">{{ __('Active') }}</label>
          </div>
        </div>
        <div class="col-md-1">
          <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Existing rules --}}
  <div class="card">
    <div class="card-header"><strong>{{ __('Existing rules') }}</strong></div>
    <div class="card-body p-0">
      @if($rules->isEmpty())
        <div class="p-3 text-muted small">{{ __('No chain rules defined yet.') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('When THIS completes') }}</th>
                <th>{{ __('Spawn a task on THIS') }}</th>
                <th>{{ __('Trigger') }}</th>
                <th>{{ __('Active') }}</th>
                <th>{{ __('Notes') }}</th>
                <th>{{ __('Action') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rules as $rule)
                <tr>
                  <td><strong>{{ $procedures[$rule->from_procedure] ?? $rule->from_procedure }}</strong></td>
                  <td>→ <strong>{{ $procedures[$rule->to_procedure] ?? $rule->to_procedure }}</strong></td>
                  <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $rule->trigger_event) }}</span></td>
                  <td>
                    @if($rule->is_active)
                      <span class="badge bg-success">{{ __('Active') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                    @endif
                  </td>
                  <td><small class="text-muted">{{ $rule->notes }}</small></td>
                  <td>
                    <form method="POST" action="{{ route('workflow.spectrum.chain.delete', $rule->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this chain rule?') }}');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
