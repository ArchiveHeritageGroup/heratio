{{--
  Potentially displaced heritage - curatorial review register (heratio#1207)

  First slice of the repatriation engine: DETECTION only. Lists museum-catalogued
  objects whose recorded origin region appears to differ from where they are now
  held, grouped by origin region with counts. This is a heuristic REVIEW AID - it
  is NOT a repatriation claim, NOT a legal determination, and NOT advice. The copy
  is jurisdiction-neutral and framed respectfully throughout: every row is a lead
  for qualified staff to examine, never a verdict.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $report   = $report ?? [];
    $records  = $report['records'] ?? [];
    $byOrigin = $report['by_origin'] ?? [];
    $scanned  = (int) ($report['scanned'] ?? 0);
    $evaluated = (int) ($report['evaluated'] ?? 0);
    $flagged  = (int) ($report['flagged_count'] ?? 0);
    $truncated = (bool) ($report['truncated'] ?? false);
    $limit    = (int) ($report['limit'] ?? 0);
    $disclaimer = $report['disclaimer'] ?? '';
@endphp

@section('title', __('Potentially displaced heritage - review register'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-route me-2"></i>{{ __('Potentially displaced heritage') }}
            </h1>
            <p class="text-muted mb-0">
                {{ __('A review register of objects whose recorded origin appears to differ from where they are now held.') }}
            </p>
        </div>
        <div class="text-end small text-muted">
            <div><span class="badge bg-secondary">{{ $scanned }}</span> {{ __('records scanned') }}</div>
            <div><span class="badge bg-secondary">{{ $evaluated }}</span> {{ __('with a placeable origin and holding') }}</div>
            <div><span class="badge bg-warning text-dark">{{ $flagged }}</span> {{ __('flagged for review') }}</div>
        </div>
    </div>

    {{-- Prominent "review aid, not a claim" disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-triangle-exclamation fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A review aid - not a repatriation claim, and not legal advice.') }}</strong>
            <p class="mb-1 small">
                {{ $disclaimer }}
            </p>
            <p class="mb-0 small">
                {{ __('Flags are produced by a deliberately conservative heuristic that compares the country or broad region named in a record\'s origin fields against its current holding location. It only flags a record when BOTH sides can be placed with confidence and they differ; anything it cannot place is left unflagged. A flag means "worth a closer look", nothing more. Origin, ownership and the lawfulness of any past transfer must be assessed case by case by qualified staff, against the relevant evidence and the law that applies.') }}
            </p>
        </div>
    </div>

    @if($flagged === 0)
        <div class="alert alert-info">
            <i class="fas fa-circle-info me-2"></i>
            {{ __('No origin-vs-holding mismatches were flagged. Records whose origin or holding location could not be confidently placed are intentionally left unflagged.') }}
        </div>
    @else

        <div class="row g-4 mb-4">

            {{-- Summary by origin region --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-globe me-2 text-primary"></i>
                        <strong>{{ __('By recorded origin region') }}</strong>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush mb-0">
                            @foreach($byOrigin as $grp)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $grp['region'] }}</span>
                                    <span class="badge bg-secondary rounded-pill">{{ (int) $grp['count'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="card-footer small text-muted">
                        {{ __('Origin region is read from the record\'s place-of-creation, place-of-discovery, cultural-group or cultural-context fields, in that order.') }}
                    </div>
                </div>
            </div>

            {{-- What this looks at --}}
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-circle-question me-2 text-secondary"></i>
                        <strong>{{ __('How a record gets flagged') }}</strong>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 small">
                            <li>{{ __('The recorded ORIGIN is read from the catalogue (place of creation, place of discovery, cultural group, or cultural context).') }}</li>
                            <li>{{ __('The current HOLDING is read from the current-location geography, the holding repository, the current-location note, or - failing those - the holding repository\'s recorded country.') }}</li>
                            <li>{{ __('Both are reduced to a country or broad region. A record is flagged only when BOTH are known and they differ.') }}</li>
                            <li>{{ __('Empty or unrecognised values are treated as unknown and never flagged. The intent is high precision: few false flags, even at the cost of missing some.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @if($truncated)
            <div class="alert alert-secondary py-2 small">
                <i class="fas fa-circle-info me-1"></i>
                {{ __('Showing the first :n flagged records. Adjust the limit to see more.', ['n' => $limit]) }}
            </div>
        @endif

        {{-- Flagged records table --}}
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <i class="fas fa-list-check me-2 text-warning"></i>
                <strong>{{ __('Flagged for curatorial review') }}</strong>
                <span class="badge bg-warning text-dark ms-2">{{ count($records) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">{{ __('Object') }}</th>
                            <th scope="col">{{ __('Recorded origin') }}</th>
                            <th scope="col">{{ __('Current holding') }}</th>
                            <th scope="col">{{ __('Why it was flagged') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $rec)
                            @php
                                $title = $rec['title'] ?? ('#'.$rec['id']);
                            @endphp
                            <tr>
                                <td>
                                    @if(!empty($rec['slug']))
                                        <a href="{{ url('/'.$rec['slug']) }}">{{ $title }}</a>
                                    @else
                                        <span>{{ $title }}</span>
                                    @endif
                                    <div class="text-muted small">#{{ $rec['id'] }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $rec['origin_region'] }}</span>
                                    <div class="text-muted small">
                                        {{ $rec['origin']['label'] }}: {{ $rec['origin']['value'] }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $rec['holding_region'] }}</span>
                                    <div class="text-muted small">
                                        {{ $rec['holding']['label'] }}: {{ $rec['holding']['value'] }}
                                    </div>
                                </td>
                                <td class="small text-muted">{{ $rec['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">
                <i class="fas fa-scale-balanced me-1"></i>
                {{ __('Each row is a lead for review only - not a finding of wrongful removal, and not a recommendation to return anything.') }}
            </div>
        </div>

    @endif

</div>
@endsection
