{{--
    auth-res::_candidate-card - one candidate (display name + score + evidence table)

    Bootstrap 5 chrome to match the rest of the Heratio admin UI.

    Args:
        $candidate : object - row from ahg_mention_candidate with
                     evidence_signals_decoded + evidence_data_decoded attached
        $isPlace   : bool   - PLACE/GPE/LOC mention? (controls map preview)
        $isFirst   : bool   - first candidate (radio is pre-selected, border highlighted)
--}}
@php
    $signals = $candidate->evidence_signals_decoded ?? [];
    $data    = $candidate->evidence_data_decoded ?? [];

    // Dimension order per spec (persons/orgs vs places).
    $isPlaceCand = in_array(($candidate->candidate_source ?? ''), ['mysql_term', 'fuseki_place'], true) || $isPlace;
    $dimensions = $isPlaceCand
        ? ['hierarchical', 'document_prior', 'co_occurring', 'scale', 'conflict']
        : ['temporal', 'geographic', 'relational', 'role', 'conflict'];

    $sourceBadge = [
        'mysql_actor'   => ['cls' => 'primary', 'label' => 'Local actor'],
        'fuseki_agent'  => ['cls' => 'info',    'label' => 'Fuseki agent'],
        'mysql_term'    => ['cls' => 'success', 'label' => 'Local place'],
        'fuseki_place'  => ['cls' => 'info',    'label' => 'Fuseki place'],
    ];
    $srcCfg = $sourceBadge[$candidate->candidate_source] ?? ['cls' => 'secondary', 'label' => $candidate->candidate_source];

    $compositeScore = $candidate->composite_score !== null ? (float) $candidate->composite_score : null;

    // Build the read-only "view authority" link. Prefer the slug-based
    // /{slug} catch-all URL (the same route IO show pages resolve through) -
    // it is the only authority URL guaranteed to resolve in Heratio. The
    // numeric /actor/{id} and /taxonomy/term/{id} routes are not registered,
    // so fall through to those only when there is genuinely no slug.
    $authoritySlug = $candidate->authority_slug ?? null;
    $authorityName = trim((string) ($candidate->candidate_display_name ?? '')) ?: null;
    $authorityUrl = null;
    if ($candidate->candidate_authority_id) {
        if ($authoritySlug) {
            $authorityUrl = url('/' . $authoritySlug);
        }
    } elseif ($candidate->candidate_fuseki_uri) {
        $authorityUrl = $candidate->candidate_fuseki_uri;
    }
@endphp

<div class="card mb-3 {{ $isFirst ? 'border-success' : '' }}" data-candidate-card="{{ $candidate->id }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <div class="form-check m-0">
                <input class="form-check-input" type="radio" name="candidate_id"
                       value="{{ $candidate->id }}"
                       form="auth-res-link-form"
                       id="auth-res-cand-{{ $candidate->id }}"
                       {{ $isFirst ? 'checked' : '' }}>
            </div>
            <label class="m-0" for="auth-res-cand-{{ $candidate->id }}">
                <strong>{{ $candidate->candidate_display_name }}</strong>
            </label>
            @if($isFirst)
                <span class="badge bg-success ms-1"><i class="bi bi-star-fill me-1"></i>top</span>
            @endif
            <span class="badge bg-{{ $srcCfg['cls'] }} ms-1">{{ $srcCfg['label'] }}</span>
        </div>
        <div class="text-end">
            <small class="text-muted d-block">rank #{{ (int) $candidate->rank_position }}</small>
            @if($compositeScore !== null)
                <strong class="text-success">{{ number_format($compositeScore, 3) }}</strong>
            @else
                <small class="text-muted">no score</small>
            @endif
        </div>
    </div>

    <div class="card-body py-2">
        <div class="d-flex justify-content-between mb-2 small">
            <span>
                @if($candidate->candidate_authority_id !== null)
                    <i class="bi bi-person-badge text-muted me-1"></i>
                    @if($authorityUrl)
                        <a href="{{ $authorityUrl }}" target="_blank" rel="noopener">{{ $authorityName ?? ('authority #' . (int) $candidate->candidate_authority_id) }}</a>
                    @elseif($authorityName)
                        {{ $authorityName }}
                    @else
                        authority #{{ (int) $candidate->candidate_authority_id }}
                    @endif
                @elseif($candidate->candidate_fuseki_uri)
                    <i class="bi bi-link-45deg text-muted me-1"></i>
                    <code class="small">{{ $candidate->candidate_fuseki_uri }}</code>
                @endif
            </span>
            <span class="text-muted">
                name sim: {{ number_format((float) $candidate->name_similarity_score, 3) }}
            </span>
        </div>

        @if(!empty($signals))
            <table class="table table-sm mb-2">
                <thead class="visually-hidden">
                    <tr><th>{{ __('Dimension') }}</th><th>{{ __('Signal') }}</th><th>{{ __('Detail') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($dimensions as $dim)
                        @if(array_key_exists($dim, $signals))
                            @include('auth-res::_evidence-row', [
                                'dimension' => $dim,
                                'signal'    => $signals[$dim],
                                'data'      => $data[$dim] ?? null,
                            ])
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted small mb-2 fst-italic">No evidence signals computed (run <code>auth-res:score-evidence</code>).</p>
        @endif

        @if($authorityUrl)
            <a href="{{ $authorityUrl }}" target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye me-1"></i>{{ $authorityName ?? __('View authority') }}
            </a>
        @elseif($candidate->candidate_authority_id !== null)
            <span class="text-muted small">
                <i class="bi bi-person-badge me-1"></i>{{ $authorityName ?? ('authority #' . (int) $candidate->candidate_authority_id) }}
            </span>
        @else
            <span class="text-muted small">No authority link available</span>
        @endif

        @if($isPlaceCand)
            {{-- Best-effort map preview. Leaflet is loaded only on PLACE review screens
                 by the parent page. When candidate has no coordinates, init shows a
                 world-view map instead of leaving a dead box. --}}
            <div id="auth-res-map-{{ $candidate->id }}"
                 class="mt-2"
                 data-candidate-map="1"
                 data-display-name="{{ $candidate->candidate_display_name }}"
                 style="height: 160px; max-width: 100%; overflow: hidden; position: relative;
                        border: 1px solid #dee2e6; border-radius: 4px;
                        display: flex; align-items: center; justify-content: center;
                        background: #f8f9fa; font-size: 0.75rem; color: #6c757d;">
                Map preview (no coordinates available)
            </div>
        @endif
    </div>

    <div class="card-footer d-flex gap-1 flex-wrap py-2">
        <form method="post"
              action="{{ route('auth-res.review.link', ['mention' => $candidate->mention_id ?? request()->route('mention')]) }}"
              class="d-inline">
            @csrf
            <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
            <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-check-lg me-1"></i>{{ __('Link to this') }}
            </button>
        </form>
    </div>
</div>
