@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="fas fa-user-shield me-2"></i>{{ __('PII Scan Results') }}</h1>
            <p class="text-muted mb-0">{{ $object->title ?? 'Untitled' }}</p>
        </div>
        <div>
            <a href="{{ route('ahgprivacy.pii-scan') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Scanner') }}
            </a>
            @php
              $slug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', $object->id ?? 0)->value('slug');
            @endphp
            @if($slug)
              <a href="{{ url('/' . $slug) }}" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-external-link-alt me-1"></i>{{ __('View Record') }}
              </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card {{ ($scanResult['summary']['total'] ?? 0) > 0 ? 'bg-warning text-dark' : 'bg-success text-white' }}">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ $scanResult['summary']['total'] ?? 0 }}</h2>
                    <p class="mb-0">Total Entities</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ ($scanResult['summary']['high_risk'] ?? 0) > 0 ? 'bg-danger text-white' : 'bg-light' }}">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ $scanResult['summary']['high_risk'] ?? 0 }}</h2>
                    <p class="mb-0">High Risk</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ $scanResult['summary']['medium_risk'] ?? 0 }}</h2>
                    <p class="mb-0">Medium Risk</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ $scanResult['summary']['low_risk'] ?? 0 }}</h2>
                    <p class="mb-0">Low Risk</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Risk Score -->
    @if(isset($scanResult['risk_score']))
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">{{ __('Overall Risk Score') }}</h5>
                <span class="badge @php
if ($scanResult['risk_score'] >= 70) echo 'bg-danger';
                    elseif ($scanResult['risk_score'] >= 40) echo 'bg-warning text-dark';
                    else echo 'bg-success';
@endphp fs-5">{{ $scanResult['risk_score'] }}/100</span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar @php
if ($scanResult['risk_score'] >= 70) echo 'bg-danger';
                    elseif ($scanResult['risk_score'] >= 40) echo 'bg-warning';
                    else echo 'bg-success';
@endphp" role="progressbar" style="width: {{ $scanResult['risk_score'] }}%"></div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <!-- Entities Found -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>{{ __('Detected Entities') }}</h5>
                    @if(!empty($scanResult['entities']))
                    <span class="badge bg-light text-dark">{{ count($scanResult['entities']) }} found</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if(empty($scanResult['entities']))
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                            <p class="mb-0">No PII detected in this record</p>
                        </div>
                    @else
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Value') }}</th>
                                    <th class="text-center">{{ __('Confidence') }}</th>
                                    <th class="text-center">{{ __('Risk') }}</th>
                                    <th>{{ __('Source') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($scanResult['entities'] as $entity)
                                <tr>
                                    <td>
                                        @php
$typeColors = [
                                            'SA_ID' => 'bg-danger',
                                            'NG_NIN' => 'bg-danger',
                                            'PASSPORT' => 'bg-danger',
                                            'BANK_ACCOUNT' => 'bg-danger',
                                            'TAX_NUMBER' => 'bg-danger',
                                            'PERSON' => 'bg-warning text-dark',
                                            'EMAIL' => 'bg-warning text-dark',
                                            'PHONE_SA' => 'bg-warning text-dark',
                                            'PHONE_INTL' => 'bg-warning text-dark',
                                            'ORG' => 'bg-secondary',
                                            'GPE' => 'bg-secondary',
                                            'DATE' => 'bg-light text-dark',
                                        ];
                                        $color = $typeColors[$entity['type']] ?? 'bg-primary';
@endphp
                                        <span class="badge {{ $color }}">{{ $entity['type'] }}</span>
                                    </td>
                                    <td>
                                        <code>{{ $entity['value'] }}</code>
                                    </td>
                                    <td class="text-center">
                                        @php
$conf = round(($entity['confidence'] ?? 0) * 100);
                                        $confClass = $conf >= 80 ? 'text-success' : ($conf >= 50 ? 'text-warning' : 'text-muted');
@endphp
                                        <span class="{{ $confClass }}">{{ $conf }}%</span>
                                    </td>
                                    <td class="text-center">
                                        @php
$risk = $entity['risk_level'] ?? 'low';
                                        $riskColors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'];
@endphp
                                        <span class="badge bg-{{ $riskColors[$risk] ?? 'secondary' }}">{{ ucfirst($risk) }}</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $entity['source'] ?? 'text' }}</small>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions & Summary by Type -->
        <div class="col-md-4">
            <!-- Save Results -->
            @if(!empty($scanResult['entities']))
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-save me-2"></i>{{ __('Save Results') }}</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Save detected PII to the review queue for manual verification and redaction decisions.</p>
                    <form method="post" action="{{ route('ahgprivacy.pii-scan-object', ['id' => $object->id]) }}">
                        <input type="hidden" name="save" value="1">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save me-1"></i>{{ __('Save to Review Queue') }}
                        </button>
                    </form>
                </div>
            </div>
            @endif

            <!-- Summary by Type -->
            @if(!empty($scanResult['summary']['by_type']))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('By Type') }}</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach($scanResult['summary']['by_type'] as $type => $count)
                            <tr>
                                <td>{{ $type }}</td>
                                <td class="text-end"><strong>{{ $count }}</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Scanned Fields -->
            @if(!empty($scanResult['fields_scanned']))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Fields Scanned') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($scanResult['fields_scanned'] as $field)
                        <li><i class="fas fa-check text-success me-1"></i>{{ $field }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Actions') }}</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('ahgprivacy.pii-review') }}" class="btn btn-outline-warning w-100 mb-2">
                        <i class="fas fa-tasks me-1"></i>{{ __('Review Queue') }}
                    </a>
                    <a href="{{ route('ahgprivacy.visual-redaction-editor', ['id' => $object->id]) }}" class="btn btn-outline-danger w-100 mb-2">
                        <i class="fas fa-eraser me-1"></i>{{ __('Visual Redaction Editor') }}
                    </a>
                    <a href="{{ route('ahgprivacy.pii-scan') }}" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-search me-1"></i>{{ __('Scan More Objects') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
