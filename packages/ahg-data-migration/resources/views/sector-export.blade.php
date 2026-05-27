{{--
    Sector export view - Heratio data-migration

    Copyright (C) 2026 Johan Pieterse
    Plain Sailing Information Systems
    Email: johan@plainsailingisystems.co.za

    Issue #740 - Data-migration exports parity.
    PSIS twin: atom-ahg-plugins#86.
--}}
@extends('theme::layouts.1col')
@section('title', __('Sector export'))
@section('body-class', 'edit data-migration sector-export')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-export me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Sector export') }}</h1>
      <small class="text-muted">{{ __('Per-sector CSV using a sector-specific column resolver.') }}</small>
    </div>
  </div>

  @if (session('error'))
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}</div>
  @endif

  <div class="card mb-3">
    <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-cog me-2"></i>{{ __('Sector + format') }}
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('data-migration.sector-export-new') }}">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">{{ __('Sector') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label>
            <select class="form-select" name="sector">
              @foreach (($sectors ?? []) as $code => $label)
                <option value="{{ $code }}" @selected(($sector ?? '') === $code)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">{{ __('Format') }}</label>
            <select class="form-select" name="format">
              <option value="csv">CSV</option>
            </select>
            <small class="text-muted d-block mt-1">{{ __('XML / EAD has its own dedicated action.') }}</small>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn atom-btn-white">
            <i class="fas fa-download me-1"></i> {{ __('Generate CSV') }}
          </button>
          <a href="{{ route('data-migration.index') }}" class="btn atom-btn-white">
            <i class="fas fa-times me-1"></i> {{ __('Cancel') }}
          </a>
        </div>
      </form>
    </div>
  </div>

  @if (! empty($columns))
    <div class="card">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-table me-2"></i>{{ __('Resolved columns for') }} <code class="text-white">{{ $sector }}</code>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead>
              <tr>
                <th style="width:30%">{{ __('Source key') }}</th>
                <th>{{ __('Output label') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($columns as $key => $label)
                <tr>
                  <td><code>{{ $key }}</code></td>
                  <td>{{ $label }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
@endsection
