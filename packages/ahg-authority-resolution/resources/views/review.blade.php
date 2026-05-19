{{--
    auth-res::review - three-region authority resolution review screen.

    Left:    mention + evidence packet
    Middle:  ranked candidates with per-dimension evidence
    Right:   five action buttons (link / link-different / create-new / park / reject)

    All Tailwind 4. Leaflet loaded on PLACE/GPE/LOC review screens only.
--}}
@extends('theme::layouts.1col')

@section('title', 'Review mention #' . $mention->id)

@push('css')
    @if($isPlace)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin="">
    @endif
@endpush

@section('content')
<div class="px-4 py-6 max-w-screen-2xl mx-auto">

    {{-- Header / breadcrumb --}}
    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <p class="text-xs text-slate-500">
                <a href="{{ route('auth-res.queue') }}" class="hover:underline">&larr; Review queue</a>
            </p>
            <h1 class="text-xl font-semibold text-slate-900 mt-1">
                Mention #{{ $mention->id }}
                <span class="text-slate-400 font-normal text-sm">({{ $mention->state }})</span>
            </h1>
        </div>
        @if($nextMentionId)
            <a href="{{ route('auth-res.review.show', ['mention' => $nextMentionId]) }}"
               class="text-sm text-indigo-700 hover:underline">
                Skip to next pending &rarr;
            </a>
        @endif
    </div>

    @if(session('notice'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('notice') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Three regions: left mention/evidence, middle candidates, right actions --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ============================ LEFT ============================ --}}
        <section class="lg:col-span-3 space-y-4" data-region="mention-evidence">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                        {{ $mention->entity_type }}
                    </span>
                    @if($mention->confidence !== null)
                        <span class="text-xs text-slate-500">conf {{ number_format((float) $mention->confidence, 3) }}</span>
                    @endif
                </div>
                <h2 class="text-lg font-semibold text-slate-900 break-words">{{ $mention->entity_value }}</h2>

                <dl class="mt-3 space-y-1 text-xs text-slate-600">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Source IO</dt>
                        <dd>
                            @if($ioSlug)
                                <a href="{{ url('/' . $ioSlug) }}" target="_blank" rel="noopener"
                                   class="text-indigo-700 hover:underline">
                                    {{ $mention->io_identifier ?: '#' . $mention->object_id }}
                                </a>
                            @else
                                {{ $mention->io_identifier ?: '#' . $mention->object_id }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Promoted</dt>
                        <dd>{{ $mention->promoted_at }}</dd>
                    </div>
                    @if($mention->linked_actor_id)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Previously linked</dt>
                            <dd>actor #{{ $mention->linked_actor_id }}</dd>
                        </div>
                    @endif
                </dl>

                @if($ambiguityCount > 1)
                    <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        Ambiguity: this value occurs <strong>{{ $ambiguityCount }}</strong> times
                        in the source IO - the same string may refer to different entities.
                    </div>
                @endif
            </div>

            {{-- Evidence packet: surrounding text with mention highlighted --}}
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Surrounding text</h3>
                @if($context)
                    <p class="text-sm leading-relaxed text-slate-700">
                        <span class="text-slate-400">{{ $context->surrounding_text_before }}</span><mark class="bg-yellow-200 text-slate-900 px-1 rounded">{{ $mention->entity_value }}</mark><span class="text-slate-400">{{ $context->surrounding_text_after }}</span>
                    </p>
                    @if($context->character_offset_start !== null)
                        <p class="mt-2 text-[10px] text-slate-400">
                            char offset {{ $context->character_offset_start }}-{{ $context->character_offset_end }}
                            @if($context->paragraph_offset_start !== null)
                                / paragraph {{ $context->paragraph_offset_start }}-{{ $context->paragraph_offset_end }}
                            @endif
                            @if($context->ner_model_version)
                                / NER {{ $context->ner_model_version }}
                            @endif
                        </p>
                    @endif
                @else
                    <p class="text-sm text-slate-400 italic">No context packet derived yet.</p>
                @endif
            </div>

            {{-- Co-occurring entities --}}
            @if(!empty($coOccurring))
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Co-occurring entities</h3>
                    <ul class="text-xs space-y-1">
                        @foreach(array_slice($coOccurring, 0, 12) as $c)
                            <li class="flex justify-between gap-2">
                                <span class="text-slate-700 truncate">{{ $c['value'] ?? '' }}</span>
                                <span class="text-slate-400 shrink-0">
                                    {{ $c['type'] ?? '' }}
                                    @if(isset($c['distance_tokens']))
                                        / {{ (int) $c['distance_tokens'] }}t
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Nearby dates --}}
            @if(!empty($nearbyDates))
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Nearby dates</h3>
                    <ul class="text-xs space-y-1">
                        @foreach(array_slice($nearbyDates, 0, 8) as $d)
                            <li class="flex justify-between gap-2">
                                <span class="text-slate-700">{{ $d['value'] ?? '' }}</span>
                                <span class="text-slate-400">
                                    @if(isset($d['normalized']))
                                        {{ $d['normalized'] }}
                                    @endif
                                    @if(isset($d['distance_tokens']))
                                        / {{ (int) $d['distance_tokens'] }}t
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Nearby places --}}
            @if(!empty($nearbyPlaces))
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Nearby places</h3>
                    <ul class="text-xs space-y-1">
                        @foreach(array_slice($nearbyPlaces, 0, 8) as $p)
                            <li class="text-slate-700">{{ $p['value'] ?? '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Role-language tokens --}}
            @if(!empty($roleTokens))
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Role-language tokens</h3>
                    <ul class="text-xs space-y-1">
                        @foreach(array_slice($roleTokens, 0, 10) as $t)
                            <li class="flex justify-between gap-2">
                                <span class="text-slate-700">{{ $t['token'] ?? '' }}</span>
                                <span class="text-slate-400">{{ $t['kind'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        {{-- ============================ MIDDLE ============================ --}}
        <section class="lg:col-span-6 space-y-3" data-region="candidates">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">
                    Ranked candidates
                    <span class="text-slate-400 font-normal text-sm">({{ $candidates->count() }})</span>
                </h2>
                <span class="text-xs text-slate-500">sorted by composite score</span>
            </div>

            <form id="auth-res-link-form" method="POST"
                  action="{{ route('auth-res.review.link', ['mention' => $mention->id]) }}">
                @csrf
            </form>

            @forelse($candidates as $i => $c)
                @include('auth-res::_candidate-card', [
                    'candidate' => $c,
                    'isPlace' => $isPlace,
                    'isFirst' => $i === 0,
                ])
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
                    No candidates generated yet. Run
                    <code class="rounded bg-white px-1 py-0.5 text-xs">php artisan auth-res:generate-candidates {{ $mention->id }}</code>
                    to populate this list.
                </div>
            @endforelse
        </section>

        {{-- ============================ RIGHT ============================ --}}
        <aside class="lg:col-span-3 space-y-3" data-region="actions">
            <div class="rounded-lg border border-slate-200 bg-white p-4 sticky top-4 space-y-2">
                <h2 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Actions</h2>

                {{-- 1. Link to selected candidate (submits the radio-form) --}}
                <button type="submit" form="auth-res-link-form"
                        @if($candidates->isEmpty()) disabled @endif
                        class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:bg-slate-300 disabled:cursor-not-allowed">
                    Link to selected candidate
                </button>

                {{-- 2. Link to a different existing authority --}}
                <button type="button"
                        data-auth-res-open="link-different"
                        class="w-full rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    Link to different existing authority
                </button>

                {{-- 3. Create new authority (Task 6: link to pre-filled form) --}}
                <a href="{{ route('auth-res.review.createNewForm', ['mention' => $mention->id]) }}"
                   class="block w-full rounded-md border border-indigo-400 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-100 text-center">
                    Create new authority record
                    <span class="block text-[10px] font-normal mt-0.5">Pre-fill from VIAF / Wikidata / GeoNames (if enabled)</span>
                </a>

                {{-- 4. Park for later --}}
                <button type="button"
                        data-auth-res-open="park"
                        class="w-full rounded-md border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                    Park for later
                </button>

                {{-- 5. Reject as false positive (Task 9: captures rejection_reason for NER retraining) --}}
                <button type="button"
                        data-auth-res-open="reject"
                        class="w-full rounded-md border border-rose-400 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-100">
                    Reject as false positive
                </button>

                <p class="text-[10px] text-slate-400 leading-relaxed pt-2 border-t border-slate-100">
                    Every action writes an audit row to <code>ahg_mention_decision</code> and
                    publishes RDF-Star provenance to the decisions graph in Fuseki.
                </p>
            </div>
        </aside>
    </div>
</div>

@include('auth-res::_link-different-modal', ['mention' => $mention])
@include('auth-res::_park-modal', ['mention' => $mention])
@include('auth-res::_reject-modal', ['mention' => $mention])

@push('js')
    @if($isPlace)
        <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""></script>
    @endif
    <script nonce="{{ function_exists('csp_nonce') ? csp_nonce() : '' }}">
    (function () {
        'use strict';

        // Modal open / close (Tailwind, no Bootstrap JS).
        function setVisible(modal, visible) {
            if (!modal) return;
            modal.classList.toggle('hidden', !visible);
            modal.classList.toggle('flex', visible);
        }
        document.querySelectorAll('[data-auth-res-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = btn.getAttribute('data-auth-res-open');
                var modal = document.getElementById('auth-res-' + name + '-modal');
                setVisible(modal, true);
            });
        });
        document.querySelectorAll('[data-auth-res-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = btn.getAttribute('data-auth-res-close');
                var modal = document.getElementById('auth-res-' + name + '-modal');
                setVisible(modal, false);
            });
        });

        // ---- Typeahead for link-different --------------------------------
        var input = document.getElementById('auth-res-link-different-input');
        var resultsBox = document.getElementById('auth-res-link-different-results');
        var hiddenId = document.getElementById('auth-res-link-different-authority-id');
        var chosen = document.getElementById('auth-res-link-different-chosen');
        var chosenName = document.getElementById('auth-res-link-different-chosen-name');
        var submitBtn = document.getElementById('auth-res-link-different-submit');

        if (input && resultsBox) {
            var lookupUrl = {!! json_encode(route('auth-res.lookup')) !!};
            var entityType = input.getAttribute('data-entity-type') || '';
            var debounceTimer = null;

            input.addEventListener('input', function () {
                var q = input.value.trim();
                if (q.length < 2) {
                    resultsBox.classList.add('hidden');
                    resultsBox.innerHTML = '';
                    return;
                }
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    fetch(lookupUrl + '?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(entityType), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        var rows = (json && json.results) || [];
                        if (!rows.length) {
                            resultsBox.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">No matches.</div>';
                            resultsBox.classList.remove('hidden');
                            return;
                        }
                        resultsBox.innerHTML = rows.map(function (r) {
                            var aid = r.authority_id != null ? r.authority_id : '';
                            var name = (r.display_name || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
                            var src = (r.source || '').replace(/</g, '&lt;');
                            return '<button type="button" data-aid="' + aid + '" data-name="' + name + '"' +
                                ' class="block w-full text-left px-3 py-2 hover:bg-slate-50">' +
                                '<span class="text-sm font-medium text-slate-900">' + name + '</span>' +
                                ' <span class="text-[10px] text-slate-500">' + src + ' / #' + aid + '</span>' +
                                '</button>';
                        }).join('');
                        resultsBox.classList.remove('hidden');
                        resultsBox.querySelectorAll('button[data-aid]').forEach(function (b) {
                            b.addEventListener('click', function () {
                                hiddenId.value = b.getAttribute('data-aid');
                                chosenName.textContent = b.getAttribute('data-name');
                                chosen.classList.remove('hidden');
                                submitBtn.disabled = false;
                                resultsBox.classList.add('hidden');
                            });
                        });
                    })
                    .catch(function () {
                        resultsBox.innerHTML = '<div class="px-3 py-2 text-xs text-rose-600">Lookup failed.</div>';
                        resultsBox.classList.remove('hidden');
                    });
                }, 220);
            });
        }

        // ---- Map preview (Leaflet, OSM tiles) ----------------------------
        // Best-effort: term schema has no lat/long columns and property is
        // empty for place terms, so this just initialises a world-view map
        // when coordinates are missing instead of leaving a dead box.
        if (typeof window.L !== 'undefined') {
            document.querySelectorAll('[data-candidate-map="1"]').forEach(function (el) {
                if (el.dataset.leafletInit === '1') return;
                el.dataset.leafletInit = '1';
                try {
                    el.innerHTML = '';
                    el.classList.remove('flex', 'items-center', 'justify-center', 'border-dashed');
                    var map = window.L.map(el).setView([0, 0], 1);
                    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: '(c) OpenStreetMap'
                    }).addTo(map);
                } catch (e) {
                    el.textContent = 'Map preview unavailable.';
                }
            });
        }
    })();
    </script>
@endpush
@endsection
