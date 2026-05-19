{{--
    auth-res::_candidate-card - one candidate (display name + score + evidence table)

    Args:
        $candidate : object - row from ahg_mention_candidate with
                     evidence_signals_decoded + evidence_data_decoded attached
        $isPlace   : bool   - PLACE/GPE/LOC mention? (controls map preview)
        $isFirst   : bool   - first candidate (radio is pre-selected)
--}}
@php
    $signals = $candidate->evidence_signals_decoded ?? [];
    $data = $candidate->evidence_data_decoded ?? [];

    // Dimension order per spec (persons/orgs vs places).
    $isPlaceCand = in_array(($candidate->candidate_source ?? ''), ['mysql_term', 'fuseki_place'], true) || $isPlace;
    $dimensions = $isPlaceCand
        ? ['hierarchical', 'document_prior', 'co_occurring', 'scale', 'conflict']
        : ['temporal', 'geographic', 'relational', 'role', 'conflict'];

    $sourceLabel = match($candidate->candidate_source) {
        'mysql_actor' => 'Local actor',
        'mysql_term' => 'Local place',
        'fuseki_agent' => 'Fuseki agent',
        'fuseki_place' => 'Fuseki place',
        default => $candidate->candidate_source,
    };
    $sourceCls = match($candidate->candidate_source) {
        'mysql_actor' => 'bg-blue-100 text-blue-800',
        'mysql_term' => 'bg-teal-100 text-teal-800',
        'fuseki_agent' => 'bg-purple-100 text-purple-800',
        'fuseki_place' => 'bg-pink-100 text-pink-800',
        default => 'bg-slate-100 text-slate-700',
    };

    // Build the read-only "view authority" link.
    $authorityUrl = null;
    if ($candidate->candidate_authority_id) {
        if ($candidate->candidate_source === 'mysql_actor' || $candidate->candidate_source === 'fuseki_agent') {
            $authorityUrl = url('/actor/' . (int) $candidate->candidate_authority_id);
        } elseif ($candidate->candidate_source === 'mysql_term' || $candidate->candidate_source === 'fuseki_place') {
            $authorityUrl = url('/taxonomy/term/' . (int) $candidate->candidate_authority_id);
        }
    } elseif ($candidate->candidate_fuseki_uri) {
        $authorityUrl = $candidate->candidate_fuseki_uri;
    }
@endphp

<label class="block rounded-lg border border-slate-200 bg-white p-4 cursor-pointer hover:border-indigo-400 hover:shadow-sm transition"
       data-candidate-card="{{ $candidate->id }}">
    <div class="flex items-start gap-3">
        <input type="radio" name="candidate_id" value="{{ $candidate->id }}"
               form="auth-res-link-form"
               class="mt-1 h-4 w-4 text-indigo-600 border-slate-300"
               {{ $isFirst ? 'checked' : '' }}>

        <div class="flex-1 min-w-0">
            <div class="flex items-baseline justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-slate-900 truncate">
                        {{ $candidate->candidate_display_name }}
                    </h3>
                    <div class="mt-1 flex items-center gap-2 text-xs">
                        <span class="rounded-md px-2 py-0.5 font-medium {{ $sourceCls }}">{{ $sourceLabel }}</span>
                        <span class="text-slate-500">Rank #{{ $candidate->rank_position }}</span>
                        @if($candidate->candidate_authority_id)
                            <span class="text-slate-400">id={{ $candidate->candidate_authority_id }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Composite</div>
                    <div class="text-2xl font-semibold text-indigo-700 leading-tight">
                        {{ number_format((float) ($candidate->composite_score ?? 0), 3) }}
                    </div>
                    <div class="text-[10px] text-slate-400">name: {{ number_format((float) $candidate->name_similarity_score, 3) }}</div>
                </div>
            </div>

            {{-- Evidence dimension table --}}
            @if(!empty($signals))
                <div class="mt-3 rounded-md border border-slate-100 overflow-hidden">
                    <table class="min-w-full divide-y divide-slate-100">
                        <tbody class="divide-y divide-slate-100">
                            @foreach($dimensions as $dim)
                                @if(array_key_exists($dim, $signals))
                                    @include('auth-res::_evidence-row', [
                                        'dimension' => $dim,
                                        'signal' => $signals[$dim],
                                        'data' => $data[$dim] ?? null,
                                    ])
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-3 text-xs text-slate-500 italic">No evidence signals computed yet (run auth-res:score-evidence).</p>
            @endif

            {{-- Footer: authority link + (place) map preview --}}
            <div class="mt-3 flex items-center justify-between text-xs">
                @if($authorityUrl)
                    <a href="{{ $authorityUrl }}" target="_blank" rel="noopener"
                       class="text-indigo-700 hover:underline">
                        View full authority record &rarr;
                    </a>
                @else
                    <span class="text-slate-400">No authority link available</span>
                @endif

                @if($isPlaceCand && $candidate->candidate_authority_id)
                    <span class="text-slate-400">place candidate (no coordinates on file)</span>
                @endif
            </div>

            @if($isPlaceCand)
                {{-- Best-effort map preview. The term table has no lat/long
                     columns and the property table is empty for place terms,
                     so we render an empty placeholder + the "no coordinates"
                     hint. Leaflet is loaded only on PLACE review screens. --}}
                <div id="auth-res-map-{{ $candidate->id }}"
                     class="mt-3 rounded-md border border-dashed border-slate-200 bg-slate-50 h-32 flex items-center justify-center text-xs text-slate-500"
                     data-candidate-map="1"
                     data-display-name="{{ $candidate->candidate_display_name }}">
                    Map preview (no coordinates available)
                </div>
            @endif
        </div>
    </div>
</label>
