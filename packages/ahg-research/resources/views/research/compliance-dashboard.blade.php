{{-- Compliance Dashboard --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Compliance Dashboard')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Compliance Dashboard</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-shield-alt text-primary me-2"></i>Compliance Dashboard</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

{{-- 4 summary cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                @php
                    $esIcon = match($ethicsStatus ?? '') {
                        'approved' => 'fas fa-check-circle text-success',
                        'pending' => 'fas fa-clock text-warning',
                        'rejected' => 'fas fa-times-circle text-danger',
                        default => 'fas fa-question-circle text-secondary'
                    };
                @endphp
                <i class="{{ $esIcon }} fa-2x mb-2"></i>
                <h6>{{ __('Ethics Status') }}</h6>
                <span class="badge bg-{{ match($ethicsStatus ?? '') { 'approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary' } }}">{{ ucfirst($ethicsStatus ?? 'Unknown') }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-balance-scale text-primary fa-2x mb-2"></i>
                <h6>{{ __('ODRL Policies') }}</h6>
                <h3 class="mb-0">{{ $odrlPolicyCount ?? 0 }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-lock text-warning fa-2x mb-2"></i>
                <h6>{{ __('Max Security Level') }}</h6>
                <span class="badge bg-warning">{{ ucfirst($sensitivitySummary['max_level'] ?? 'N/A') }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-chart-pie text-info fa-2x mb-2"></i>
                <h6>{{ __('Compliance Score') }}</h6>
                <span class="badge bg-info">--</span>
            </div>
        </div>
    </div>
</div>

{{-- Ethics Milestones --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('Ethics Milestones') }}</h6>
        <a href="{{ route('research.ethicsMilestones', $project->id ?? 0) }}" class="btn btn-sm btn-outline-primary">Manage</a>
    </div>
    <div class="card-body p-0">
        @if(!empty($ethicsMilestones) && count($ethicsMilestones) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($ethicsMilestones as $m)
                    <tr>
                        <td>{{ e($m->title ?? '') }}</td>
                        <td><span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $m->milestone_type ?? '')) }}</span></td>
                        <td><span class="badge bg-{{ match($m->status ?? '') { 'approved' => 'success', 'completed' => 'success', 'rejected' => 'danger', default => 'warning' } }}">{{ ucfirst($m->status ?? 'pending') }}</span></td>
                        <td class="small">{{ $m->created_at ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-3 text-muted small">No ethics milestones.</div>
        @endif
    </div>
</div>

{{-- ODRL Policies --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('ODRL Policies') }}</h6>
        <a href="{{ route('research.odrlPolicies') }}" class="btn btn-sm btn-outline-primary">Manage</a>
    </div>
    <div class="card-body p-0">
        @if(!empty($odrlPolicies) && count($odrlPolicies) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>{{ __('Policy') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($odrlPolicies as $p)
                    <tr>
                        <td>{{ e($p->title ?? $p->name ?? '') }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($p->type ?? '') }}</span></td>
                        <td><span class="badge bg-{{ ($p->status ?? '') === 'active' ? 'success' : 'warning' }}">{{ ucfirst($p->status ?? 'draft') }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-3 text-muted small">No ODRL policies.</div>
        @endif
    </div>
</div>

{{-- Sensitivity Breakdown --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('Sensitivity Breakdown') }}</h6></div>
    <div class="card-body">
        @if(!empty($sensitivityBreakdown) && count($sensitivityBreakdown) > 0)
            @foreach($sensitivityBreakdown as $level => $count)
                <span class="badge bg-{{ match($level) { 'high' => 'danger', 'medium' => 'warning', 'low' => 'success', default => 'secondary' } }} me-2 mb-2">{{ ucfirst($level) }}: {{ $count }}</span>
            @endforeach
        @else
            <span class="text-muted small">No sensitivity data available.</span>
        @endif
    </div>
</div>
@endsection
