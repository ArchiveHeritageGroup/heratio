{{--
    Provenance & custody history for a museum object.

    Replaces a single-line table fed by a hardcoded empty collection. Every
    field here is a real column on provenance_entry / provenance_overview -
    bound by the name the query actually returns, since getChain() and
    getOverview() are plain SELECT * on those tables.

    A GAP is a first-class state, not a missing row. An honest provenance
    record says "ownership between 1750 and 1801 is unknown" rather than
    quietly closing the chain, and the dev data has exactly such an entry.
--}}
@extends('theme::layouts.1col')
@section('title', __('Provenance'))

@php
    $fmtDate = function ($date, $qualifier) {
        if (blank($date)) return null;
        return trim(($qualifier && $qualifier !== 'exact' ? __(ucfirst($qualifier)) . ' ' : '') . $date);
    };
    $certaintyClass = ['certain' => 'success', 'probable' => 'info', 'possible' => 'warning', 'unknown' => 'secondary'];
@endphp

@section('content')
<div class="container py-4">

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ url('/museum/' . ($resource->slug ?? '')) }}">{{ $resource->title ?? __('Object') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Provenance') }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-1"><i class="fas fa-history me-2"></i>{{ __('Provenance & Custody History') }}</h1>
    @if(!empty($resource->title))
        <p class="text-muted mb-4">{{ $resource->title }}</p>
    @endif

    {{-- Summary, when a curator has written one. --}}
    @if(!empty($overview?->provenance_summary))
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted mb-2">{{ __('Summary') }}</h2>
                <p class="mb-0">{{ $overview->provenance_summary }}</p>
            </div>
        </div>
    @endif

    {{-- Acquisition and custody. Only rendered when something is recorded:
         a grid of empty dashes tells the reader nothing. --}}
    @if($overview)
        @php
            $facts = array_filter([
                __('Current status')  => $overview->current_status,
                __('Custody')         => $overview->custody_type,
                __('Acquired by')     => $overview->acquisition_type,
                __('Acquired')        => $overview->acquisition_date_text ?: $overview->acquisition_date,
                __('Price')           => $overview->acquisition_price
                                            ? trim(($overview->acquisition_currency ?? '') . ' ' . number_format((float) $overview->acquisition_price, 2))
                                            : null,
                __('Certainty')       => $overview->certainty_level,
                __('Research status') => $overview->research_status,
            ], fn ($v) => filled($v));
        @endphp
        @if($facts)
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Acquisition & custody') }}</h2>
                    <dl class="row mb-0">
                        @foreach($facts as $label => $value)
                            <dt class="col-sm-3 fw-semibold">{{ $label }}</dt>
                            <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', (string) $value)) }}</dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif

        {{-- Due diligence. Shown only where a determination has actually been
             recorded - an absent check and a completed check that found nothing
             are different states, and rendering "No" for both would assert
             something untrue. --}}
        @php
            // 'none' is the vocabulary's no-concern default, not a finding.
            // Rendering a warning-bordered card to announce "Cultural property:
            // None" is noise, and worse, it implies a determination where the
            // curator only declined to flag one. Substantive values are
            // flagged / claimed / restituted / cleared.
            $culturalStatus  = in_array($overview->cultural_property_status, [null, '', 'none'], true)
                ? null : $overview->cultural_property_status;
            $hasDueDiligence = $overview->nazi_era_provenance_checked || $culturalStatus !== null;
            // Amber only for an OPEN question. A cleared or restituted record is
            // resolved, and colouring it as a caution misreads the finding.
            $dueDiligenceOpen = ($overview->nazi_era_provenance_checked && ! $overview->nazi_era_provenance_clear)
                || in_array($culturalStatus, ['flagged', 'claimed'], true);
        @endphp
        @if($hasDueDiligence)
            <div class="card mb-4 {{ $dueDiligenceOpen ? 'border-warning' : 'border-success' }}">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">{{ __('Due diligence') }}</h2>

                    @if($overview->nazi_era_provenance_checked)
                        <p class="mb-1">
                            <span class="badge bg-{{ $overview->nazi_era_provenance_clear ? 'success' : 'warning text-dark' }} me-2">
                                {{ $overview->nazi_era_provenance_clear ? __('Clear') : __('Unresolved') }}
                            </span>
                            {{ __('Nazi-era provenance (1933-1945) has been researched.') }}
                        </p>
                        @if(filled($overview->nazi_era_notes))
                            <p class="small text-muted ms-1 mb-3">{{ $overview->nazi_era_notes }}</p>
                        @endif
                    @endif

                    @if($culturalStatus !== null)
                        <p class="mb-1">
                            <strong>{{ __('Cultural property') }}:</strong>
                            {{ ucfirst(str_replace('_', ' ', $culturalStatus)) }}
                        </p>
                        @if(filled($overview->cultural_property_notes))
                            <p class="small text-muted ms-1 mb-0">{{ $overview->cultural_property_notes }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        @if($overview->has_gaps)
            <div class="alert alert-warning d-flex align-items-start" role="alert">
                <i class="fas fa-unlink me-2 mt-1" aria-hidden="true"></i>
                <div>
                    <strong>{{ __('This provenance has known gaps.') }}</strong>
                    @if(filled($overview->gap_description))
                        <div class="small mt-1">{{ $overview->gap_description }}</div>
                    @endif
                </div>
            </div>
        @endif
    @endif

    {{-- The chain itself. --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ __('Chain of ownership') }}</span>
            @if(count($provenanceChain))
                <span class="text-muted small">{{ trans_choice('{1} :count entry|[2,*] :count entries', count($provenanceChain), ['count' => count($provenanceChain)]) }}</span>
            @endif
        </div>

        @forelse($provenanceChain as $entry)
            <div class="card-body border-top {{ $entry->is_gap ? 'bg-warning-subtle' : '' }}">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h3 class="h6 mb-1">
                            <span class="text-muted me-2">{{ $entry->sequence }}.</span>
                            @if($entry->is_gap)
                                <i class="fas fa-unlink text-warning me-1" aria-hidden="true"></i>
                                <em>{{ __('Gap in provenance') }}</em>
                            @else
                                {{ $entry->owner_name ?: __('Unknown owner') }}
                            @endif
                        </h3>
                        <div class="small text-muted">
                            @if(filled($entry->owner_type))<span class="me-3">{{ ucfirst($entry->owner_type) }}</span>@endif
                            @if(filled($entry->owner_location))<span class="me-3"><i class="fas fa-map-marker-alt me-1" aria-hidden="true"></i>{{ $entry->owner_location }}</span>@endif
                            @php $from = $fmtDate($entry->start_date, $entry->start_date_qualifier); $to = $fmtDate($entry->end_date, $entry->end_date_qualifier); @endphp
                            @if($from || $to)
                                <span class="me-3"><i class="fas fa-clock me-1" aria-hidden="true"></i>{{ $from ?: '?' }} &ndash; {{ $to ?: '?' }}</span>
                            @endif
                        </div>
                    </div>
                    @if(filled($entry->certainty))
                        <span class="badge bg-{{ $certaintyClass[$entry->certainty] ?? 'secondary' }}">{{ ucfirst($entry->certainty) }}</span>
                    @endif
                </div>

                @if($entry->is_gap && filled($entry->gap_explanation))
                    <p class="small mb-0 mt-2">{{ $entry->gap_explanation }}</p>
                @endif

                @if(filled($entry->transfer_type) && !$entry->is_gap)
                    <p class="small mb-0 mt-2">
                        <strong>{{ __('Transfer') }}:</strong> {{ ucfirst(str_replace('_', ' ', $entry->transfer_type)) }}
                        @if($entry->sale_price)
                            &mdash; {{ trim(($entry->sale_currency ?? '') . ' ' . number_format((float) $entry->sale_price, 2)) }}
                        @endif
                        @if(filled($entry->auction_house))
                            &mdash; {{ $entry->auction_house }}@if(filled($entry->auction_lot)), {{ __('lot') }} {{ $entry->auction_lot }}@endif
                        @endif
                    </p>
                @endif

                @if(filled($entry->transfer_details))<p class="small text-muted mb-0 mt-1">{{ $entry->transfer_details }}</p>@endif

                @if(filled($entry->evidence_description) || filled($entry->sources))
                    <p class="small text-muted mb-0 mt-2">
                        <i class="fas fa-file-alt me-1" aria-hidden="true"></i>
                        @if(filled($entry->evidence_type))<strong>{{ ucfirst(str_replace('_', ' ', $entry->evidence_type)) }}:</strong> @endif
                        {{ $entry->evidence_description ?: $entry->sources }}
                    </p>
                @endif

                @if(filled($entry->notes))<p class="small text-muted fst-italic mb-0 mt-1">{{ $entry->notes }}</p>@endif
            </div>
        @empty
            <div class="card-body text-muted text-center py-4">
                {{ __('No provenance has been recorded for this object.') }}
            </div>
        @endforelse
    </div>

    {{-- Supporting documents. Download is authorisation-gated in the
         controller that serves the file, not here. --}}
    @if(isset($documents) && count($documents))
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Supporting documents') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($documents as $doc)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-paperclip me-2 text-muted" aria-hidden="true"></i>
                            {{ $doc->title ?? $doc->file_name ?? __('Document') }}
                            @if(filled($doc->document_type ?? null))
                                <span class="badge bg-light text-dark ms-2">{{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}</span>
                            @endif
                        </span>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/provenance/document/' . $doc->id . '/download') }}">
                            <i class="fas fa-download me-1" aria-hidden="true"></i>{{ __('Download') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</div>
@endsection
