{{--
  ODI Quality Scorecard
  Open Discovery Initiative conformance metrics per library collection.

  @author    Johan Pieterse
  @copyright Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'ODI Quality Scorecard')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-clipboard-check me-2"></i>ODI Quality Scorecard
            </h1>
            <p class="text-muted small mb-0">
                Open Discovery Initiative (ODI) conformance metrics for each library collection:
                link-resolver availability, open-access share, preprint indexing and ORCID coverage.
            </p>
        </div>
        <div>
            <form method="POST" action="{{ route('library.odi-scorecard-refresh') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync-alt me-1"></i>Recompute scores
                </button>
            </form>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('status'))
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('status') }}</div>
    @endif

    @php
        $scoreBadge = function ($score) {
            $s = (float) $score;
            if ($s >= 75) return 'success';
            if ($s >= 50) return 'warning';
            return 'danger';
        };
    @endphp

    @if(empty($scorecards))
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
                <p class="mb-2">No scorecards computed yet.</p>
                <p class="small mb-3">
                    Run <code>php artisan ahg:library-odi-refresh</code> or use the
                    Recompute scores button above to generate the scorecard from the
                    current library catalogue.
                </p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Collection</th>
                            <th class="text-end">Items</th>
                            <th class="text-center">Link resolver</th>
                            <th class="text-end">OA %</th>
                            <th class="text-end">Preprints</th>
                            <th class="text-end">ORCID records</th>
                            <th class="text-center" style="width:140px;">Quality score</th>
                            <th class="small">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scorecards as $card)
                            <tr>
                                <td>
                                    <span class="fw-semibold">
                                        {{ $card->collection_title ?: ('Collection #' . $card->collection_id) }}
                                    </span>
                                    <div class="small text-muted">ID {{ $card->collection_id }}</div>
                                </td>
                                <td class="text-end">{{ number_format($card->item_count) }}</td>
                                <td class="text-center">
                                    @if($card->link_resolver_present)
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Yes</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="fas fa-times me-1"></i>No</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $card->oa_percentage, 1) }}%</td>
                                <td class="text-end">{{ number_format($card->preprints_indexed) }}</td>
                                <td class="text-end">{{ number_format($card->orcid_in_records) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $scoreBadge($card->quality_score) }} fs-6">
                                        {{ number_format((float) $card->quality_score, 1) }}
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    @if($card->updated_at)
                                        {{ \Carbon\Carbon::parse($card->updated_at)->diffForHumans() }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-muted small mt-3 mb-0">
            Quality score is a weighted composite (0-100): open-access share 35%,
            link-resolver presence 25%, ORCID coverage 25%, preprint indexing 15%.
        </p>
    @endif

</div>
@endsection
