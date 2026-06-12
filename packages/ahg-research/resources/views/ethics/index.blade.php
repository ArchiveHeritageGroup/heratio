{{-- Research Ethics & Consent register - per-project list + summary (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Research Ethics & Consent'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Research Ethics & Consent') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-shield-alt text-primary me-2"></i>{{ __('Research Ethics & Consent') }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.ethics.create', $project->id ?? 0) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('New Record') }}</a>
        @if(($summary['total'] ?? 0) > 0)
            <a href="{{ route('research.ethics.export', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}</a>
        @endif
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('The register of this project\'s ethics approvals and the consent basis for its human-subject and sensitive data. Record each approval (committee, reference number, decision and expiry dates, status), the consent basis on which the data is held, and its sensitivity classification. These are generic governance concepts and apply to any jurisdiction.') }}</p>

{{-- Per-project summary --}}
@if(($summary['total'] ?? 0) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('Summary') }}</h6>
        <span class="badge bg-primary rounded-pill">{{ $summary['total'] }} {{ __('total') }}</span>
    </div>
    <div class="card-body">
        @if(($summary['expired'] ?? 0) > 0 || ($summary['expiring_soon'] ?? 0) > 0)
        <div class="alert alert-warning d-flex align-items-center mb-3 py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div class="small">
                @if(($summary['expired'] ?? 0) > 0)<strong>{{ $summary['expired'] }}</strong> {{ trans_choice('approval has|approvals have', $summary['expired']) }} {{ __('expired.') }} @endif
                @if(($summary['expiring_soon'] ?? 0) > 0)<strong>{{ $summary['expiring_soon'] }}</strong> {{ trans_choice('approval is|approvals are', $summary['expiring_soon']) }} {{ __('expiring soon.') }}@endif
            </div>
        </div>
        @endif
        <div class="row">
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By status') }}</div>
                @foreach($summary['by_status'] as $s)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($s['label']) }} <span class="badge bg-secondary ms-1">{{ $s['count'] }}</span></span>
                @endforeach
            </div>
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By approval type') }}</div>
                @foreach($summary['by_type'] as $t)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($t['label']) }} <span class="badge bg-secondary ms-1">{{ $t['count'] }}</span></span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Record list --}}
@if(empty($records))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No ethics records yet. Record the ethics approvals and consent basis for this project\'s human-subject, animal, data-protection or biosafety work.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Consent basis') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Expiry') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
            @php
                $st = $r['status'] ?? 'pending';
                $badge = match($st) {
                    'approved' => 'success', 'conditions' => 'info', 'pending' => 'warning',
                    'rejected' => 'danger', 'expired' => 'dark', default => 'secondary',
                };
                $flag = $flags[$r['id']] ?? null;
            @endphp
            <tr>
                <td>
                    <a href="{{ route('research.ethics.show', [$project->id ?? 0, $r['id']]) }}">{{ e($r['title']) }}</a>
                    @if($r['committee_name'] !== '')<div class="text-muted small">{{ e($r['committee_name']) }}@if($r['reference_number'] !== '') &middot; {{ e($r['reference_number']) }}@endif</div>@endif
                </td>
                <td><span class="text-muted small">{{ e($typeOptions[$r['approval_type']] ?? ucfirst(str_replace('_',' ',$r['approval_type']))) }}</span></td>
                <td><span class="text-muted small">{{ e($consentOptions[$r['consent_basis']] ?? ucfirst(str_replace('_',' ',$r['consent_basis']))) }}</span></td>
                <td><span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span></td>
                <td class="small">
                    @if($r['expiry_date'] !== '')
                        {{ e($r['expiry_date']) }}
                        @if($flag === 'expired')<span class="badge bg-danger ms-1">{{ __('Expired') }}</span>
                        @elseif($flag === 'soon')<span class="badge bg-warning text-dark ms-1">{{ __('Soon') }}</span>@endif
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td class="text-end">
                    <a href="{{ route('research.ethics.edit', [$project->id ?? 0, $r['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.ethics.show', [$project->id ?? 0, $r['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
