@extends('theme::layouts.1col')
@section('title', 'PII Scan Results — ' . ($io->title ?? ''))

@section('content')
<div class="container py-4">

  {{-- Flash Messages --}}
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
  @endif

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">
        <i class="fas fa-user-shield me-2"></i>PII Scan Results
      </h4>
      <p class="text-muted mb-0">
        Personally Identifiable Information detected in
        <strong>{{ $io->title ?? 'Untitled' }}</strong>
      </p>
    </div>
    <div class="btn-group">
      <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
      </a>
      <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn atom-btn-white">
        <i class="fas fa-eye me-1"></i> {{ __('View Record') }}
      </a>
    </div>
  </div>

  @php
    // Scan result data — default to empty if not provided
    $scanResult = $scanResult ?? null;
    $entities = $scanResult->entities ?? [];
    $riskScore = $scanResult->risk_score ?? 0;
    $fieldsSanned = $scanResult->fields_scanned ?? [];
    $totalEntities = is_countable($entities) ? count($entities) : 0;

    $highRisk = 0;
    $mediumRisk = 0;
    $lowRisk = 0;
    $byType = [];

    if ($totalEntities > 0) {
      foreach ($entities as $entity) {
        $risk = $entity->risk ?? $entity['risk'] ?? 'low';
        $type = $entity->type ?? $entity['type'] ?? 'UNKNOWN';
        if ($risk === 'high') $highRisk++;
        elseif ($risk === 'medium') $mediumRisk++;
        else $lowRisk++;
        if (!isset($byType[$type])) $byType[$type] = 0;
        $byType[$type]++;
      }
    }

    // Risk score color
    if ($riskScore >= 70) {
      $riskColor = 'danger';
      $riskLabel = 'High Risk';
    } elseif ($riskScore >= 40) {
      $riskColor = 'warning';
      $riskLabel = 'Medium Risk';
    } else {
      $riskColor = 'success';
      $riskLabel = 'Low Risk';
    }

    // Badge color mapping for PII types
    $typeColors = [
      'SA_ID'       => 'danger',
      'PASSPORT'    => 'danger',
      'EMAIL'       => 'warning',
      'PHONE'       => 'warning',
      'ADDRESS'     => 'info',
      'NAME'        => 'primary',
      'DOB'         => 'warning',
      'BANK'        => 'danger',
      'TAX'         => 'danger',
      'MEDICAL'     => 'danger',
      'BIOMETRIC'   => 'danger',
      'IP_ADDRESS'  => 'info',
      'UNKNOWN'     => 'secondary',
    ];
  @endphp

  {{-- Summary Cards Row --}}
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-primary">{{ $totalEntities }}</div>
          <small class="text-muted">{{ __('Total Entities') }}</small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #dc3545 !important;">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-danger">{{ $highRisk }}</div>
          <small class="text-muted">{{ __('High Risk') }}</small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #ffc107 !important;">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-warning">{{ $mediumRisk }}</div>
          <small class="text-muted">{{ __('Medium Risk') }}</small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #198754 !important;">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-success">{{ $lowRisk }}</div>
          <small class="text-muted">{{ __('Low Risk') }}</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Risk Score Card --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><i class="fas fa-tachometer-alt me-1"></i> Risk Score</h6>
        <span class="badge bg-{{ $riskColor }} fs-6">{{ $riskScore }}/100 &mdash; {{ $riskLabel }}</span>
      </div>
      <div class="progress" style="height: 12px;">
        <div class="progress-bar bg-{{ $riskColor }}"
             role="progressbar"
             style="width: {{ $riskScore }}%"
             aria-valuenow="{{ $riskScore }}"
             aria-valuemin="0"
             aria-valuemax="100"></div>
      </div>
    </div>
  </div>

  @if($totalEntities > 0)
    {{-- Two-Column Layout --}}
    <div class="row g-4">

      {{-- Left Column: Detected Entities Table --}}
      <div class="col-md-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-list me-1"></i> Detected Entities
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Value') }}</th>
                    <th>{{ __('Confidence') }}</th>
                    <th>{{ __('Risk') }}</th>
                    <th>{{ __('Source') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($entities as $entity)
                    @php
                      $eType = $entity->type ?? $entity['type'] ?? 'UNKNOWN';
                      $eValue = $entity->value ?? $entity['value'] ?? '';
                      $eConfidence = $entity->confidence ?? $entity['confidence'] ?? 0;
                      $eRisk = $entity->risk ?? $entity['risk'] ?? 'low';
                      $eSource = $entity->source ?? $entity['source'] ?? '';

                      $badgeColor = $typeColors[$eType] ?? 'secondary';

                      if ($eRisk === 'high') {
                        $riskBadgeColor = 'danger';
                      } elseif ($eRisk === 'medium') {
                        $riskBadgeColor = 'warning';
                      } else {
                        $riskBadgeColor = 'success';
                      }
                    @endphp
                    <tr>
                      <td>
                        <span class="badge bg-{{ $badgeColor }}">{{ $eType }}</span>
                      </td>
                      <td>
                        <code>{{ $eValue }}</code>
                      </td>
                      <td>
                        {{ number_format($eConfidence * 100, 1) }}%
                      </td>
                      <td>
                        <span class="badge bg-{{ $riskBadgeColor }}">{{ ucfirst($eRisk) }}</span>
                      </td>
                      <td>
                        <small class="text-muted">{{ $eSource }}</small>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {{-- Right Column: Save & Summary --}}
      <div class="col-md-4">

        {{-- Save Results Card --}}
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-white fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-save me-1"></i> Save Results
          </div>
          <div class="card-body">
            <p class="text-muted small">Save this scan result to the record for future reference and compliance tracking.</p>
            <form method="POST" action="#">
              @csrf
              <input type="hidden" name="information_object_id" value="{{ $io->id ?? '' }}">
              <button type="submit" class="btn atom-btn-outline-success w-100" id="save-scan-btn">
                <i class="fas fa-save me-1"></i> {{ __('Save Scan Results') }}
              </button>
            </form>
          </div>
        </div>

        {{-- By Type Summary --}}
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-white fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-chart-pie me-1"></i> By Type
          </div>
          <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0">
              <thead>
                <tr>
                  <th>{{ __('Type') }}</th>
                  <th class="text-end">{{ __('Count') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($byType as $typeName => $typeCount)
                  <tr>
                    <td>
                      <span class="badge bg-{{ $typeColors[$typeName] ?? 'secondary' }}">{{ $typeName }}</span>
                    </td>
                    <td class="text-end">{{ $typeCount }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        {{-- Fields Scanned --}}
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-white fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-search me-1"></i> Fields Scanned
          </div>
          <div class="card-body">
            @if(!empty($fieldsSanned))
              <ul class="list-unstyled mb-0">
                @foreach($fieldsSanned as $field)
                  <li class="mb-1">
                    <i class="fas fa-check-circle text-success me-1"></i>
                    <small>{{ $field }}</small>
                  </li>
                @endforeach
              </ul>
            @else
              <p class="text-muted small mb-0">No field information available.</p>
            @endif
          </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-bolt me-1"></i> Quick Actions
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="{{ route('io.privacy.dashboard') }}" class="btn atom-btn-white btn-sm">
                <i class="fas fa-clipboard-list me-1"></i> {{ __('Review Queue') }}
              </a>
              <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) . '/privacy/redaction' : '#' }}" class="btn atom-btn-white btn-sm">
                <i class="fas fa-eraser me-1"></i> {{ __('Visual Redaction') }}
              </a>
              <a href="{{ route('io.privacy.scan', $io->id) }}" class="btn atom-btn-white btn-sm">
                <i class="fas fa-search-plus me-1"></i> {{ __('Scan More') }}
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>

  @else
    {{-- Empty State --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="mb-3">
          <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
        </div>
        <h5 class="text-success">{{ __('No PII Detected') }}</h5>
        <p class="text-muted mb-3">
          No personally identifiable information was found in this record.
        </p>
        <div class="btn-group">
          <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn atom-btn-white">
            <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Record') }}
          </a>
          <a href="{{ route('io.privacy.scan', $io->id) }}" class="btn atom-btn-white">
            <i class="fas fa-redo me-1"></i> {{ __('Scan Again') }}
          </a>
        </div>
      </div>
    </div>
  @endif

</div>
@endsection
