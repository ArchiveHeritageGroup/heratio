@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-file-alt me-2"></i>{{ e($artefact->title ?: 'Studio artefact') }}</h1>
    <p class="text-muted mb-0">
        {{ \AhgResearch\Services\ResearchStudioService::SUPPORTED_TYPES[$artefact->output_type] ?? $artefact->output_type }}
        - {{ \Carbon\Carbon::parse($artefact->created_at)->toDayDateTimeString() }}
        @if($artefact->model)- <span class="badge bg-secondary">{{ e($artefact->model) }}</span>@endif
    </p>
@endsection
@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('research.studio', $project->id) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Studio') }}</a>
    <div>
        @if(in_array($artefact->output_type, ['spreadsheet', 'audio']) && $artefact->status === 'ready')
            <a href="{{ route('research.studioDownload', ['projectId' => $project->id, 'artefactId' => $artefact->id]) }}" class="btn btn-sm btn-success">
                <i class="fas fa-download me-1"></i>{{ __('Download') }}
            </a>
        @endif
        <form method="post" action="{{ route('research.studioDelete', ['projectId' => $project->id, 'artefactId' => $artefact->id]) }}" class="d-inline" onsubmit="return confirm('Delete this artefact?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</button>
        </form>
    </div>
</div>

@if($artefact->status === 'error')
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ e($artefact->error_text ?: 'Generation failed.') }}
    </div>
@endif

@if($artefact->output_type === 'audio' && $artefact->audio_url)
    <div class="card mb-3">
        <div class="card-body">
            <audio controls preload="metadata" src="{{ e($artefact->audio_url) }}" class="w-100"></audio>
            @if($artefact->audio_duration_seconds)
                <small class="text-muted">{{ gmdate('i:s', (int) $artefact->audio_duration_seconds) }} duration</small>
            @endif
        </div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Body') }}</h6></div>
    <div class="card-body" id="studio-body" data-body-format="{{ $artefact->body_format ?? 'markdown' }}">
        @if(($artefact->body_format ?? 'markdown') === 'mermaid')
            <pre class="mermaid">{!! e($artefact->body) !!}</pre>
        @elseif(($artefact->body_format ?? 'markdown') === 'json')
            <pre class="bg-light p-3 rounded" style="max-height:480px;overflow:auto"><code>{!! e($artefact->body) !!}</code></pre>
        @else
            <div class="markdown-body">{!! nl2br(e($artefact->body)) !!}</div>
        @endif
    </div>
</div>

@if(!empty($citations))
    <div class="card">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Sources') }}</h6></div>
        <ul class="list-group list-group-flush" id="studio-citations">
            @foreach($citations as $c)
                <li class="list-group-item" data-citation-n="{{ (int) ($c['n'] ?? 0) }}">
                    <strong>[{{ (int) ($c['n'] ?? 0) }}]</strong>
                    <a href="{{ e($c['url'] ?? '#') }}" target="_blank">{{ e($c['title'] ?? 'Untitled') }}</a>
                    @if(!empty($c['identifier']))<span class="badge bg-secondary ms-1">{{ e($c['identifier']) }}</span>@endif
                    <div class="small text-muted mt-1">{{ e(mb_substr((string) ($c['snippet'] ?? ''), 0, 240)) }}@if(mb_strlen((string) ($c['snippet'] ?? '')) > 240)...@endif</div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@push('js')
<script type="module">
    if (document.querySelector('pre.mermaid')) {
        try {
            const m = await import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs');
            m.default.initialize({ startOnLoad: true });
        } catch (e) {
            console.warn('mermaid failed to load', e);
        }
    }
</script>
<script src="{{ asset('vendor/ahg-research/citation-popover.js') }}"></script>
@endpush
@endsection
