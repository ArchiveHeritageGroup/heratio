{{-- Research Funding tracker - per-project list + summary (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Research Funding'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Research Funding') }}</li>
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
    <h1 class="h2"><i class="fas fa-hand-holding-dollar text-primary me-2"></i>{{ __('Research Funding') }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.funding.create', $project->id ?? 0) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('New Funding') }}</a>
        @if(($summary['total'] ?? 0) > 0)
            <a href="{{ route('research.funding.export', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}</a>
        @endif
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('The ledger of this project\'s funding sources - the funder, award reference, amount, currency, status and award period for each line. Awarded amounts are totalled separately for each currency and are never added across currencies. This is the awarded-funding record, distinct from drafting a grant proposal.') }}</p>

{{-- Per-project summary --}}
@if(($summary['total'] ?? 0) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('Summary') }}</h6>
        <span class="badge bg-primary rounded-pill">{{ $summary['total'] }} {{ __('total') }}</span>
    </div>
    <div class="card-body">
        @if(($summary['active_now'] ?? 0) > 0)
        <div class="alert alert-success d-flex align-items-center mb-3 py-2">
            <i class="fas fa-play-circle me-2"></i>
            <div class="small"><strong>{{ $summary['active_now'] }}</strong> {{ trans_choice('funding line is|funding lines are', $summary['active_now']) }} {{ __('active right now.') }}</div>
        </div>
        @endif

        {{-- Awarded total PER CURRENCY - never cross-summed. --}}
        @if(! empty($summary['by_currency']))
        <div class="mb-3">
            <div class="text-muted small text-uppercase mb-2">{{ __('Awarded total by currency') }}</div>
            <div class="row g-2">
                @foreach($summary['by_currency'] as $c)
                <div class="col-sm-6 col-lg-4">
                    <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ e($c['currency']) }} {{ number_format((float) $c['amount'], 2) }}</span>
                        <span class="badge bg-secondary">{{ $c['count'] }} {{ trans_choice('award|awards', $c['count']) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="form-text">{{ __('Each currency is totalled on its own line. Different currencies are never summed together.') }}</div>
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
                <div class="text-muted small text-uppercase mb-2">{{ __('By funder type') }}</div>
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
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No funding records yet. Record the funding sources that support this project - the funder, amount, currency, status and award period for each.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Funding') }}</th>
                <th>{{ __('Type') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Period') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
            @php
                $st = $r['status'] ?? 'applied';
                $badge = match($st) {
                    'awarded' => 'success', 'active' => 'primary', 'completed' => 'secondary',
                    'declined' => 'danger', 'applied' => 'warning', default => 'secondary',
                };
                $isActive = $active[$r['id']] ?? false;
            @endphp
            <tr>
                <td>
                    <a href="{{ route('research.funding.show', [$project->id ?? 0, $r['id']]) }}">{{ e($r['title']) }}</a>
                    <div class="text-muted small">{{ e($r['funder_name']) }}@if($r['award_reference'] !== '') &middot; {{ e($r['award_reference']) }}@endif</div>
                </td>
                <td><span class="text-muted small">{{ e($typeOptions[$r['funder_type']] ?? ucfirst(str_replace('_',' ',$r['funder_type']))) }}</span></td>
                <td class="text-end">
                    @if($r['amount'] !== '')
                        <span class="fw-semibold">{{ e($r['currency']) }} {{ number_format((float) $r['amount'], 2) }}</span>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td>
                    <span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
                    @if($isActive)<span class="badge bg-success-subtle text-success border border-success ms-1">{{ __('Active now') }}</span>@endif
                </td>
                <td class="small">
                    @if($r['start_date'] !== '' || $r['end_date'] !== '')
                        {{ e($r['start_date'] !== '' ? $r['start_date'] : '?') }} &ndash; {{ e($r['end_date'] !== '' ? $r['end_date'] : '?') }}
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td class="text-end">
                    <a href="{{ route('research.funding.edit', [$project->id ?? 0, $r['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.funding.show', [$project->id ?? 0, $r['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
