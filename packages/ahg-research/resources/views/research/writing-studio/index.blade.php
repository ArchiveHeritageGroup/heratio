{{-- Writing Studio - document list - Research OS Stage 13 (epic heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Writing Studio')

@php
    $badges = $statusBadges ?? [];
    $docList = is_array($docs) ? $docs : [];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Writing Studio') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-pen-fancy text-primary me-2"></i>{{ __('Writing Studio') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createDocForm"><i class="fas fa-plus me-1"></i>{{ __('New Document') }}</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('Write as you go, grounded in this project. Cite your own claims and pull in your sources without leaving the page.') }}</p>

{{-- Create document --}}
<div class="collapse mb-4" id="createDocForm">
    <div class="card card-body">
        <form method="POST" action="{{ route('research.writing.store', $project->id) }}">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">{{ __('Title') }}</label>
                    <input type="text" name="title" class="form-control" maxlength="500" required placeholder="{{ __('e.g. Chapter 3: Methods') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">{{ __('Type') }}</label>
                    <select name="doc_type" class="form-select">
                        @foreach(($docTypes ?? []) as $k => $label)
                            <option value="{{ $k }}" @selected($k === 'section')>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        @foreach(($statuses ?? []) as $k => $label)
                            <option value="{{ $k }}" @selected($k === 'draft')>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>{{ __('Create') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Document list --}}
@if(count($docList) > 0)
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-center">{{ __('Sections') }}</th>
                    <th>{{ __('Updated') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($docList as $d)
                    <tr>
                        <td><a href="{{ route('research.writing.edit', [$project->id, $d->id]) }}" class="fw-semibold text-decoration-none">{{ e($d->title ?? 'Untitled') }}</a></td>
                        <td><span class="text-muted small">{{ __(($docTypes[$d->doc_type] ?? $d->doc_type ?? '')) }}</span></td>
                        <td><span class="badge bg-{{ $badges[$d->status] ?? 'secondary' }}">{{ __(($statuses[$d->status] ?? $d->status ?? '')) }}</span></td>
                        <td class="text-center">{{ (int) ($d->section_count ?? 0) }}</td>
                        <td class="small text-muted">{{ $d->updated_at ? \Illuminate\Support\Carbon::parse($d->updated_at)->diffForHumans() : '' }}</td>
                        <td class="text-end">
                            <a href="{{ route('research.writing.edit', [$project->id, $d->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <a href="{{ route('research.writing.export', [$project->id, $d->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Export Markdown') }}"><i class="fas fa-download"></i></a>
                            <a href="{{ route('research.writing.versions', [$project->id, $d->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Version history') }}"><i class="fas fa-history"></i></a>
                            <form method="POST" action="{{ route('research.writing.destroy', [$project->id, $d->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this document and all its sections and versions?') }}');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="text-center py-5 text-muted">
        <i class="fas fa-pen-fancy fa-3x mb-3 opacity-50"></i>
        <p class="mb-1">{{ __('No documents yet.') }}</p>
        <p class="small">{{ __('Start a chapter, article, or section and write as you go.') }}</p>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createDocForm"><i class="fas fa-plus me-1"></i>{{ __('New Document') }}</button>
    </div>
@endif
@endsection
