{{-- Project Collaborators --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Project Collaborators')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Collaborators</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-users text-primary me-2"></i>Collaborators</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if(!empty($collaborators) && count($collaborators) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($collaborators as $c)
                    <tr>
                        <td><strong>{{ e(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) }}</strong></td>
                        <td>{{ e($c->email ?? '') }}</td>
                        <td>
                            <span class="badge bg-{{ match($c->role ?? '') { 'admin' => 'danger', 'editor' => 'warning', 'reviewer' => 'info', default => 'secondary' } }}">{{ ucfirst($c->role ?? 'contributor') }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ match($c->status ?? '') { 'active' => 'success', 'invited' => 'info', default => 'warning' } }}">{{ ucfirst($c->status ?? 'pending') }}</span>
                        </td>
                        <td>
                            @if(($c->researcher_id ?? 0) != ($researcher->id ?? -1))
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this collaborator?')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="collaborator_id" value="{{ $c->id }}">
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                            </form>
                            @else
                            <span class="badge bg-primary">{{ __('Owner') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-4 text-muted">
            <i class="fas fa-users fa-2x mb-2 opacity-50"></i>
            <p>No collaborators yet.</p>
        </div>
        @endif
    </div>
</div>
@endsection
