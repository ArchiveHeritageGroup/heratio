{{-- Data Management Plan (DMP) Builder - per-project plan list + new-plan form (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Data Management Plans'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Data Management Plans') }}</li>
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
    <h1 class="h2"><i class="fas fa-database text-primary me-2"></i>{{ __('Data Management Plans') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newPlanForm"><i class="fas fa-plus me-1"></i>{{ __('New Plan') }}</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('A data management plan (DMP) is the FAIR research artifact funders ask for. This builder follows the RDA / Science Europe machine-actionable DMP (maDMP) common standard: data description, the four FAIR dimensions, storage and backup, preservation and retention, sharing and access, costs, responsibilities, and ethics and legal. The funder is recorded as data on the plan and is never assumed.') }}</p>

{{-- New-plan form (collapsed) --}}
<div class="collapse mb-4" id="newPlanForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Create a Data Management Plan') }}</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('research.dmp.store', $project->id ?? 0) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control" maxlength="255" value="{{ old('title') }}" placeholder="{{ __('Optional - defaults to Data Management Plan') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Funder') }}</label>
                        <input type="text" name="funder" class="form-control" maxlength="255" value="{{ old('funder') }}" placeholder="{{ __('e.g. the funder requiring this plan') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Funder template (optional)') }}</label>
                        <select name="funder_template" class="form-select">
                            <option value="">{{ __('None') }}</option>
                            @foreach($funderOptions as $code => $label)
                                <option value="{{ e($code) }}" @selected(old('funder_template') === $code)>{{ e($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Language') }}</label>
                        <input type="text" name="language" class="form-control" maxlength="12" value="{{ old('language', 'en') }}" placeholder="en">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Contact name') }}</label>
                        <input type="text" name="contact_name" class="form-control" maxlength="255" value="{{ old('contact_name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Contact email') }}</label>
                        <input type="email" name="contact_email" class="form-control" maxlength="255" value="{{ old('contact_email') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>{{ __('Create plan') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Plan list --}}
@if(empty($plans))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No data management plans yet. Create one to start answering the maDMP questions for this project.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Funder') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Completeness') }}</th>
                <th>{{ __('Updated') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plans as $p)
            @php
                $st = $p['status'] ?? 'draft';
                $badge = match($st) { 'published' => 'success', 'approved' => 'primary', 'in_review' => 'info', 'superseded' => 'dark', default => 'secondary' };
                $c = $completeness[$p['id']] ?? ['filled' => 0, 'total' => 0, 'pct' => 0];
            @endphp
            <tr>
                <td><a href="{{ route('research.dmp.show', [$project->id ?? 0, $p['id']]) }}">{{ e($p['title']) }}</a></td>
                <td><span class="text-muted small">{{ e($p['funder'] !== '' ? $p['funder'] : '-') }}</span></td>
                <td><span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span></td>
                <td style="min-width:140px;">
                    <div class="progress" role="progressbar" aria-valuenow="{{ $c['pct'] }}" aria-valuemin="0" aria-valuemax="100" style="height:18px;">
                        <div class="progress-bar @if($c['pct']==100) bg-success @endif" style="width: {{ $c['pct'] }}%;">{{ $c['pct'] }}%</div>
                    </div>
                    <span class="text-muted small">{{ $c['filled'] }}/{{ $c['total'] }} {{ __('sections') }}</span>
                </td>
                <td class="text-muted small">{{ e($p['updated_at']) }}</td>
                <td class="text-end">
                    <a href="{{ route('research.dmp.edit', [$project->id ?? 0, $p['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.dmp.show', [$project->id ?? 0, $p['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                    <a href="{{ route('research.dmp.export', [$project->id ?? 0, $p['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('maDMP JSON') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
