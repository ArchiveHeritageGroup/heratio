{{-- Assertions --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Assertions')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Assertions</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-gavel text-primary me-2"></i>Assertions</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createAssertionForm"><i class="fas fa-plus me-1"></i>Create Assertion</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

{{-- Filter form --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">{{ __('Type') }}</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">{{ __('All Types') }}</option>
                    @foreach(['biographical', 'chronological', 'spatial', 'relational', 'attributive'] as $t)
                        <option value="{{ $t }}" {{ ($typeFilter ?? '') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(['proposed', 'verified', 'disputed'] as $s)
                        <option value="{{ $s }}" {{ ($statusFilter ?? '') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="{{ request()->url() }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Create assertion form (collapsed) --}}
<div class="collapse mb-4" id="createAssertionForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Create Assertion') }}</h6></div>
        <div class="card-body">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Predicate <span class="text-danger">*</span></label>
                        <input type="text" name="predicate" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Object <span class="text-danger">*</span></label>
                        <input type="text" name="object" class="form-control" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Assertion Type') }}</label>
                        <select name="assertion_type" class="form-select">
                            <option value="biographical">{{ __('Biographical') }}</option>
                            <option value="chronological">{{ __('Chronological') }}</option>
                            <option value="spatial">{{ __('Spatial') }}</option>
                            <option value="relational">{{ __('Relational') }}</option>
                            <option value="attributive">{{ __('Attributive') }}</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Confidence <span id="confidenceValue">50</span>%</label>
                        <input type="range" name="confidence" class="form-range" min="0" max="100" value="50" oninput="document.getElementById('confidenceValue').textContent=this.value">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Create</button>
            </form>
        </div>
    </div>
</div>

{{-- Assertions table --}}
<div class="card">
    <div class="card-body p-0">
        @if(!empty($assertions) && count($assertions) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Predicate') }}</th>
                        <th>{{ __('Object') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Evidence') }}</th>
                        <th>{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assertions as $a)
                    <tr>
                        <td>{{ e($a->subject_label ?? '') }}</td>
                        <td>{{ e($a->predicate ?? '') }}</td>
                        <td>{{ e($a->object_label ?? $a->object_value ?? '') }}</td>
                        <td>
                            <span class="badge bg-info">{{ ucfirst($a->assertion_type ?? '') }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ match($a->status ?? '') { 'verified' => 'success', 'disputed' => 'danger', default => 'warning' } }}">{{ ucfirst($a->status ?? 'proposed') }}</span>
                        </td>
                        <td>{{ $a->evidence_count ?? 0 }}</td>
                        <td class="small">{{ $a->created_at ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-4 text-muted">
            <i class="fas fa-gavel fa-2x mb-2 opacity-50"></i>
            <p>No assertions found.</p>
        </div>
        @endif
    </div>
</div>
@endsection
