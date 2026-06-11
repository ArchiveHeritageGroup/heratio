{{-- Method Design Studio - per-project protocol list (heratio#1231) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Method Studio'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Method Studio') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-drafting-compass text-primary me-2"></i>{{ __('Method Design Studio') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#startProtocolForm"><i class="fas fa-plus me-1"></i>{{ __('New Method Protocol') }}</button>
        <a href="{{ route('research.method.templates', ['project' => $project->id ?? 0]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-th-large me-1"></i>{{ __('Browse templates') }}</a>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('A Method Protocol is written once from a discipline template, then reused by your thesis methodology chapter, grant, and ethics application.') }}</p>

{{-- Start-from-template form (collapsed) --}}
<div class="collapse mb-4" id="startProtocolForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Start a Method Protocol') }}</h6></div>
        <div class="card-body">
            @if(empty($templates))
                <div class="alert alert-info mb-0">{{ __('No method templates are available yet.') }}</div>
            @else
            <form method="POST" action="{{ route('research.method.store', $project->id ?? 0) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Discipline template') }} <span class="text-danger">*</span></label>
                        <select name="template_code" class="form-select" required>
                            <option value="">{{ __('Choose a template...') }}</option>
                            @foreach($templates as $t)
                                <option value="{{ e($t['code']) }}">{{ e($t['name']) }}@if(!empty($t['discipline'])) - {{ e($t['discipline']) }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control" maxlength="255" placeholder="{{ __('Optional - defaults to the template name') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i>{{ __('Create') }}</button>
                    </div>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>

{{-- Protocol list --}}
@if(empty($protocols))
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-1"></i>{{ __('No method protocols yet. Start one from a discipline template to design your methodology.') }}
    </div>
@else
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Template') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Updated') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($protocols as $p)
            <tr>
                <td><a href="{{ route('research.method.show', [$project->id ?? 0, $p['id']]) }}">{{ e($p['title']) }}</a></td>
                <td><span class="text-muted small">{{ e($p['template_code']) }}</span></td>
                <td>
                    @php
                        $st = $p['status'] ?? 'draft';
                        $badge = match($st) { 'final' => 'success', 'in_review' => 'info', default => 'secondary' };
                    @endphp
                    <span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
                </td>
                <td class="text-muted small">{{ e($p['updated_at']) }}</td>
                <td class="text-end">
                    <a href="{{ route('research.method.edit', [$project->id ?? 0, $p['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.method.show', [$project->id ?? 0, $p['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
