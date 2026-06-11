{{--
    Quick Capture Inbox - heratio#1228 (ROS Stage 0, epic #1222).
    The front door of the research mind: capture anything, triage later.
    Bootstrap 5 + central theme. Never 500s - empty-state when the inbox is clear.
--}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'inbox'])
@endsection

@section('title', __('Quick Capture Inbox'))

@php
    // Badge colour helpers, with a sensible fallback when a dropdown row carries no colour.
    $kindColor = [];
    foreach (($kindOptions ?? []) as $o) { $kindColor[$o['code']] = $o['color'] ?? null; }
    $originColor = [];
    foreach (($originOptions ?? []) as $o) { $originColor[$o['code']] = $o['color'] ?? null; }
    $kindLabel = [];
    foreach (($kindOptions ?? []) as $o) { $kindLabel[$o['code']] = $o['label'] ?? ucfirst($o['code']); }
    $originLabel = [];
    foreach (($originOptions ?? []) as $o) { $originLabel[$o['code']] = $o['label'] ?? ucfirst($o['code']); }
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Inbox') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-inbox text-primary me-2"></i>{{ __('Quick Capture Inbox') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#quickCaptureForm" aria-expanded="true" aria-controls="quickCaptureForm">
            <i class="fas fa-bolt me-1"></i>{{ __('Capture') }}
        </button>
    </div>
</div>

<p class="text-muted">{{ __('Ideas arrive from anywhere. Drop them here so nothing is lost - triage into a project later.') }}</p>

{{-- ─── Quick capture (one-tap note + optional link / file) ─────────────── --}}
<div class="collapse show mb-4" id="quickCaptureForm">
    <div class="card border-primary-subtle">
        <div class="card-header bg-primary-subtle"><h6 class="mb-0"><i class="fas fa-bolt me-1"></i>{{ __('Capture an idea') }}</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('research.inbox.capture') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('Title') }} <span class="text-muted small">({{ __('optional') }})</span></label>
                    <input type="text" name="title" maxlength="500" class="form-control" placeholder="{{ __('A short headline for this idea') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Note') }}</label>
                    <textarea name="body" class="form-control" rows="3" placeholder="{{ __('Type a note, paste a transcription, or drop in some context...') }}"></textarea>
                    <div class="form-text">{{ __('Voice captures land here as transcription text.') }}</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Kind') }}</label>
                        <select name="kind" class="form-select">
                            @foreach($kindOptions as $o)
                                <option value="{{ $o['code'] }}" @selected($o['code']==='note')>{{ $o['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Origin') }}</label>
                        <select name="origin" class="form-select">
                            @foreach($originOptions as $o)
                                <option value="{{ $o['code'] }}" @selected($o['code']==='web')>{{ $o['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Source URL') }} <span class="text-muted small">({{ __('optional') }})</span></label>
                        <input type="url" name="source_url" maxlength="1000" class="form-control" placeholder="https://...">
                    </div>
                </div>
                <div class="row g-3 mt-0">
                    <div class="col-md-8">
                        <label class="form-label">{{ __('Attachment') }} <span class="text-muted small">({{ __('optional, up to 50 MB') }})</span></label>
                        <input type="file" name="attachment" class="form-control">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-inbox me-1"></i>{{ __('Capture to inbox') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ─── Filters ─────────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('research.inbox.index') }}" class="card card-body mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">{{ __('Kind') }}</label>
            <select name="kind" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All kinds') }}</option>
                @foreach($kindOptions as $o)
                    <option value="{{ $o['code'] }}" @selected(($kind ?? '')===$o['code'])>{{ $o['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small mb-1">{{ __('Status') }}</label>
            <div class="btn-group w-100" role="group" aria-label="{{ __('Status filter') }}">
                @php
                    $statusChips = [
                        '' => ['label' => __('Inbox'), 'icon' => 'inbox', 'count' => $counts['inbox'] ?? 0],
                        'triaged' => ['label' => __('Triaged'), 'icon' => 'check-circle', 'count' => $counts['triaged'] ?? 0],
                        'archived' => ['label' => __('Archived'), 'icon' => 'archive', 'count' => $counts['archived'] ?? 0],
                    ];
                    $curStatus = $status ?? '';
                @endphp
                @foreach($statusChips as $code => $chip)
                    <a class="btn btn-sm {{ $curStatus === $code ? 'btn-secondary' : 'btn-outline-secondary' }}"
                       href="{{ route('research.inbox.index', array_filter(['kind' => $kind, 'status' => $code])) }}">
                        <i class="fas fa-{{ $chip['icon'] }} me-1"></i>{{ $chip['label'] }}
                        <span class="badge bg-light text-dark ms-1">{{ $chip['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            @if($kind || ($status ?? ''))
                <a href="{{ route('research.inbox.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>{{ __('Clear') }}</a>
            @endif
        </div>
    </div>
</form>

{{-- ─── Items ───────────────────────────────────────────────────────────── --}}
@if(!empty($items) && count($items) > 0)
    <div class="list-group shadow-sm">
        @foreach($items as $item)
            @php
                $kc = $kindColor[$item->kind] ?? null;
                $oc = $originColor[$item->origin] ?? null;
            @endphp
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-3 flex-grow-1">
                        <div class="mb-1 d-flex flex-wrap gap-1 align-items-center">
                            <span class="badge" style="background-color: {{ $kc ?: '#0d6efd' }}">{{ $kindLabel[$item->kind] ?? ucfirst($item->kind) }}</span>
                            <span class="badge" style="background-color: {{ $oc ?: '#6c757d' }}">{{ $originLabel[$item->origin] ?? ucfirst($item->origin) }}</span>
                            @if($item->status === 'triaged')
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Triaged') }}</span>
                            @elseif($item->status === 'archived')
                                <span class="badge bg-secondary"><i class="fas fa-archive me-1"></i>{{ __('Archived') }}</span>
                            @endif
                            @if($item->project_id)
                                <span class="badge bg-info text-dark"><i class="fas fa-folder me-1"></i>{{ __('In project') }} #{{ $item->project_id }}</span>
                            @endif
                        </div>
                        @if($item->title)
                            <h6 class="mb-1">{{ e($item->title) }}</h6>
                        @endif
                        @if($item->body)
                            <p class="mb-1 text-body-secondary">{{ e(Str::limit($item->body, 240)) }}</p>
                        @endif
                        @if($item->source_url)
                            <p class="mb-1 small"><i class="fas fa-link me-1 text-muted"></i><a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ e(Str::limit($item->source_url, 80)) }}</a></p>
                        @endif
                        @if($item->attachment_path)
                            <p class="mb-1 small text-muted"><i class="fas fa-paperclip me-1"></i>{{ e(basename($item->attachment_path)) }}</p>
                        @endif
                        <small class="text-muted"><i class="far fa-clock me-1"></i>{{ $item->captured_at ?? $item->created_at ?? '' }}</small>
                    </div>
                    <div class="text-nowrap">
                        <div class="btn-group btn-group-sm">
                            @if($item->status !== 'triaged')
                                <form method="POST" action="{{ route('research.inbox.triage', $item->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success" title="{{ __('Mark triaged') }}"><i class="fas fa-check"></i></button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#move-{{ $item->id }}" title="{{ __('Move to project') }}"><i class="fas fa-folder-plus"></i></button>
                            @if($item->status !== 'archived')
                                <form method="POST" action="{{ route('research.inbox.archive', $item->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary" title="{{ __('Archive') }}"><i class="fas fa-archive"></i></button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('research.inbox.restore', $item->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-info" title="{{ __('Restore to inbox') }}"><i class="fas fa-undo"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Move-to-project picker (collapsed) --}}
                <div class="collapse mt-2" id="move-{{ $item->id }}">
                    @if(!empty($projects) && count($projects) > 0)
                        <form method="POST" action="{{ route('research.inbox.move', $item->id) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-8">
                                <label class="form-label small mb-1">{{ __('Move to project') }}</label>
                                <select name="project_id" class="form-select form-select-sm" required>
                                    <option value="">{{ __('Choose a project...') }}</option>
                                    @foreach($projects as $p)
                                        <option value="{{ $p->id }}" @selected($item->project_id == $p->id)>{{ e($p->title ?? ('Project #'.$p->id)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-folder-plus me-1"></i>{{ __('Move') }}</button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-info mb-0 py-2 small">
                            {{ __('You have no projects yet.') }}
                            <a href="{{ route('research.projects') }}">{{ __('Create one') }}</a> {{ __('to triage items into it.') }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    {{-- Empty state - never 500, always a friendly nudge. --}}
    <div class="text-center text-muted py-5">
        <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
        <h5>{{ __('Your inbox is empty - capture an idea') }}</h5>
        <p class="mb-3">{{ __('Use the Capture button above to drop in a note, a link, or a file. Nothing gets lost.') }}</p>
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#quickCaptureForm"><i class="fas fa-bolt me-1"></i>{{ __('Capture an idea') }}</button>
    </div>
@endif
@endsection
