{{--
    auth-res::queue - Heratio authority-resolution review queue (Bootstrap 5).

    Pending NER mentions promoted into the authority-resolution workflow.
    Click any row to jump to /admin/authority-resolution/review/{id}.
--}}
@extends('theme::layouts.1col')

@section('title', 'Authority Resolution Queue')

@section('content')
@php
    $typeBadges = [
        'PERSON'     => 'primary',
        'ORG'        => 'info',
        'GPE'        => 'success',
        'LOC'        => 'success',
        'PLACE'      => 'success',
        'ISAD_PLACE' => 'success',
    ];
    $stateBadges = [
        'pending'             => 'warning',
        'linked'              => 'success',
        'parked'              => 'info',
        'rejected'            => 'secondary',
        'new_record_created'  => 'primary',
    ];
@endphp
<div class="container py-4">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">{{ __('Authority Resolution') }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            <i class="bi bi-people me-2"></i>{{ __('Authority Resolution Queue') }}
        </h1>
        <div class="btn-group">
            <a href="{{ route('auth-res.park.index') }}" class="btn btn-outline-info">
                <i class="bi bi-pause-circle me-1"></i>{{ __('Parked') }}
            </a>
            <a href="{{ route('auth-res.settings.show') }}" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i>{{ __('Lookup settings') }}
            </a>
        </div>
    </div>

    <p class="text-muted mb-3">
        {{ __('Pending NER mentions promoted into the authority-resolution workflow. Pick a mention to see its evidence packet and ranked candidates.') }}
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

    {{-- State KPIs --}}
    <div class="row g-2 mb-3">
        @foreach(['pending', 'linked', 'parked', 'rejected', 'new_record_created'] as $state)
            <div class="col-md col-sm-4">
                <a href="{{ route('auth-res.queue', ['state' => $state]) }}"
                   class="text-decoration-none">
                    <div class="card text-center border-{{ $stateBadges[$state] ?? 'secondary' }}">
                        <div class="card-body py-2">
                            <h4 class="mb-0">{{ number_format($counts[$state] ?? 0) }}</h4>
                            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $state)) }}</small>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('auth-res.queue') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Entity type') }}</label>
                    <select name="entity_type" class="form-select form-select-sm">
                        <option value="">{{ __('All types') }}</option>
                        @foreach(['PERSON', 'ORG', 'GPE', 'LOC', 'PLACE'] as $et)
                            <option value="{{ $et }}" {{ $filterEntityType === $et ? 'selected' : '' }}>{{ $et }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('State') }}</label>
                    <select name="state" class="form-select form-select-sm">
                        @foreach(['pending', 'linked', 'parked', 'rejected', 'new_record_created', 'any'] as $s)
                            <option value="{{ $s }}" {{ $filterState === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Object ID') }}</label>
                    <input type="number" name="object_id"
                           value="{{ $filterObjectId ?: '' }}"
                           class="form-control form-control-sm"
                           placeholder="any">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>{{ __('Filter') }}
                    </button>
                    <a href="{{ route('auth-res.queue') }}" class="btn btn-sm btn-link">
                        {{ __('Reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Result table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ trans_choice('{0} no mentions|{1} :count mention|[2,*] :count mentions', $rows->count(), ['count' => number_format($rows->count())]) }}</span>
            <small class="text-muted">{{ __('Sorted by mention id') }}</small>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Mention') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Source IO') }}</th>
                        <th class="text-center">{{ __('Candidates') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Promoted') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td class="text-muted small">#{{ (int) $r->id }}</td>
                            <td><strong>{{ $r->entity_value }}</strong></td>
                            <td>
                                <span class="badge bg-{{ $typeBadges[$r->entity_type] ?? 'secondary' }}">
                                    {{ $r->entity_type }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                <span class="text-muted">Object #{{ (int) $r->object_id }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ ((int) $r->candidate_count) > 0 ? 'dark' : 'light text-dark border' }}">
                                    {{ (int) $r->candidate_count }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $stateBadges[$r->state] ?? 'secondary' }}">
                                    {{ $r->state }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $r->promoted_at }}</td>
                            <td>
                                <a href="{{ route('auth-res.review.show', ['mention' => $r->id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-search me-1"></i>{{ __('Review') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                {{ __('No mentions match the current filter.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->count() === 200)
            <div class="card-footer text-muted small">
                {{ __('Showing first 200 rows. Tighten the filters to narrow the list.') }}
            </div>
        @endif
    </div>
</div>
@endsection
