{{--
  Heratio - Annex IV technical-documentation index.
  Copyright (c) 2026 Plain Sailing Information Systems.
  Johan Pieterse <johan@plainsailingisystems.co.za>. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')
@section('title', 'Annex IV Technical Documentation')
@section('body-class', 'admin ai-compliance')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0"><i class="fas fa-file-contract me-2"></i>{{ __('Annex IV Technical Documentation') }}</h1>
      <a href="{{ route('ai-compliance.models.index') }}" class="btn atom-btn-white"><i class="fas fa-microchip me-1"></i>{{ __('Model registry') }}</a>
    </div>

    @if(session('success'))
      <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif
    @if(session('console'))
      <details class="mb-3">
        <summary class="text-muted">{{ __('Last command output') }}</summary>
        <pre class="bg-light p-2 small">{{ session('console') }}</pre>
      </details>
    @endif

    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>
      {{ __('Each per-service bundle includes the EU Declaration of Conformity (Annex V) and the full Annex IV technical documentation. Bundles are retained for at least 10 years (Article 11(3)). Storage location:') }}
      <code>{{ $storeDir }}</code>
    </div>

    <form method="post" action="{{ route('ai-compliance.documentation.generate') }}" class="card mb-4">
      @csrf
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-cogs me-2"></i>{{ __('Generate now') }}
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <label class="form-label">{{ __('Service') }}</label>
            <select name="service" class="form-select">
              <option value="">{{ __('All services') }}</option>
              @foreach($services as $svc)
                <option value="{{ $svc }}">{{ $svc }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-8 d-flex align-items-end">
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-play me-1"></i>{{ __('Generate bundle(s)') }}</button>
            <span class="ms-3 text-muted small">{{ __('Runs') }} <code>php artisan ai-compliance:annex-iv</code></span>
          </div>
        </div>
      </div>
    </form>

    @foreach($services as $svc)
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-folder me-2"></i><strong>{{ $svc }}</strong></span>
          <span class="badge bg-secondary">{{ count($byService[$svc] ?? []) }} {{ __('bundle(s)') }}</span>
        </div>
        <div class="card-body p-0">
          @if(empty($byService[$svc]))
            <div class="p-3 text-muted">{{ __('No bundles generated yet for this service.') }}</div>
          @else
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>{{ __('Filename') }}</th>
                  <th>{{ __('Date') }}</th>
                  <th>{{ __('Size') }}</th>
                  <th>{{ __('Modified') }}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach($byService[$svc] as $row)
                  <tr>
                    <td><code>{{ $row['name'] }}</code></td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ number_format($row['size_bytes'] / 1024, 1) }} KB</td>
                    <td>{{ $row['modified_iso'] }}</td>
                    <td>
                      <a href="{{ route('ai-compliance.documentation.show', $row['name']) }}" class="btn btn-sm atom-btn-white" target="_blank"><i class="fas fa-download me-1"></i>{{ __('Open') }}</a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
