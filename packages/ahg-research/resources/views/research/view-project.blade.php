@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-project-diagram me-2"></i>{{ e($project->title) }}</h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Project Details</h5></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9"><span class="badge bg-{{ $project->status === 'active' ? 'success' : ($project->status === 'completed' ? 'info' : 'secondary') }}">{{ ucfirst($project->status ?? 'active') }}</span></dd>
                    <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ ucfirst($project->project_type ?? 'personal') }}</dd>
                    @if($project->institution)<dt class="col-sm-3">Institution</dt><dd class="col-sm-9">{{ e($project->institution) }}</dd>@endif
                    @if($project->start_date)<dt class="col-sm-3">Start Date</dt><dd class="col-sm-9">{{ $project->start_date }}</dd>@endif
                    @if($project->expected_end_date)<dt class="col-sm-3">Expected End</dt><dd class="col-sm-9">{{ $project->expected_end_date }}</dd>@endif
                    @if($project->description)<dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ e($project->description) }}</dd>@endif
                </dl>
            </div>
        </div>

        @if(!empty($milestones))
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-flag me-2"></i>Milestones</h5></div>
            <div class="list-group list-group-flush">
                @foreach($milestones as $m)
                <div class="list-group-item d-flex justify-content-between">
                    <span>{{ e($m->title ?? 'Milestone') }}</span>
                    <span class="badge bg-{{ ($m->status ?? '') === 'completed' ? 'success' : 'secondary' }}">{{ ucfirst($m->status ?? 'pending') }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($resources))
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-link me-2"></i>Resources</h5></div>
            <div class="list-group list-group-flush">
                @foreach($resources as $r)
                <div class="list-group-item">
                    <strong>{{ e($r->title ?? 'Resource') }}</strong>
                    @if($r->external_url ?? null) <a href="{{ e($r->external_url) }}" target="_blank" class="ms-2"><i class="fas fa-external-link-alt"></i></a>@endif
                    @if($r->description ?? null)<br><small class="text-muted">{{ e($r->description) }}</small>@endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Collaborators</h5></div>
            <div class="list-group list-group-flush">
                @forelse($collaborators as $c)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ e($c->first_name . ' ' . $c->last_name) }}</span>
                    <span class="badge bg-info">{{ ucfirst($c->role ?? 'contributor') }}</span>
                </div>
                @empty
                <div class="list-group-item text-muted">No collaborators yet</div>
                @endforelse
            </div>
        </div>

        @if(!empty($activities))
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-stream me-2"></i>Recent Activity</h5></div>
            <div class="list-group list-group-flush">
                @foreach(array_slice($activities, 0, 10) as $a)
                <div class="list-group-item py-2">
                    <small class="text-muted">{{ $a->created_at ?? '' }}</small><br>
                    {{ e($a->description ?? $a->action ?? '') }}
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
