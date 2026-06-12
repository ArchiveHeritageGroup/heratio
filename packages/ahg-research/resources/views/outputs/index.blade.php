{{-- Research Outputs register - per-project list + summary (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Research Outputs'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Research Outputs') }}</li>
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
    <h1 class="h2"><i class="fas fa-book text-primary me-2"></i>{{ __('Research Outputs') }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.outputs.create', $project->id ?? 0) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('New Output') }}</a>
        @if(($summary['total'] ?? 0) > 0)
            <a href="{{ route('research.outputs.export', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}</a>
        @endif
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('The register of scholarly outputs this project has produced - journal articles, datasets, software, presentations, theses, reports and chapters. Each output carries a persistent identifier (DOI, handle, ISBN or URL) resolved to a citable link, and can be linked to the project data management plan.') }}</p>

{{-- Per-project summary --}}
@if(($summary['total'] ?? 0) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('Summary') }}</h6>
        <span class="badge bg-primary rounded-pill">{{ $summary['total'] }} {{ __('total') }}</span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By type') }}</div>
                @foreach($summary['by_type'] as $t)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($t['label']) }} <span class="badge bg-secondary ms-1">{{ $t['count'] }}</span></span>
                @endforeach
            </div>
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By status') }}</div>
                @foreach($summary['by_status'] as $s)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($s['label']) }} <span class="badge bg-secondary ms-1">{{ $s['count'] }}</span></span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Output list --}}
@if(empty($outputs))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No research outputs recorded yet. Add the journal articles, datasets, software, presentations, theses and reports this project produces.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Identifier') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($outputs as $o)
            @php
                $st = $o['status'] ?? 'planned';
                $badge = match($st) { 'published' => 'success', 'in_progress' => 'warning', default => 'secondary' };
                $url = $resolved[$o['id']] ?? null;
            @endphp
            <tr>
                <td>
                    <a href="{{ route('research.outputs.show', [$project->id ?? 0, $o['id']]) }}">{{ e($o['title']) }}</a>
                    @if($o['venue'] !== '')<div class="text-muted small">{{ e($o['venue']) }}</div>@endif
                </td>
                <td><span class="text-muted small">{{ e($typeOptions[$o['output_type']] ?? ucfirst(str_replace('_',' ',$o['output_type']))) }}</span></td>
                <td>
                    @if($url)
                        <a href="{{ e($url) }}" target="_blank" rel="noopener noreferrer" class="small"><i class="fas fa-external-link-alt me-1"></i>{{ e($o['identifier'] !== '' ? $o['identifier'] : $url) }}</a>
                    @elseif($o['identifier'] !== '')
                        <span class="text-muted small">{{ e($o['identifier']) }}</span>
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="text-muted small">{{ e($o['output_date'] !== '' ? $o['output_date'] : '-') }}</td>
                <td><span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span></td>
                <td class="text-end">
                    <a href="{{ route('research.outputs.edit', [$project->id ?? 0, $o['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.outputs.show', [$project->id ?? 0, $o['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
