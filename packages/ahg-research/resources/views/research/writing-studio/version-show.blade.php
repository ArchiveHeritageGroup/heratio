{{-- Writing Studio - single version snapshot - Research OS Stage 13 (epic heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Version v' . ($version->version_no ?? '') . ' - Writing Studio')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.writing.index', $project->id) }}">{{ __('Writing Studio') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.writing.edit', [$project->id, $doc->id]) }}">{{ e(\Illuminate\Support\Str::limit($doc->title ?? 'Document', 50)) }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.writing.versions', [$project->id, $doc->id]) }}">{{ __('Versions') }}</a></li>
        <li class="breadcrumb-item active">v{{ $version->version_no ?? '' }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-file-alt text-primary me-2"></i>{{ __('Version') }} v{{ $version->version_no ?? '' }}</h1>
    <a href="{{ route('research.writing.versions', [$project->id, $doc->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

@if(!empty($version->note))
    <p class="text-muted"><i class="fas fa-sticky-note me-1"></i>{{ e($version->note) }}</p>
@endif
<p class="small text-muted">{{ __('Saved') }}: {{ $version->created_at ? \Illuminate\Support\Carbon::parse($version->created_at)->format('Y-m-d H:i') : '' }}</p>

<div class="card">
    <div class="card-body">
        @php $snap = (string) ($version->snapshot ?? ''); @endphp
        @if(trim($snap) !== '')
            <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ $snap }}</pre>
        @else
            <p class="text-muted mb-0">{{ __('This snapshot is empty.') }}</p>
        @endif
    </div>
</div>
@endsection
