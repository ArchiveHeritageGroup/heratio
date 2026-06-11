{{--
    Related records panel - heratio#1214 item 2.

    Surfaces an object's deterministic, catalogue-based graph neighbours
    (records, people & organisations, repositories, subjects/places, accessions,
    and RiC-native entities incl. the exhibitions that include it) drawn from the
    `relation` table via RelationshipService::crossCollectionNeighbours().
    Read-only and AI-free. Unpublished related records are never disclosed to a
    non-admin viewer.

    Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
    Part of Heratio. Licensed under the GNU AGPL v3 or later.
--}}
@php
    $__relGroups = [];
    try {
        $__relId = (int) ($io->id ?? 0);
        if ($__relId > 0 && class_exists(\AhgRic\Services\RelationshipService::class)) {
            $__rel = app(\AhgRic\Services\RelationshipService::class)->crossCollectionNeighbours($__relId);
            $__groups = $__rel['groups'] ?? [];

            // Publication gate: never disclose an unpublished related *record* to
            // a non-admin viewer. Admins (can-update on this description) see all.
            // Reference entities (actors, repositories, terms) have no publication
            // status row, so they are always shown - only record-class items gate.
            $__relAdmin = false;
            try { $__relAdmin = \AhgCore\Services\AclService::check($io ?? null, 'update'); }
            catch (\Throwable $e) { $__relAdmin = false; }

            if (! $__relAdmin && $__groups) {
                $__ids = [];
                foreach ($__groups as $g) {
                    foreach ($g['items'] as $it) { $__ids[(int) $it['id']] = true; }
                }
                $__pub = [];
                if ($__ids && \Illuminate\Support\Facades\Schema::hasTable('status')) {
                    $__pub = array_flip(
                        \Illuminate\Support\Facades\DB::table('status')
                            ->where('type_id', 158)->where('status_id', 160)
                            ->whereIn('object_id', array_keys($__ids))
                            ->pluck('object_id')->map(fn ($v) => (int) $v)->all()
                    );
                }
                $__filtered = [];
                foreach ($__groups as $g) {
                    $__items = [];
                    foreach ($g['items'] as $it) {
                        if (($g['domain'] ?? '') === 'Records & descriptions'
                            && ! isset($__pub[(int) $it['id']])) { continue; }
                        $__items[] = $it;
                    }
                    if ($__items) {
                        $g['items'] = $__items;
                        $g['count'] = count($__items);
                        $__filtered[] = $g;
                    }
                }
                $__groups = $__filtered;
            }
            $__relGroups = $__groups;
        }
    } catch (\Throwable $e) {
        $__relGroups = [];
    }
    $__relTotal = 0;
    foreach ($__relGroups as $g) { $__relTotal += (int) ($g['count'] ?? count($g['items'] ?? [])); }
@endphp

@if(! empty($__relGroups) && $__relTotal > 0)
<section class="mt-4" id="related-records-panel">
    <h2 class="h5 mb-2">
        <i class="fas fa-project-diagram me-1"></i> {{ __('Related records') }}
        <span class="badge bg-secondary">{{ $__relTotal }}</span>
    </h2>
    <p class="text-muted small mb-3">{{ __('Records, people, places and other entities connected to this description across the collection.') }}</p>
    <div class="row g-3">
        @foreach($__relGroups as $g)
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small">{{ __($g['domain'] ?? __('Other')) }}</span>
                    <span class="badge bg-light text-dark">{{ (int) ($g['count'] ?? 0) }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach(array_slice($g['items'], 0, 12) as $it)
                    <li class="list-group-item py-1 px-3 small">
                        @if(! empty($it['slug']))
                            <a href="{{ url('/'.$it['slug']) }}">{{ $it['name'] }}</a>
                        @else
                            {{ $it['name'] }}
                        @endif
                    </li>
                    @endforeach
                    @if((int) ($g['count'] ?? 0) > 12)
                    <li class="list-group-item py-1 px-3 small text-muted">{{ __('and :n more', ['n' => (int) $g['count'] - 12]) }}</li>
                    @endif
                </ul>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif
