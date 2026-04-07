{{-- Snapshots --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Snapshots')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Snapshots</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-camera text-primary me-2"></i>Snapshots</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createSnapshotForm"><i class="fas fa-plus me-1"></i>Create Snapshot</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

{{-- Create snapshot form (collapsed) --}}
<div class="collapse mb-4" id="createSnapshotForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">Create Snapshot</h6></div>
        <div class="card-body">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Create</button>
            </form>
        </div>
    </div>
</div>

{{-- Snapshots table --}}
<div class="card">
    <div class="card-body p-0">
        @if(!empty($snapshots) && count($snapshots) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($snapshots as $s)
                    <tr>
                        <td><strong>{{ e($s->title ?? 'Untitled') }}</strong></td>
                        <td>{{ e(Str::limit($s->description ?? '', 80)) }}</td>
                        <td class="small">{{ $s->created_at ?? '' }}</td>
                        <td>
                            <span class="badge bg-{{ match($s->status ?? '') { 'completed' => 'success', 'failed' => 'danger', default => 'secondary' } }}">{{ ucfirst($s->status ?? 'created') }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-4 text-muted">
            <i class="fas fa-camera fa-2x mb-2 opacity-50"></i>
            <p>No snapshots yet. Create one to capture the current project state.</p>
        </div>
        @endif
    </div>
</div>
@endsection
