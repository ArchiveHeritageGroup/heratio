{{-- Writing Studio - editor - Research OS Stage 13 (epic heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', ($doc->title ?? 'Document') . ' - Writing Studio')

@php
    $badges = $statusBadges ?? [];
    $sectionList = is_array($sections) ? $sections : [];
    $claimList   = is_array($claims) ? $claims : [];
    $sourceList  = is_array($sources) ? $sources : [];
    $aiOn = (bool) ($aiAvailable ?? false);
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.writing.index', $project->id) }}">{{ __('Writing Studio') }}</a></li>
        <li class="breadcrumb-item active">{{ e(\Illuminate\Support\Str::limit($doc->title ?? 'Document', 60)) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Document header / meta --}}
<form method="POST" action="{{ route('research.writing.update', [$project->id, $doc->id]) }}" class="mb-3">
    @csrf
    <div class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label small fw-bold">{{ __('Title') }}</label>
            <input type="text" name="title" class="form-control" maxlength="500" required value="{{ e($doc->title ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">{{ __('Type') }}</label>
            <select name="doc_type" class="form-select">
                @foreach(($docTypes ?? []) as $k => $label)
                    <option value="{{ $k }}" @selected(($doc->doc_type ?? '') === $k)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold">{{ __('Status') }}</label>
            <select name="status" class="form-select">
                @foreach(($statuses ?? []) as $k => $label)
                    <option value="{{ $k }}" @selected(($doc->status ?? '') === $k)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-outline-primary w-100" title="{{ __('Save document details') }}"><i class="fas fa-save"></i></button>
        </div>
    </div>
</form>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="{{ route('research.writing.export', [$project->id, $doc->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>{{ __('Export Markdown') }}</a>
    <a href="{{ route('research.writing.versions', [$project->id, $doc->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-history me-1"></i>{{ __('Version history') }}</a>
    <form method="POST" action="{{ route('research.writing.versions.save', [$project->id, $doc->id]) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-camera me-1"></i>{{ __('Save version') }}</button>
    </form>
</div>

@if(!empty($aiDraft) && !empty($aiDraftSectionId))
    {{-- A produced AI draft, labelled, awaiting researcher approval. Nothing is saved until they paste + save the section. --}}
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-robot me-1"></i>{{ __($aiLabel ?? 'AI-assisted draft (review required before use)') }}</span>
            <span class="badge bg-warning text-dark">{{ __('Not saved') }}</span>
        </div>
        <div class="card-body">
            <p class="small text-muted">{{ __('This draft was generated via the AHG AI gateway. Review and edit it, then save it into the section below if you accept it. It is never saved automatically.') }}</p>
            <form method="POST" action="{{ route('research.writing.sections.save', [$project->id, $doc->id, $aiDraftSectionId]) }}">
                @csrf
                <textarea name="body" class="form-control font-monospace" rows="10">{{ $aiDraft }}</textarea>
                <div class="mt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-check me-1"></i>{{ __('Accept into section') }}</button>
                    <a href="{{ route('research.writing.edit', [$project->id, $doc->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('Discard') }}</a>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="row g-3">
    {{-- Sections (the prose) --}}
    <div class="col-lg-8">
        <h2 class="h5 mb-3">{{ __('Sections') }}</h2>

        @forelse($sectionList as $s)
            <div class="card mb-3" id="section-{{ $s->id }}">
                <div class="card-body">
                    <form method="POST" action="{{ route('research.writing.sections.save', [$project->id, $doc->id, $s->id]) }}">
                        @csrf
                        <div class="mb-2">
                            <input type="text" name="heading" class="form-control form-control-sm fw-bold" maxlength="500" placeholder="{{ __('Section heading (optional)') }}" value="{{ e($s->heading ?? '') }}">
                        </div>
                        <textarea name="body" class="form-control" rows="8" placeholder="{{ __('Write here...') }}">{{ $s->body ?? '' }}</textarea>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                                @if($aiOn)
                                    <button type="submit" form="ai-{{ $s->id }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-robot me-1"></i>{{ __('AI draft') }}</button>
                                @endif
                            </div>
                            <span class="text-muted small">{{ $s->updated_at ? \Illuminate\Support\Carbon::parse($s->updated_at)->diffForHumans() : '' }}</span>
                        </div>
                    </form>
                    @if($aiOn)
                        <form method="POST" action="{{ route('research.writing.sections.ai', [$project->id, $doc->id, $s->id]) }}" id="ai-{{ $s->id }}" class="mt-2">
                            @csrf
                            <input type="text" name="instruction" class="form-control form-control-sm" maxlength="2000" placeholder="{{ __('Optional instruction for the AI draft (e.g. summarise the argument so far)') }}">
                        </form>
                    @endif
                    <form method="POST" action="{{ route('research.writing.sections.delete', [$project->id, $doc->id, $s->id]) }}" class="mt-2" onsubmit="return confirm('{{ __('Delete this section?') }}');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete section') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted border rounded mb-3">
                <i class="fas fa-paragraph fa-2x mb-2 opacity-50"></i>
                <p class="mb-0 small">{{ __('No sections yet. Add your first section below and start writing.') }}</p>
            </div>
        @endforelse

        {{-- Add a section --}}
        <div class="card border-primary">
            <div class="card-header bg-primary-subtle fw-bold"><i class="fas fa-plus me-1"></i>{{ __('Add a section') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.writing.sections.add', [$project->id, $doc->id]) }}">
                    @csrf
                    <input type="text" name="heading" class="form-control form-control-sm fw-bold mb-2" maxlength="500" placeholder="{{ __('Section heading (optional)') }}">
                    <textarea name="body" class="form-control" rows="4" placeholder="{{ __('Write here...') }}"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary mt-2"><i class="fas fa-plus me-1"></i>{{ __('Add section') }}</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Claims + sources sidebar to cite from --}}
    <div class="col-lg-4">
        {{-- Cite a claim --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-balance-scale text-primary me-1"></i>{{ __('Cite a claim') }}</span>
                <span class="badge bg-secondary">{{ count($claimList) }}</span>
            </div>
            <div class="card-body">
                @if(count($sectionList) === 0)
                    <p class="text-muted small mb-0">{{ __('Add a section first, then you can cite claims into it.') }}</p>
                @elseif(count($claimList) === 0)
                    <p class="text-muted small mb-0">{{ __('No claims in this project yet. Build claims in the Claim Ledger, then cite them here.') }}</p>
                @else
                    <form method="POST" action="{{ route('research.writing.cite', [$project->id, $doc->id]) }}">
                        @csrf
                        <label class="form-label small fw-bold">{{ __('Claim') }}</label>
                        <select name="claim_id" class="form-select form-select-sm mb-2" required>
                            @foreach($claimList as $c)
                                <option value="{{ $c->id }}">{{ e(\Illuminate\Support\Str::limit($c->object_value ?? $c->subject_label ?? ('Claim #'.$c->id), 70)) }}</option>
                            @endforeach
                        </select>
                        <label class="form-label small fw-bold">{{ __('Into section') }}</label>
                        <select name="section_id" class="form-select form-select-sm mb-2" required>
                            @foreach($sectionList as $s)
                                <option value="{{ $s->id }}">{{ e(\Illuminate\Support\Str::limit($s->heading ?: ('Section #'.$s->id), 50)) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-quote-right me-1"></i>{{ __('Cite into section') }}</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Pull a source --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-book text-info me-1"></i>{{ __('Pull a source') }}</span>
                <span class="badge bg-secondary">{{ count($sourceList) }}</span>
            </div>
            <div class="card-body">
                @if(count($sectionList) === 0)
                    <p class="text-muted small mb-0">{{ __('Add a section first, then you can pull sources into it.') }}</p>
                @elseif(count($sourceList) === 0)
                    <p class="text-muted small mb-0">{{ __('No bibliography sources in this project yet. Add sources in the bibliography, then pull them here.') }}</p>
                @else
                    <form method="POST" action="{{ route('research.writing.source', [$project->id, $doc->id]) }}">
                        @csrf
                        <label class="form-label small fw-bold">{{ __('Source') }}</label>
                        <select name="source_id" class="form-select form-select-sm mb-2" required>
                            @foreach($sourceList as $src)
                                <option value="{{ $src->id }}">{{ e(\Illuminate\Support\Str::limit(trim(($src->authors ? $src->authors.' - ' : '').($src->title ?? ('Source #'.$src->id))), 70)) }}</option>
                            @endforeach
                        </select>
                        <label class="form-label small fw-bold">{{ __('Into section') }}</label>
                        <select name="section_id" class="form-select form-select-sm mb-2" required>
                            @foreach($sectionList as $s)
                                <option value="{{ $s->id }}">{{ e(\Illuminate\Support\Str::limit($s->heading ?: ('Section #'.$s->id), 50)) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-info w-100"><i class="fas fa-file-import me-1"></i>{{ __('Pull into section') }}</button>
                    </form>
                @endif
            </div>
        </div>

        @unless($aiOn)
            <div class="alert alert-light border small mb-0">
                <i class="fas fa-info-circle me-1"></i>{{ __('AI drafting is off on this install. The studio works fully without it.') }}
            </div>
        @endunless
    </div>
</div>
@endsection
