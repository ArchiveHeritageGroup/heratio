{{-- Time Machine timeline - Research OS moonshot 19 (heratio#1240). The honesty engine. --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Time Machine'))

@php
    $project    = $project ?? null;
    $projectId  = $projectId ?? ($project->id ?? 0);
    $grouped    = is_array($grouped ?? null) ? $grouped : [];
    $order      = ($order ?? 'desc') === 'asc' ? 'asc' : 'desc';
    $eventCount = (int) ($eventCount ?? 0);
    $kindBadges = $kindBadges ?? [];
    $kindLabels = $kindLabels ?? [];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $projectId) }}">{{ e($project->title ?? __('Project')) }}</a></li>
        <li class="breadcrumb-item active">{{ __('Time Machine') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h2 mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i>{{ __('Time Machine') }}</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('research.timemachine.asOf', $projectId) }}" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-calendar-day me-1"></i>{{ __('State as of a date') }}
        </a>
        <a href="{{ route('research.viewProject', $projectId) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

<p class="text-muted">{{ __('A read-only reconstruction of how this research developed over time, merged from the records the project already keeps - question briefs, decisions, claims, arguments, captured items and method protocols. Nothing here is editable; this is the honest record of what happened, and when.') }}</p>

{{-- Order toggle --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <span class="text-muted small">{{ trans_choice('{0}No events yet|{1}1 event|[2,*]:count events', $eventCount, ['count' => $eventCount]) }}</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Order') }}">
        <a href="{{ route('research.timemachine.index', $projectId) }}?order=desc"
           class="btn {{ $order === 'desc' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-arrow-down-wide-short me-1"></i>{{ __('Newest first') }}
        </a>
        <a href="{{ route('research.timemachine.index', $projectId) }}?order=asc"
           class="btn {{ $order === 'asc' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-arrow-up-wide-short me-1"></i>{{ __('Oldest first') }}
        </a>
    </div>
</div>

@if(empty($grouped))
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-clock-rotate-left fa-2x mb-3 d-block"></i>
            <p class="mb-1">{{ __('No dated activity has been recorded for this project yet.') }}</p>
            <p class="small mb-0">{{ __('As you save question briefs, log decisions, record claims, build arguments, capture items, and define method protocols, they will appear here on the timeline automatically.') }}</p>
        </div>
    </div>
@else
    @foreach($grouped as $monthKey => $events)
        @php
            try { $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $monthKey)->format('F Y'); }
            catch (\Throwable $e) { $monthLabel = $monthKey; }
        @endphp
        <h2 class="h5 text-muted border-bottom pb-2 mt-4">{{ $monthLabel }}</h2>
        <ul class="list-unstyled ms-2">
            @foreach($events as $event)
                @php
                    $kind  = $event['kind'] ?? 'inbox';
                    $badge = $kindBadges[$kind] ?? 'secondary';
                    $icon  = $event['icon'] ?? 'fa-circle';
                    $when  = $event['when'] ?? null;
                    $detail = trim((string) ($event['detail'] ?? ''));
                    $link  = $event['link'] ?? null;
                @endphp
                <li class="d-flex mb-3">
                    <div class="flex-shrink-0 me-3 text-center" style="width:2rem;">
                        <span class="badge rounded-pill bg-{{ $badge }}"><i class="fas {{ $icon }}"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                            <span class="fw-semibold">{{ $event['label'] ?? __('Event') }}</span>
                            @if($when)
                                <span class="text-muted small" title="{{ $when->format('Y-m-d H:i') }}">{{ $when->format('d M Y, H:i') }}</span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}-emphasis border border-{{ $badge }}-subtle">{{ __($kindLabels[$kind] ?? ucfirst($kind)) }}</span>
                            @if($detail !== '')
                                <span class="ms-1">{{ \Illuminate\Support\Str::limit($detail, 200) }}</span>
                            @endif
                            @if($link)
                                <a href="{{ $link }}" class="ms-1 text-decoration-none">{{ __('open') }} <i class="fas fa-arrow-up-right-from-square fa-xs"></i></a>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endforeach
@endif
@endsection
