@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-clipboard-check me-2"></i>{{ __('PII Review Queue') }}</h1>
        <a href="{{ route('ahgprivacy.pii-scan') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Scanner') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Pending Review
                <span class="badge bg-warning text-dark ms-2">{{ count($entities) }}</span>
            </h5>
        </div>
        <div class="card-body p-0">
            @if(empty($entities) || count($entities) === 0)
                <div class="text-center text-muted py-5">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                    <p>No pending PII entities to review</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">{{ __('Status') }}</th>
                            <th style="width: 120px;">{{ __('Type') }}</th>
                            <th>{{ __('Value') }}</th>
                            <th>{{ __('Object') }}</th>
                            <th class="text-center" style="width: 100px;">{{ __('Confidence') }}</th>
                            <th style="width: 200px;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entities as $entity)
                        <tr id="entity-row-{{ $entity->id }}">
                            <td>
                                @if($entity->status === 'flagged')
                                    <span class="badge bg-danger"><i class="fas fa-flag me-1"></i>{{ __('Flagged') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                @php
$typeBadges = [
                                    'PERSON' => 'bg-info',
                                    'SA_ID' => 'bg-danger',
                                    'NG_NIN' => 'bg-danger',
                                    'PASSPORT' => 'bg-danger',
                                    'EMAIL' => 'bg-warning text-dark',
                                    'PHONE_SA' => 'bg-warning text-dark',
                                    'BANK_ACCOUNT' => 'bg-danger',
                                    'ORG' => 'bg-secondary',
                                    'GPE' => 'bg-secondary',
                                    // ISAD Access Point types
                                    'ISAD_SUBJECT' => 'bg-purple text-white',
                                    'ISAD_PLACE' => 'bg-teal text-white',
                                    'ISAD_NAME' => 'bg-indigo text-white',
                                    'ISAD_DATE' => 'bg-cyan text-dark',
                                ];
                                $badge = $typeBadges[$entity->entity_type] ?? 'bg-primary';
                                $isIsad = strpos($entity->entity_type, 'ISAD_') === 0;
                                $displayType = $isIsad ? str_replace('ISAD_', '', $entity->entity_type) : $entity->entity_type;
@endphp
                                <span class="badge {{ $badge }}" style="{{ $isIsad ? 'background-color: #6f42c1 !important;' : '' }}">
                                    @if($isIsad)<i class="fas fa-tag me-1"></i>@endif
                                    {{ $displayType }}
                                </span>
                                @if($isIsad)
                                <br><small class="text-muted">{{ __('ISAD Access Point') }}</small>
                                @endif
                            </td>
                            <td>
                                <code>{{ $entity->entity_value }}</code>
                            </td>
                            <td>
                                <a href="/index.php/{{ $entity->object_slug }}" target="_blank">
                                    {{ substr($entity->object_title ?? 'Untitled', 0, 50) }}
                                    @if(strlen($entity->object_title ?? '') > 50)...@endif
                                </a>
                            </td>
                            <td class="text-center">
                                @php
$conf = round($entity->confidence * 100);
                                $confClass = $conf >= 80 ? 'text-success' : ($conf >= 60 ? 'text-warning' : 'text-danger');
@endphp
                                <span class="{{ $confClass }}">{{ $conf }}%</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form action="{{ route('ahgprivacy.pii-entity-action') }}" method="post" class="d-inline">
                                        <input type="hidden" name="entity_id" value="{{ $entity->id }}">
                                        <div class="btn-group btn-group-sm">
                                            <button type="submit" name="entity_action" value="approved" class="btn btn-outline-success" title="{{ __('Approve - Not PII') }}">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="submit" name="entity_action" value="redacted" class="btn btn-outline-warning" title="{{ __('Redact - Is PII') }}">
                                                <i class="fas fa-eraser"></i>
                                            </button>
                                            <button type="submit" name="entity_action" value="rejected" class="btn btn-outline-danger" title="{{ __('Reject - False Positive') }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </form>
                                    <a href="{{ route('ahgprivacy.visual-redaction-editor', ['id' => $entity->object_id]) }}"
                                       class="btn btn-sm btn-outline-dark" title="{{ __('Visual Redaction Editor') }}">
                                        <i class="fas fa-mask"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <!-- Legend -->
    <div class="card mt-4">
        <div class="card-body">
            <h6><i class="fas fa-info-circle me-2"></i>{{ __('Review Actions') }}</h6>
            <div class="row">
                <div class="col-md-4">
                    <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                    <strong>{{ __('Approve') }}</strong> - Not sensitive PII, can remain visible
                </div>
                <div class="col-md-4">
                    <span class="badge bg-warning text-dark me-2"><i class="fas fa-eraser"></i></span>
                    <strong>{{ __('Redact') }}</strong> - Is PII, should be masked/restricted in metadata and PDFs
                </div>
                <div class="col-md-4">
                    <span class="badge bg-danger me-2"><i class="fas fa-times"></i></span>
                    <strong>{{ __('Reject') }}</strong> - False positive, not actually PII
                </div>
            </div>
        </div>
    </div>

    <!-- Entity Sources Legend -->
    <div class="card mt-3">
        <div class="card-body">
            <h6><i class="fas fa-tags me-2"></i>{{ __('Entity Sources') }}</h6>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2"><strong>{{ __('Extracted Entities (NER/Regex)') }}</strong></p>
                    <span class="badge bg-info me-1">{{ __('PERSON') }}</span>
                    <span class="badge bg-secondary me-1">{{ __('ORG') }}</span>
                    <span class="badge bg-secondary me-1">{{ __('GPE') }}</span>
                    <span class="badge bg-danger me-1">{{ __('SA_ID') }}</span>
                    <span class="badge bg-warning text-dark me-1">{{ __('EMAIL') }}</span>
                    <small class="d-block text-muted mt-1">{{ __('Extracted from metadata text fields and PDF content') }}</small>
                </div>
                <div class="col-md-6">
                    <p class="mb-2"><strong>{{ __('ISAD Access Points') }}</strong></p>
                    <span class="badge me-1" style="background-color: #6f42c1;"><i class="fas fa-tag me-1"></i>{{ __('SUBJECT') }}</span>
                    <span class="badge me-1" style="background-color: #6f42c1;"><i class="fas fa-tag me-1"></i>{{ __('PLACE') }}</span>
                    <span class="badge me-1" style="background-color: #6f42c1;"><i class="fas fa-tag me-1"></i>{{ __('NAME') }}</span>
                    <span class="badge me-1" style="background-color: #6f42c1;"><i class="fas fa-tag me-1"></i>{{ __('DATE') }}</span>
                    <small class="d-block text-muted mt-1">{{ __('From Subject, Place, Name, and Date access point fields') }}</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
