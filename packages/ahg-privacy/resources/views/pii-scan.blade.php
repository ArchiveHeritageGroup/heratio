@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-user-shield me-2"></i>PII Detection Scanner</h1>
        <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ number_format($stats['total_scanned']) }}</h2>
                    <p class="mb-0">Objects Scanned</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ number_format($stats['with_pii']) }}</h2>
                    <p class="mb-0">With PII Detected</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ number_format($stats['high_risk_entities']) }}</h2>
                    <p class="mb-0">High-Risk Entities</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 class="display-5">{{ number_format($stats['coverage_percent'], 1) }}%</h2>
                    <p class="mb-0">Coverage</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Scan Controls -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Run PII Scan</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('ahgprivacy.pii-scan-run') }}" method="post">
                        <div class="mb-3">
                            <label class="form-label">Repository (optional)</label>
                            <select name="repository_id" class="form-select">
                                <option value="">All repositories</option>
                                @foreach($repositories as $repo)
                                    <option value="{{ $repo->id }}">{{ $repo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Batch Size</label>
                            <select name="limit" class="form-select">
                                <option value="25">25 objects</option>
                                <option value="50" selected>50 objects</option>
                                <option value="100">100 objects</option>
                                <option value="250">250 objects</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-play me-1"></i>Start Scan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Entities by Type -->
            @if(!empty($stats['by_type']))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Entities by Type</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach($stats['by_type'] as $type => $count)
                            <tr>
                                <td>
                                    @php
$badges = [
                                        'PERSON' => 'bg-info',
                                        'SA_ID' => 'bg-danger',
                                        'EMAIL' => 'bg-warning text-dark',
                                        'PHONE_SA' => 'bg-warning text-dark',
                                        'ORG' => 'bg-secondary',
                                        'GPE' => 'bg-secondary',
                                        'DATE' => 'bg-light text-dark',
                                    ];
                                    $badge = $badges[$type] ?? 'bg-primary';
@endphp
                                    <span class="badge {{ $badge }}">{{ $type }}</span>
                                </td>
                                <td class="text-end">{{ number_format($count) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('ahgprivacy.pii-review') }}" class="btn btn-outline-warning w-100 mb-2">
                        <i class="fas fa-tasks me-1"></i>Review Pending ({{ $stats['pending_review'] }})
                    </a>
                    <a href="{{ route('ahgprivacy.ropa-list') }}" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-database me-1"></i>Data Inventory
                    </a>
                </div>
            </div>
        </div>

        <!-- High-Risk Objects -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>High-Risk Objects</h5>
                </div>
                <div class="card-body p-0">
                    @if(empty($highRiskObjects) || count($highRiskObjects) === 0)
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                            <p>No high-risk PII detected</p>
                        </div>
                    @else
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Object</th>
                                    <th class="text-center" style="width: 100px;">PII Count</th>
                                    <th style="width: 150px;">Scanned</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($highRiskObjects as $obj)
                                <tr>
                                    <td>
                                        <a href="{{ url('/' . ($obj->slug ?? 'informationobject/' . ($obj->object_id ?? ''))) }}" target="_blank">
                                            {{ $obj->title ?? 'Untitled' }}
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">{{ $obj->entity_count }}</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ date('Y-m-d H:i', strtotime($obj->extracted_at)) }}</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('ahgprivacy.pii-scan-object', ['id' => $obj->object_id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PII Types Legend -->
<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>PII Types Detected</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>High Risk</h6>
                    <ul class="list-unstyled small">
                        <li><strong>SA_ID</strong> - South African ID Numbers</li>
                        <li><strong>NG_NIN</strong> - Nigerian National ID</li>
                        <li><strong>PASSPORT</strong> - Passport Numbers</li>
                        <li><strong>BANK_ACCOUNT</strong> - Bank Account Numbers</li>
                        <li><strong>TAX_NUMBER</strong> - Tax Reference Numbers</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Medium Risk</h6>
                    <ul class="list-unstyled small">
                        <li><strong>PERSON</strong> - Names (via NER)</li>
                        <li><strong>EMAIL</strong> - Email Addresses</li>
                        <li><strong>PHONE_SA</strong> - SA Phone Numbers</li>
                        <li><strong>PHONE_INTL</strong> - International Phones</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-secondary"><i class="fas fa-info-circle me-1"></i>Low Risk</h6>
                    <ul class="list-unstyled small">
                        <li><strong>ORG</strong> - Organizations (via NER)</li>
                        <li><strong>GPE</strong> - Places (via NER)</li>
                        <li><strong>DATE</strong> - Dates (via NER)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
