{{--
    auth-res::review - three-region authority-resolution review screen (Bootstrap 5).

    Left   : source mention + context packet
    Middle : ranked candidates from ahg_mention_candidate (composite_score DESC)
    Right  : five action buttons (link / link different / create new / park / reject)

    Mirrors the AtoM-side reviewSuccess.php Bootstrap layout.
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
<div class="container-fluid py-4">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('auth-res.queue') }}">{{ __('Authority Resolution') }}</a>
            </li>
            <li class="breadcrumb-item active">{{ __('Mention') }} #{{ (int) $mention->id }}</li>
        </ol>
    </nav>

    <h1 class="mb-3">
        <i class="bi bi-bank me-2"></i>{{ __('Review mention') }}
        <span class="badge bg-{{ $typeBadges[$mention->entity_type] ?? 'secondary' }} ms-2">
            {{ $mention->entity_type }}
        </span>
        <span class="badge bg-{{ $stateBadges[$mention->state] ?? 'secondary' }} ms-1">
            {{ $mention->state }}
        </span>
    </h1>

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

    {{-- Shared form used by the candidate-card radios and the "Link selected" button --}}
    <form id="auth-res-link-form" method="POST"
          action="{{ route('auth-res.review.link', ['mention' => $mention->id]) }}">
        @csrf
    </form>

    <div class="row g-3">

        {{-- ================ LEFT: SOURCE + CONTEXT ================ --}}
        <div class="col-lg-4">

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-quote me-1"></i>{{ __('Source mention') }}</strong>
                    <small class="text-muted">#{{ (int) $mention->id }}</small>
                </div>
                <div class="card-body">
                    <h4 class="mb-2">{{ $mention->entity_value }}</h4>

                    <p class="small mb-2">
                        <strong>{{ __('Source') }}:</strong>
                        @if($ioSlug)
                            <a href="{{ url('/' . $ioSlug) }}" target="_blank" rel="noopener">
                                {{ $mention->io_identifier ?: ('Object #' . (int) $mention->object_id) }}
                                <i class="bi bi-box-arrow-up-right small ms-1"></i>
                            </a>
                        @else
                            <span class="text-muted">{{ $mention->io_identifier ?: ('Object #' . (int) $mention->object_id) }}</span>
                        @endif
                    </p>

                    @if($mention->confidence !== null)
                        <p class="small mb-2 text-muted">
                            {{ __('NER confidence') }}: {{ number_format((float) $mention->confidence, 3) }}
                        </p>
                    @endif

                    <p class="small mb-0 text-muted">
                        {{ __('Promoted') }}: {{ $mention->promoted_at }}
                    </p>

                    @if($mention->linked_actor_id)
                        <p class="small mb-0 text-muted">
                            {{ __('Previously linked') }}: actor #{{ (int) $mention->linked_actor_id }}
                        </p>
                    @endif

                    @if($ambiguityCount > 1)
                        <div class="alert alert-warning small mt-3 mb-0 py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ __('Ambiguity: this value occurs') }}
                            <strong>{{ $ambiguityCount }}</strong>
                            {{ __('times in the source IO - the same string may refer to different entities.') }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="bi bi-text-paragraph me-1"></i>{{ __('Context window') }}</strong>
                </div>
                <div class="card-body">
                    @if(!$context)
                        <p class="text-muted small mb-0">{{ __('No context packet computed for this mention.') }}</p>
                    @else
                        <div class="bg-light p-2 rounded small" style="font-family: monospace; line-height: 1.5;">
                            <span class="text-muted">...{{ $context->surrounding_text_before }}</span><mark class="bg-warning"><strong>{{ $mention->entity_value }}</strong></mark><span class="text-muted">{{ $context->surrounding_text_after }}...</span>
                        </div>
                        @if($context->character_offset_start !== null)
                            <div class="row mt-2 small text-muted">
                                <div class="col-6">
                                    {{ __('Offset') }}: {{ (int) $context->character_offset_start }}-{{ (int) $context->character_offset_end }}
                                </div>
                                <div class="col-6 text-end">
                                    @if($context->paragraph_offset_start !== null)
                                        {{ __('Paragraph') }}: {{ (int) $context->paragraph_offset_start }}-{{ (int) $context->paragraph_offset_end }}
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if($context->ner_model_version)
                            <p class="small text-muted mt-1 mb-0">NER {{ $context->ner_model_version }}</p>
                        @endif
                    @endif
                </div>
            </div>

            @if(!empty($coOccurring))
                <div class="card mb-3">
                    <div class="card-header">
                        <strong><i class="bi bi-people me-1"></i>{{ __('Co-occurring entities') }}</strong>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach(array_slice($coOccurring, 0, 12) as $e)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                <span>
                                    <span class="badge bg-{{ $typeBadges[$e['type'] ?? ''] ?? 'secondary' }} me-1">
                                        {{ $e['type'] ?? '' }}
                                    </span>
                                    {{ $e['value'] ?? '' }}
                                </span>
                                @if(isset($e['distance_chars']) || isset($e['distance_tokens']))
                                    <small class="text-muted">
                                        @if(isset($e['distance_chars']))
                                            &Delta; {{ (int) $e['distance_chars'] }}
                                        @elseif(isset($e['distance_tokens']))
                                            &Delta; {{ (int) $e['distance_tokens'] }}t
                                        @endif
                                    </small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($nearbyDates))
                <div class="card mb-3">
                    <div class="card-header">
                        <strong><i class="bi bi-calendar me-1"></i>{{ __('Nearby dates') }}</strong>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach(array_slice($nearbyDates, 0, 8) as $d)
                            <li class="list-group-item d-flex justify-content-between py-1">
                                <span>{{ $d['value'] ?? '' }}</span>
                                <small class="text-muted">
                                    @if(isset($d['normalized']))
                                        {{ $d['normalized'] }}
                                    @elseif(isset($d['distance_chars']))
                                        &Delta; {{ (int) $d['distance_chars'] }}
                                    @endif
                                </small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($nearbyPlaces))
                <div class="card mb-3">
                    <div class="card-header">
                        <strong><i class="bi bi-geo-alt me-1"></i>{{ __('Nearby places') }}</strong>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach(array_slice($nearbyPlaces, 0, 8) as $p)
                            <li class="list-group-item d-flex justify-content-between py-1">
                                <span>{{ $p['value'] ?? '' }}</span>
                                @if(isset($p['distance_chars']))
                                    <small class="text-muted">&Delta; {{ (int) $p['distance_chars'] }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($roleTokens))
                <div class="card mb-3">
                    <div class="card-header">
                        <strong><i class="bi bi-tag me-1"></i>{{ __('Role language') }}</strong>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach(array_slice($roleTokens, 0, 10) as $t)
                            <li class="list-group-item d-flex justify-content-between py-1">
                                <span>
                                    <span class="badge bg-secondary me-1">{{ $t['kind'] ?? '' }}</span>
                                    {{ $t['token'] ?? '' }}
                                </span>
                                @if(isset($t['distance_chars']))
                                    <small class="text-muted">&Delta; {{ (int) $t['distance_chars'] }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ================ MIDDLE: CANDIDATES ================ --}}
        <div class="col-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">
                    <i class="bi bi-list-ol me-1"></i>{{ __('Ranked candidates') }}
                </h5>
                <span class="badge bg-secondary">
                    {{ $candidates->count() }} {{ __('candidate(s)') }}
                </span>
            </div>

            @forelse($candidates as $i => $c)
                @include('auth-res::_candidate-card', [
                    'candidate' => $c,
                    'isPlace'   => $isPlace,
                    'isFirst'   => $i === 0,
                ])
            @empty
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ __('No candidates generated yet. Run') }}
                    <code>php artisan auth-res:generate-candidates {{ $mention->id }}</code>
                    {{ __('and') }}
                    <code>php artisan auth-res:score-evidence</code>
                    {{ __('for this mention.') }}
                </div>
            @endforelse
        </div>

        {{-- ================ RIGHT: ACTIONS ================ --}}
        <div class="col-lg-3">
            <div class="card mb-3 sticky-top" style="top: 70px;">
                <div class="card-header">
                    <strong><i class="bi bi-hammer me-1"></i>{{ __('Decisions') }}</strong>
                </div>
                <div class="card-body d-grid gap-2">

                    @if($mention->state === 'pending')

                        {{-- 1. Link to selected candidate --}}
                        <button type="submit"
                                form="auth-res-link-form"
                                class="btn btn-success w-100"
                                @if($candidates->isEmpty()) disabled @endif>
                            <i class="bi bi-check-lg me-1"></i>{{ __('Link to selected') }}
                            @if($candidates->isEmpty())
                                <br><small>{{ __('no candidate available') }}</small>
                            @endif
                        </button>

                        {{-- 2. Link to a different existing authority --}}
                        <button type="button"
                                class="btn btn-warning w-100"
                                data-bs-toggle="modal" data-bs-target="#ar-link-different-modal">
                            <i class="bi bi-search me-1"></i>{{ __('Link to different') }}
                        </button>

                        {{-- 3. Create new authority --}}
                        <a href="{{ route('auth-res.review.createNewForm', ['mention' => $mention->id]) }}"
                           class="btn btn-outline-success w-100">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('Create new authority record') }}
                        </a>

                        {{-- 4. Park for later --}}
                        <button type="button"
                                class="btn btn-info w-100"
                                data-bs-toggle="modal" data-bs-target="#ar-park-modal">
                            <i class="bi bi-pause-fill me-1"></i>{{ __('Park') }}
                        </button>

                        {{-- 5. Reject as false positive --}}
                        <button type="button"
                                class="btn btn-outline-danger w-100"
                                data-bs-toggle="modal" data-bs-target="#ar-reject-modal">
                            <i class="bi bi-x-lg me-1"></i>{{ __('Reject') }}
                        </button>

                    @else
                        <div class="alert alert-info mb-0">
                            <strong>{{ __('Already decided') }}</strong><br>
                            <small>{{ __('State') }}: <code>{{ $mention->state }}</code></small>
                        </div>
                    @endif

                    @if($nextMentionId)
                        <hr class="my-2">
                        <a href="{{ route('auth-res.review.show', ['mention' => $nextMentionId]) }}"
                           class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-skip-forward me-1"></i>{{ __('Skip to next pending') }} (#{{ $nextMentionId }})
                        </a>
                    @endif
                    <a href="{{ route('auth-res.queue') }}" class="btn btn-sm btn-link w-100">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to queue') }}
                    </a>
                </div>
            </div>

            <p class="text-muted small">
                {{ __('Every action writes an audit row to') }}
                <code>ahg_mention_decision</code>
                {{ __('and publishes RDF-Star provenance to the decisions graph in Fuseki.') }}
            </p>
        </div>

    </div>
</div>

@include('auth-res::_link-different-modal', ['mention' => $mention])
@include('auth-res::_park-modal',            ['mention' => $mention])
@include('auth-res::_reject-modal',          ['mention' => $mention])

@push('js')
    @if($isPlace)
        <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""></script>
    @endif
    <script nonce="{{ function_exists('csp_nonce') ? csp_nonce() : '' }}">
    document.addEventListener('DOMContentLoaded', function () {

        // ---- Link-different typeahead ----
        var search        = document.getElementById('ar-link-different-search');
        var results       = document.getElementById('ar-link-different-results');
        var hiddenAuth    = document.getElementById('ar-link-different-authority-id');
        var selected      = document.getElementById('ar-link-different-selected');
        var selectedName  = document.getElementById('ar-link-different-selected-name');
        var submitBtn     = document.getElementById('ar-link-different-submit');

        if (search && results) {
            var debounceTimer = null;
            var entityType = search.getAttribute('data-entity-type') || '';
            var lookupUrl  = {!! json_encode(route('auth-res.lookup')) !!};

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function (c) {
                    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
                });
            }

            function doSearch() {
                var q = search.value.trim();
                if (q.length < 2) { results.innerHTML = ''; return; }

                fetch(lookupUrl + '?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(entityType), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    results.innerHTML = '';
                    var rows = (data && data.results) || [];
                    if (!rows.length) {
                        results.innerHTML = '<div class="list-group-item text-muted small">no results</div>';
                        return;
                    }
                    rows.forEach(function (row) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.innerHTML = '<strong>' + escapeHtml(row.display_name || '') + '</strong>'
                            + ' <span class="badge bg-light text-dark border ms-1">' + escapeHtml(row.source || '') + '</span>';
                        btn.addEventListener('click', function () {
                            hiddenAuth.value = row.authority_id || '';
                            selectedName.textContent = row.display_name || '';
                            selected.classList.remove('d-none');
                            submitBtn.disabled = !row.authority_id;
                        });
                        results.appendChild(btn);
                    });
                })
                .catch(function () {
                    results.innerHTML = '<div class="list-group-item text-danger small">lookup failed</div>';
                });
            }

            search.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(doSearch, 250);
            });
        }

        // ---- Leaflet placeholders ----
        // Best-effort: term schema has no lat/long columns so this initialises a
        // world-view map when coordinates are missing, instead of a dead box.
        if (typeof window.L !== 'undefined') {
            document.querySelectorAll('[data-candidate-map="1"]').forEach(function (el) {
                if (el.dataset.leafletInit === '1') return;
                el.dataset.leafletInit = '1';
                try {
                    el.innerHTML = '';
                    el.style.display = 'block';
                    el.style.background = '';
                    var map = window.L.map(el, { zoomControl: false, attributionControl: false }).setView([0, 0], 1);
                    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: 'OpenStreetMap'
                    }).addTo(map);
                } catch (e) {
                    el.textContent = 'Map preview unavailable.';
                }
            });
        }
    });
    </script>
@endpush
@endsection
