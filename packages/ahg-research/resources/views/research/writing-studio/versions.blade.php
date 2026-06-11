{{-- Writing Studio - version history - Research OS Stage 13 (epic heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Version history - Writing Studio')

@php
    $versionList = is_array($versions) ? $versions : [];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.writing.index', $project->id) }}">{{ __('Writing Studio') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.writing.edit', [$project->id, $doc->id]) }}">{{ e(\Illuminate\Support\Str::limit($doc->title ?? 'Document', 50)) }}</a></li>
        <li class="breadcrumb-item active">{{ __('Versions') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-history text-primary me-2"></i>{{ __('Version history') }}</h1>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('research.writing.versions.save', [$project->id, $doc->id]) }}" class="d-flex gap-2">
            @csrf
            <input type="text" name="note" class="form-control form-control-sm" maxlength="1000" placeholder="{{ __('Optional note for this version') }}">
            <button type="submit" class="btn btn-sm btn-success text-nowrap"><i class="fas fa-camera me-1"></i>{{ __('Save version') }}</button>
        </form>
        <a href="{{ route('research.writing.edit', [$project->id, $doc->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('Each saved version is a full snapshot of the document at that moment.') }}</p>

@if(count($versionList) > 0)
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th class="text-center">{{ __('Version') }}</th>
                    <th>{{ __('Note') }}</th>
                    <th>{{ __('Saved') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($versionList as $v)
                    <tr>
                        <td class="text-center"><span class="badge bg-primary">v{{ $v->version_no }}</span></td>
                        <td>{{ e($v->note ?? '') ?: '—' }}</td>
                        <td class="small text-muted">{{ $v->created_at ? \Illuminate\Support\Carbon::parse($v->created_at)->format('Y-m-d H:i') : '' }}</td>
                        <td class="text-end">
                            <a href="{{ route('research.writing.versions.show', [$project->id, $doc->id, $v->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="text-center py-5 text-muted">
        <i class="fas fa-history fa-3x mb-3 opacity-50"></i>
        <p class="mb-1">{{ __('No versions yet.') }}</p>
        <p class="small">{{ __('Save a version to capture a snapshot you can return to.') }}</p>
    </div>
@endif
@endsection
