{{--
    auth-res::park - Heratio authority-resolution parked-mention queue (Bootstrap 5).

    Dedicated screen (Task 7). Lists every active row in ahg_mention_park,
    grouped by parked_at desc. The archivist can:
        - filter by parked_by / entity_type / reason text / new-candidate-only
        - sort by parked_at / entity_type / new-candidate flag
        - unpark + re-review (POST -> back to /admin/authority-resolution/review/{id})
--}}
@extends('theme::layouts.1col')

@section('title', 'Parked mentions')

@section('content')
<div class="container py-4">

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('auth-res.queue') }}">{{ __('Authority Resolution') }}</a>
            </li>
            <li class="breadcrumb-item active">{{ __('Park queue') }}</li>
        </ol>
    </nav>

    <h1 class="mb-3">
        <i class="bi bi-pause-circle me-2"></i>{{ __('Parked Mentions') }}
    </h1>

    <p class="text-muted mb-3">
        {{ __('Mentions an archivist could not resolve at first pass. The Task 7 background scan flags rows whose candidate set has changed since parking - those rows get a "new candidate" badge. Unparking flips state back to pending and re-runs candidate generation + evidence scoring.') }}
    </p>

    @if(session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="row g-2 mb-3">
        <div class="col-md-3 col-sm-6">
            <div class="card text-center border-info">
                <div class="card-body py-2">
                    <h4 class="mb-0">{{ number_format($totalParked) }}</h4>
                    <small class="text-muted">{{ __('Total parked') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card text-center border-warning">
                <div class="card-body py-2">
                    <h4 class="mb-0">{{ number_format($totalNewCandidate) }}</h4>
                    <small class="text-muted">{{ __('New candidate(s) available') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card text-center border-secondary">
                <div class="card-body py-2">
                    <h4 class="mb-0">{{ number_format(count($countsByArchivist)) }}</h4>
                    <small class="text-muted">{{ __('Archivists involved') }}</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Dashboard widget partial --}}
    @include('auth-res::_park-dashboard-widget', [
        'countsByArchivist' => $countsByArchivist,
        'archivistNames'    => $archivistNames,
    ])

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('auth-res.park.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Parked by') }}</label>
                    <select name="parked_by" class="form-select form-select-sm">
                        <option value="0">{{ __('All archivists') }}</option>
                        @foreach($allParkedBy as $u)
                            <option value="{{ (int) $u->id }}" {{ $filterParkedBy === (int) $u->id ? 'selected' : '' }}>
                                {{ $u->name ?: 'user #' . (int) $u->id }} ({{ (int) $u->c }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('Entity type') }}</label>
                    <select name="entity_type" class="form-select form-select-sm">
                        <option value="">{{ __('All') }}</option>
                        @foreach($entityTypes as $et)
                            <option value="{{ $et }}" {{ $filterEntityType === $et ? 'selected' : '' }}>{{ \AhgAiServices\Support\EntityTypeLabels::label($et) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('Reason contains') }}</label>
                    <input type="text" name="reason_q"
                           value="{{ $filterReasonQ }}"
                           class="form-control form-control-sm"
                           placeholder="{{ __('text search') }}">
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="ar-park-newonly"
                               name="new_candidate_only" value="1"
                               {{ $filterNewCandidateOnly ? 'checked' : '' }}>
                        <label class="form-check-label small" for="ar-park-newonly">
                            {{ __('New candidate(s) only') }}
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">{{ __('Sort') }}</label>
                    <select name="sort_by" class="form-select form-select-sm">
                        @foreach([
                            'parked_at_desc' => 'Parked at (newest)',
                            'parked_at_asc'  => 'Parked at (oldest)',
                            'entity_type'    => 'Entity type',
                            'new_candidate'  => 'New-candidate flag',
                        ] as $key => $label)
                            <option value="{{ $key }}" {{ $filterSortBy === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>{{ __('Filter') }}
                    </button>
                    <a href="{{ route('auth-res.park.index') }}" class="btn btn-sm btn-link">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Parked queue list --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ number_format(count($rows)) }} {{ __('row(s) shown') }}</span>
            <small class="text-muted">
                {{ __('Sorted: new-candidate flagged first, then most-recently parked') }}
            </small>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Mention') }}</th>
                        <th>{{ __('Source IO') }}</th>
                        <th>{{ __('Parked by') }}</th>
                        <th>{{ __('Parked at') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th class="text-center">{{ __('Cands') }}</th>
                        <th class="text-center">{{ __('Flag') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @include('auth-res::_park-row', [
                            'r' => $r,
                            'archivistNames' => $archivistNames,
                        ])
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                {{ __('No parked mentions match the current filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(count($rows) === 200)
            <div class="card-footer text-muted small">
                {{ __('Showing first 200 parked rows. Tighten the filters to narrow the list.') }}
            </div>
        @endif
    </div>

    <p class="text-muted small mt-3">
        {{ __('Background scan:') }}
        <code>php artisan auth-res:scan-parked</code>
        {{ __('sweeps every parked row and flips') }}
        <code>new_candidate_available=1</code>
        {{ __('when the candidate set has changed since parking. Wire it via cron or') }}
        <code>php artisan schedule:run</code>.
    </p>
</div>
@endsection
