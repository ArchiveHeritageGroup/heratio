{{-- View Extraction Job - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'Extraction Job Details')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Extraction Job</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-cogs text-primary me-2"></i>{{ e($job->title ?? 'Extraction Job') }}</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Job Details</div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-{{ match($job->status ?? '') { 'completed' => 'success', 'running' => 'primary', 'failed' => 'danger', 'queued' => 'info', default => 'warning' } }} fs-6">{{ ucfirst($job->status ?? 'pending') }}</span></dd>
        <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $job->extraction_type ?? '')) }}</dd>
        <dt class="col-sm-3">Source</dt><dd class="col-sm-9">{{ e($job->source_name ?? '-') }}</dd>
        <dt class="col-sm-3">Progress</dt><dd class="col-sm-9"><div class="progress" style="height:8px;"><div class="progress-bar" style="width:{{ $job->progress ?? 0 }}%"></div></div><small>{{ $job->progress ?? 0 }}%</small></dd>
        <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $job->created_at ?? '' }}</dd>
        <dt class="col-sm-3">Completed</dt><dd class="col-sm-9">{{ $job->completed_at ?? 'In progress' }}</dd>
    </dl>
</div></div>
@if(!empty($results))
<div class="card mb-4"><div class="card-header">Results ({{ count($results) }})</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>{{ __('Entity') }}</th><th>{{ __('Type') }}</th><th>{{ __('Confidence') }}</th><th>{{ __('Source') }}</th></tr></thead><tbody>
        @foreach($results as $r)<tr><td>{{ e($r->entity ?? '') }}</td><td><span class="badge bg-secondary">{{ $r->entity_type ?? '' }}</span></td><td>{{ number_format(($r->confidence ?? 0) * 100) }}%</td><td class="small">{{ e(Str::limit($r->source_text ?? '', 40)) }}</td></tr>@endforeach
    </tbody></table>
</div></div>
@endif
</div><div class="col-md-4">
@if(!empty($job->error_message))
<div class="card mb-4 border-danger"><div class="card-header bg-danger text-white">Error</div><div class="card-body"><pre class="mb-0 small text-danger">{{ e($job->error_message) }}</pre></div></div>
@endif
<div class="card"><div class="card-header"><h6 class="mb-0">{{ __('Actions') }}</h6></div><div class="card-body d-flex flex-column gap-2">
    @if(($job->status ?? '') === 'completed')
    <a href="#" class="btn btn-outline-primary btn-sm"><i class="fas fa-download me-1"></i>{{ __('Export Results') }}</a>
    @endif
    @if(in_array($job->status ?? '', ['running', 'queued']))
    <form method="POST" onsubmit="return confirm('Cancel this job?')">@csrf <input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-stop me-1"></i>{{ __('Cancel Job') }}</button></form>
    @endif
    <a href="{{ url()->previous() }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div></div>
</div></div>
@endsection