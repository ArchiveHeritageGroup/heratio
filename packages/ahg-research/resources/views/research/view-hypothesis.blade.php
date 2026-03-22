{{-- View Hypothesis - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'View Hypothesis')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Hypothesis</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-lightbulb text-primary me-2"></i>{{ e($hypothesis->title ?? 'Hypothesis') }}</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Hypothesis</div><div class="card-body">
    <p>{{ e($hypothesis->description ?? '') }}</p>
    <span class="badge bg-{{ match($hypothesis->status ?? '') { 'confirmed' => 'success', 'refuted' => 'danger', 'testing' => 'info', default => 'warning' } }} fs-6">{{ ucfirst($hypothesis->status ?? 'proposed') }}</span>
</div></div>
<div class="card mb-4"><div class="card-header">Supporting Evidence</div><div class="card-body p-0">
    @if(!empty($evidence))
    <table class="table table-hover mb-0"><thead class="table-light"><tr><th>Evidence</th><th>Source</th><th>Supports</th></tr></thead><tbody>
        @foreach($evidence as $e)<tr><td>{{ e(Str::limit($e->description ?? '', 60)) }}</td><td>{{ e($e->source_title ?? '-') }}</td><td><span class="badge bg-{{ ($e->supports ?? true) ? 'success' : 'danger' }}">{{ ($e->supports ?? true) ? 'Supports' : 'Contradicts' }}</span></td></tr>@endforeach
    </tbody></table>
    @else <div class="text-center py-4 text-muted">No evidence linked yet.</div>@endif
</div></div>
</div><div class="col-md-4">
<div class="card mb-4"><div class="card-header"><h6 class="mb-0">Details</h6></div><div class="card-body small">
    <dl class="row mb-0"><dt class="col-5">Project</dt><dd class="col-7">{{ e($hypothesis->project_title ?? '-') }}</dd><dt class="col-5">Created</dt><dd class="col-7">{{ $hypothesis->created_at ?? '' }}</dd><dt class="col-5">Updated</dt><dd class="col-7">{{ $hypothesis->updated_at ?? '' }}</dd></dl>
</div></div>
<div class="card"><div class="card-header"><h6 class="mb-0">Update Status</h6></div><div class="card-body">
    <form method="POST">@csrf <input type="hidden" name="hypothesis_id" value="{{ $hypothesis->id ?? 0 }}">
        <select name="status" class="form-select mb-2">@foreach(['proposed','testing','confirmed','refuted'] as $s)<option value="{{ $s }}" {{ ($hypothesis->status ?? '') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select>
        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-save me-1"></i>Update</button>
    </form>
</div></div>
</div></div>
@endsection