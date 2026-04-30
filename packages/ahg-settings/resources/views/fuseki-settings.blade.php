{{--
  Fuseki / RIC Triplestore — connection and sync settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('fuseki')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Fuseki / RIC Triplestore')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-project-diagram me-2"></i>{{ __('Fuseki / RIC Triplestore') }}</h1>
<p class="text-muted">Apache Fuseki RDF triplestore synchronisation</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.fuseki') }}">
    @csrf

    {{-- Card 1: Fuseki Connection --}}
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-server me-2"></i>{{ __('Fuseki Connection') }}</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label for="fuseki_endpoint" class="form-label fw-bold">{{ __('Fuseki SPARQL Endpoint') }}</label>
            <input type="url" class="form-control" id="fuseki_endpoint" name="fuseki_endpoint"
                   value="{{ $settings['fuseki_endpoint'] ?? config('ric.fuseki_endpoint', 'http://localhost:3030/ric') }}"
                   placeholder="{{ __('http://localhost:3030/ric') }}">
            <div class="form-text">Full URL to Fuseki SPARQL endpoint (e.g., http://localhost:3030/ric)</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-outline-secondary d-block w-100" id="test-fuseki-btn" disabled>
              <i class="fas fa-plug me-1"></i>{{ __('Test Connection') }}
            </button>
          </div>
          <div class="col-md-6">
            <label for="fuseki_username" class="form-label fw-bold">{{ __('Username') }}</label>
            <input type="text" class="form-control" id="fuseki_username" name="fuseki_username"
                   value="{{ $settings['fuseki_username'] ?? 'admin' }}">
          </div>
          <div class="col-md-6">
            <label for="fuseki_password" class="form-label fw-bold">{{ __('Password') }}</label>
            <input type="password" class="form-control" id="fuseki_password" name="fuseki_password"
                   value="{{ $settings['fuseki_password'] ?? '' }}" placeholder="{{ __('Leave blank to keep current') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: RIC Sync Settings --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sync-alt me-2"></i>{{ __('RIC Sync Settings') }}</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="fuseki_sync_enabled"
                     name="fuseki_sync_enabled" value="1"
                     {{ ($settings['fuseki_sync_enabled'] ?? '1') === '1' || ($settings['fuseki_sync_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="fuseki_sync_enabled">
                <strong>{{ __('Enable Automatic Sync') }}</strong>
              </label>
            </div>
            <div class="form-text">Master switch for all RIC sync operations</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="fuseki_queue_enabled"
                     name="fuseki_queue_enabled" value="1"
                     {{ ($settings['fuseki_queue_enabled'] ?? '1') === '1' || ($settings['fuseki_queue_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="fuseki_queue_enabled">
                <strong>{{ __('Use Async Queue') }}</strong>
              </label>
            </div>
            <div class="form-text">Queue sync operations for background processing</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="fuseki_sync_on_save"
                     name="fuseki_sync_on_save" value="1"
                     {{ ($settings['fuseki_sync_on_save'] ?? '1') === '1' || ($settings['fuseki_sync_on_save'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="fuseki_sync_on_save">{{ __('Sync on Record Save') }}</label>
            </div>
            <div class="form-text">Automatically sync to Fuseki when records are created/updated</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="fuseki_sync_on_delete"
                     name="fuseki_sync_on_delete" value="1"
                     {{ ($settings['fuseki_sync_on_delete'] ?? '1') === '1' || ($settings['fuseki_sync_on_delete'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="fuseki_sync_on_delete">{{ __('Sync on Record Delete') }}</label>
            </div>
            <div class="form-text">Remove from Fuseki when records are deleted</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="fuseki_cascade_delete"
                     name="fuseki_cascade_delete" value="1"
                     {{ ($settings['fuseki_cascade_delete'] ?? '1') === '1' || ($settings['fuseki_cascade_delete'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="fuseki_cascade_delete">{{ __('Cascade Delete References') }}</label>
            </div>
            <div class="form-text">Also remove triples where deleted record is the object</div>
          </div>
          <div class="col-md-6">
            <label for="fuseki_batch_size" class="form-label fw-bold">{{ __('Batch Size') }}</label>
            <input type="number" class="form-control" id="fuseki_batch_size" name="fuseki_batch_size"
                   value="{{ $settings['fuseki_batch_size'] ?? '100' }}" min="10" max="1000" step="10">
            <div class="form-text">Records per batch for bulk sync operations</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 3: Integrity Check Settings --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-check-double me-2"></i>{{ __('Integrity Check Settings') }}</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="fuseki_integrity_schedule" class="form-label fw-bold">{{ __('Check Schedule') }}</label>
            <select class="form-select" id="fuseki_integrity_schedule" name="fuseki_integrity_schedule">
              @php $curSched = $settings['fuseki_integrity_schedule'] ?? 'weekly'; @endphp
              <option value="daily" {{ $curSched === 'daily' ? 'selected' : '' }}>{{ __('Daily') }}</option>
              <option value="weekly" {{ $curSched === 'weekly' ? 'selected' : '' }}>{{ __('Weekly') }}</option>
              <option value="monthly" {{ $curSched === 'monthly' ? 'selected' : '' }}>{{ __('Monthly') }}</option>
              <option value="disabled" {{ $curSched === 'disabled' ? 'selected' : '' }}>{{ __('Disabled') }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="fuseki_orphan_retention_days" class="form-label fw-bold">{{ __('Orphan Retention (days)') }}</label>
            <input type="number" class="form-control" id="fuseki_orphan_retention_days" name="fuseki_orphan_retention_days"
                   value="{{ $settings['fuseki_orphan_retention_days'] ?? '30' }}" min="1" max="365">
            <div class="form-text">Days to retain orphaned triples before cleanup</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 4: Quick Actions --}}
    <div class="card mb-4">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          @if(\Route::has('ric.index'))
            <a href="{{ route('ric.index') }}" class="btn btn-outline-primary">
              <i class="fas fa-tachometer-alt me-1"></i>{{ __('RIC Dashboard') }}
            </a>
          @endif
          <a href="https://www.ica.org/standards/RiC/ontology" target="_blank" class="btn btn-outline-info">
            <i class="fas fa-book me-1"></i>{{ __('RiC-O Reference') }}
          </a>
          @php
            $fusekiAdmin = preg_replace('#/[^/]+$#', '/', $settings['fuseki_endpoint'] ?? config('ric.fuseki_endpoint', 'http://localhost:3030/ric'));
          @endphp
          <a href="{{ e($fusekiAdmin) }}" target="_blank" class="btn btn-outline-secondary">
            <i class="fas fa-database me-1"></i>{{ __('Fuseki Admin') }}
          </a>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
