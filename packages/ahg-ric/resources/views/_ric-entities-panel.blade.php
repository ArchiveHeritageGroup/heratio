@once
@include('ahg-ric::_ric-api-base')
@endonce
{{-- RiC Entities Panel — included on IO/Actor show pages --}}
{{-- Usage: @include('ahg-ric::_ric-entities-panel', ['record' => $io]) --}}
@php
    $recordId = $record->id ?? null;
@endphp

@if($recordId)
@php
    $recordType = $recordType ?? 'record';
    $ricIsAdmin = \AhgCore\Services\AclService::check($record ?? null, 'update');
@endphp
<section class="mt-4" id="ric-entities-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">
            <i class="fas fa-project-diagram me-1"></i> {{ __('RiC Context') }}
            <a href="https://openric.org" target="_blank" rel="noopener" class="ms-2 small text-decoration-none" title="{{ __('Open RiC contract — see openric.org') }}">
                <i class="fas fa-external-link-alt"></i> <span class="small">{{ __('OpenRiC') }}</span>
            </a>
            @if($ricIsAdmin)
            <a href="{{ url('/admin/ric/validate/' . $recordType . '/' . $recordId) }}" class="ms-2 small text-decoration-none" title="{{ __('Run SHACL validation against the OpenRiC shape set') }}">
                <i class="fas fa-check-double"></i> <span class="small">{{ __('Validate') }}</span>
            </a>
            @endif
        </h2>
        @if($ricIsAdmin)
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('activity')">
                <i class="fas fa-running"></i> {{ __('Add Activity') }}
            </button>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('place')">
                <i class="fas fa-map-marker-alt"></i> {{ __('Add Place') }}
            </button>
            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('rule')">
                <i class="fas fa-gavel"></i> {{ __('Add Rule') }}
            </button>
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('instantiation')">
                <i class="fas fa-file-alt"></i> {{ __('Add Instantiation') }}
            </button>
        </div>
        @endif
    </div>
    <script>window.RIC_IS_ADMIN = @json($ricIsAdmin);</script>

    {{-- Tabs --}}
    <ul class="nav nav-tabs nav-tabs-sm" id="ricEntitiesTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="ric-activities-tab" data-bs-toggle="tab" href="#ric-activities" role="tab">
                <i class="fas fa-running"></i> {{ __('Activities') }} <span class="badge bg-secondary ric-count-activities">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-instantiations-tab" data-bs-toggle="tab" href="#ric-instantiations" role="tab">
                <i class="fas fa-file-alt"></i> {{ __('Instantiations') }} <span class="badge bg-secondary ric-count-instantiations">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-places-tab" data-bs-toggle="tab" href="#ric-places" role="tab">
                <i class="fas fa-map-marker-alt"></i> {{ __('Places') }} <span class="badge bg-secondary ric-count-places">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-rules-tab" data-bs-toggle="tab" href="#ric-rules" role="tab">
                <i class="fas fa-gavel"></i> {{ __('Rules') }} <span class="badge bg-secondary ric-count-rules">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-relations-tab" data-bs-toggle="tab" href="#ric-relations" role="tab">
                <i class="fas fa-link"></i> {{ __('Relations') }} <span class="badge bg-secondary ric-count-relations">0</span>
            </a>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" id="ricEntitiesTabContent">
        {{-- Activities tab --}}
        <div class="tab-pane fade show active" id="ric-activities" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Predicate') }}</th><th></th></tr></thead>
                <tbody id="ric-activities-body"><tr><td colspan="5" class="text-muted">{{ __('Loading...') }}</td></tr></tbody>
            </table>
        </div>
        {{-- Instantiations tab --}}
        <div class="tab-pane fade" id="ric-instantiations" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Carrier') }}</th><th>{{ __('MIME Type') }}</th><th>{{ __('Size') }}</th><th></th></tr></thead>
                <tbody id="ric-instantiations-body"><tr><td colspan="5" class="text-muted">{{ __('Loading...') }}</td></tr></tbody>
            </table>
        </div>
        {{-- Places tab --}}
        <div class="tab-pane fade" id="ric-places" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Coordinates') }}</th><th></th></tr></thead>
                <tbody id="ric-places-body"><tr><td colspan="4" class="text-muted">{{ __('Loading...') }}</td></tr></tbody>
            </table>
        </div>
        {{-- Rules tab --}}
        <div class="tab-pane fade" id="ric-rules" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Jurisdiction') }}</th><th></th></tr></thead>
                <tbody id="ric-rules-body"><tr><td colspan="4" class="text-muted">{{ __('Loading...') }}</td></tr></tbody>
            </table>
        </div>
        {{-- Relations tab --}}
        <div class="tab-pane fade" id="ric-relations" role="tabpanel">
            @include('ahg-ric::_relation-editor', ['recordId' => $recordId])
        </div>
    </div>
</section>

{{-- Entity creation modal --}}
@include('ahg-ric::_ric-entity-modal', ['recordId' => $recordId])

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recordId = {{ $recordId }};

    function renderEmpty() {
        renderActivities([]);
        renderInstantiations([]);
        renderPlaces([]);
        renderRules([]);
        document.querySelector('.ric-count-activities').textContent = 0;
        document.querySelector('.ric-count-instantiations').textContent = 0;
        document.querySelector('.ric-count-places').textContent = 0;
        document.querySelector('.ric-count-rules').textContent = 0;
    }

    // Load entities for this record
    fetch(`${RIC_API_BASE}/records/${recordId}/entities`, { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) { renderEmpty(); return; }
            renderActivities(data.activities || []);
            renderInstantiations(data.instantiations || []);
            renderPlaces(data.places || []);
            renderRules(data.rules || []);
            document.querySelector('.ric-count-activities').textContent = (data.activities || []).length;
            document.querySelector('.ric-count-instantiations').textContent = (data.instantiations || []).length;
            document.querySelector('.ric-count-places').textContent = (data.places || []).length;
            document.querySelector('.ric-count-rules').textContent = (data.rules || []).length;
        })
        .catch(() => { renderEmpty(); });

    // Load relations — public API returns grouped {outgoing, incoming};
    // flatten back for the legacy table renderer.
    fetch(`${RIC_API_BASE}/relations-for/${recordId}`, { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(payload => {
            if (!payload) {
                document.querySelector('.ric-count-relations').textContent = 0;
                if (typeof renderRelations === 'function') renderRelations([]);
                return;
            }
            const flat = [ ...(payload.outgoing || []), ...(payload.incoming || []) ];
            document.querySelector('.ric-count-relations').textContent = flat.length;
            if (typeof renderRelations === 'function') renderRelations(flat);
        })
        .catch(() => {
            document.querySelector('.ric-count-relations').textContent = 0;
            if (typeof renderRelations === 'function') renderRelations([]);
        });

    function renderActivities(items) {
        const body = document.getElementById('ric-activities-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="5" class="text-muted">No activities linked</td></tr>'; return; }
        body.innerHTML = items.map(a => `<tr>
            <td>${a.slug ? `<a href="/admin/ric/entities/activities/${a.slug}">${a.name || 'Unnamed'}</a>` : (a.name || 'Unnamed')}</td>
            <td><span class="badge bg-info">${a.type_id || ''}</span></td>
            <td>${a.date_display || [a.start_date, a.end_date].filter(Boolean).join(' - ') || ''}</td>
            <td><code>${a.rico_predicate || ''}</code></td>
            <td>${window.RIC_IS_ADMIN ? `<button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${a.id})"><i class="fas fa-trash"></i></button>` : ''}</td>
        </tr>`).join('');
    }

    function renderInstantiations(items) {
        const body = document.getElementById('ric-instantiations-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="5" class="text-muted">No instantiations linked</td></tr>'; return; }
        body.innerHTML = items.map(i => `<tr>
            <td>${i.slug ? `<a href="/admin/ric/entities/instantiations/${i.slug}">${i.title || 'Unnamed'}</a>` : (i.title || 'Unnamed')}</td>
            <td><span class="badge bg-secondary">${i.carrier_type || ''}</span></td>
            <td><code>${i.mime_type || ''}</code></td>
            <td>${i.extent_value ? Math.round(i.extent_value / 1024) + ' KB' : ''}</td>
            <td>${window.RIC_IS_ADMIN ? `<button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${i.id})"><i class="fas fa-trash"></i></button>` : ''}</td>
        </tr>`).join('');
    }

    function renderPlaces(items) {
        const body = document.getElementById('ric-places-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="4" class="text-muted">No places linked</td></tr>'; return; }
        body.innerHTML = items.map(p => `<tr>
            <td>${p.slug ? `<a href="/admin/ric/entities/places/${p.slug}">${p.name || 'Unnamed'}</a>` : (p.name || 'Unnamed')}</td>
            <td><span class="badge bg-success">${p.type_id || ''}</span></td>
            <td>${p.latitude && p.longitude ? p.latitude + ', ' + p.longitude : ''}</td>
            <td>${window.RIC_IS_ADMIN ? `<button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${p.id})"><i class="fas fa-trash"></i></button>` : ''}</td>
        </tr>`).join('');
    }

    function renderRules(items) {
        const body = document.getElementById('ric-rules-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="4" class="text-muted">No rules linked</td></tr>'; return; }
        body.innerHTML = items.map(r => `<tr>
            <td>${r.slug ? `<a href="/admin/ric/entities/rules/${r.slug}">${r.title || 'Unnamed'}</a>` : (r.title || 'Unnamed')}</td>
            <td><span class="badge bg-warning text-dark">${r.type_id || ''}</span></td>
            <td>${r.jurisdiction || ''}</td>
            <td>${window.RIC_IS_ADMIN ? `<button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${r.id})"><i class="fas fa-trash"></i></button>` : ''}</td>
        </tr>`).join('');
    }

    window.ricDeleteEntity = function(id) {
        if (!confirm('Delete this RiC entity?')) return;
        fetch(`${RIC_API_BASE}/entities/${id}`, { method: 'DELETE', credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(() => location.reload());
    };
});
</script>
@endif
