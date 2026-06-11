{{--
    Research Command Centre journey panel - Research OS #1225.
    Renders the per-project journey (where am I / what's next) at the top of the
    project page. Expects $journey (array of phases) + $journeyProgress.

    Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
    Part of Heratio. Licensed under the GNU AGPL v3 or later.
--}}
@php
    $journey = $journey ?? [];
    $jp = $journeyProgress ?? ['done' => 0, 'total' => 0, 'pct' => 0, 'next' => null];
    $statusMeta = [
        'done'    => ['bg' => 'success',   'icon' => 'fa-check'],
        'started' => ['bg' => 'primary',   'icon' => 'fa-spinner'],
        'todo'    => ['bg' => 'secondary', 'icon' => 'fa-circle'],
        'info'    => ['bg' => 'light',     'icon' => 'fa-ellipsis'],
    ];
@endphp

@if(!empty($journey))
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="background:var(--ahg-primary, #264653);color:#fff">
        <span class="fw-bold"><i class="fas fa-route me-2"></i>{{ __('Research Journey') }}</span>
        <span class="small">
            {{ __('Progress') }}: <strong>{{ $jp['done'] }}/{{ $jp['total'] }}</strong>
            @if($jp['next'])
                &middot; {{ __('Next') }}:
                @if(!empty($jp['next']['url']))
                    <a href="{{ $jp['next']['url'] }}" class="text-white text-decoration-underline">{{ __($jp['next']['label']) }}</a>
                @else
                    <strong>{{ __($jp['next']['label']) }}</strong>
                @endif
            @else
                &middot; {{ __('All core phases started') }}
            @endif
        </span>
    </div>
    <div class="card-body">
        <div class="progress mb-3" style="height:6px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: {{ (int) $jp['pct'] }}%"
                 aria-valuenow="{{ (int) $jp['pct'] }}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex flex-row flex-nowrap overflow-auto gap-2 pb-1">
            @foreach($journey as $ph)
                @php $m = $statusMeta[$ph['status']] ?? $statusMeta['todo']; @endphp
                <{{ !empty($ph['url']) ? 'a' : 'div' }}
                    @if(!empty($ph['url'])) href="{{ $ph['url'] }}" @endif
                    class="text-decoration-none text-dark flex-shrink-0 border rounded p-2 text-center position-relative {{ $ph['status'] === 'todo' ? 'bg-light' : '' }}"
                    style="width:118px;"
                    title="{{ __($ph['hint']) }}">
                    <div class="mb-1">
                        <span class="badge rounded-pill bg-{{ $m['bg'] }} {{ $ph['status'] === 'info' ? 'text-dark border' : '' }}">
                            <i class="fas {{ $m['icon'] }}"></i>
                        </span>
                    </div>
                    <div class="small fw-semibold"><i class="fas {{ $ph['icon'] }} me-1"></i>{{ __($ph['label']) }}</div>
                    @if($ph['count'] !== null)
                        <div class="small text-muted">{{ $ph['count'] }}</div>
                    @endif
                    @if(($ph['flag'] ?? null) === 'warn')
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" title="{{ __($ph['hint']) }}">!</span>
                    @endif
                </{{ !empty($ph['url']) ? 'a' : 'div' }}>
            @endforeach
        </div>
        @if($jp['next'] && !empty($jp['next']['url']))
            <div class="mt-3">
                <a href="{{ $jp['next']['url'] }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-arrow-right me-1"></i>{{ __('Continue: :phase', ['phase' => __($jp['next']['label'])]) }}
                </a>
                <span class="small text-muted ms-2">{{ __($jp['next']['hint']) }}</span>
            </div>
        @endif
    </div>
</div>
@endif
