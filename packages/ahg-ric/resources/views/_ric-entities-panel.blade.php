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
@endphp
<section class="mt-4" id="ric-entities-panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">
            <i class="fas fa-project-diagram me-1"></i> RiC Context
            <a href="https://openric.org" target="_blank" rel="noopener" class="ms-2 small text-decoration-none" title="Open RiC contract — see openric.org">
                <i class="fas fa-external-link-alt"></i> <span class="small">OpenRiC</span>
            </a>
            <a href="{{ url('/admin/ric/validate/' . $recordType . '/' . $recordId) }}" class="ms-2 small text-decoration-none" title="Run SHACL validation against the OpenRiC shape set">
                <i class="fas fa-check-double"></i> <span class="small">Validate</span>
            </a>
        </h2>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('activity')">
                <i class="fas fa-running"></i> Add Activity
            </button>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('place')">
                <i class="fas fa-map-marker-alt"></i> Add Place
            </button>
            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('rule')">
                <i class="fas fa-gavel"></i> Add Rule
            </button>
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#ricEntityModal" onclick="ricSetEntityType('instantiation')">
                <i class="fas fa-file-alt"></i> Add Instantiation
            </button>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs nav-tabs-sm" id="ricEntitiesTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="ric-activities-tab" data-bs-toggle="tab" href="#ric-activities" role="tab">
                <i class="fas fa-running"></i> Activities <span class="badge bg-secondary ric-count-activities">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-instantiations-tab" data-bs-toggle="tab" href="#ric-instantiations" role="tab">
                <i class="fas fa-file-alt"></i> Instantiations <span class="badge bg-secondary ric-count-instantiations">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-places-tab" data-bs-toggle="tab" href="#ric-places" role="tab">
                <i class="fas fa-map-marker-alt"></i> Places <span class="badge bg-secondary ric-count-places">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-rules-tab" data-bs-toggle="tab" href="#ric-rules" role="tab">
                <i class="fas fa-gavel"></i> Rules <span class="badge bg-secondary ric-count-rules">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ric-relations-tab" data-bs-toggle="tab" href="#ric-relations" role="tab">
                <i class="fas fa-link"></i> Relations <span class="badge bg-secondary ric-count-relations">0</span>
            </a>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" id="ricEntitiesTabContent">
        {{-- Activities tab --}}
        <div class="tab-pane fade show active" id="ric-activities" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>Name</th><th>Type</th><th>Dates</th><th>Predicate</th><th></th></tr></thead>
                <tbody id="ric-activities-body"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
        {{-- Instantiations tab --}}
        <div class="tab-pane fade" id="ric-instantiations" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>Title</th><th>Carrier</th><th>MIME Type</th><th>Size</th><th></th></tr></thead>
                <tbody id="ric-instantiations-body"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
        {{-- Places tab --}}
        <div class="tab-pane fade" id="ric-places" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>Name</th><th>Type</th><th>Coordinates</th><th></th></tr></thead>
                <tbody id="ric-places-body"><tr><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
        {{-- Rules tab --}}
        <div class="tab-pane fade" id="ric-rules" role="tabpanel">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>Title</th><th>Type</th><th>Jurisdiction</th><th></th></tr></thead>
                <tbody id="ric-rules-body"><tr><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
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

    // Load entities for this record
    fetch(`${RIC_API_BASE}/records/${recordId}/entities`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            renderActivities(data.activities || []);
            renderInstantiations(data.instantiations || []);
            renderPlaces(data.places || []);
            renderRules(data.rules || []);
            document.querySelector('.ric-count-activities').textContent = (data.activities || []).length;
            document.querySelector('.ric-count-instantiations').textContent = (data.instantiations || []).length;
            document.querySelector('.ric-count-places').textContent = (data.places || []).length;
            document.querySelector('.ric-count-rules').textContent = (data.rules || []).length;
        })
        .catch(() => {});

    // Load relations — public API returns grouped {outgoing, incoming};
    // flatten back for the legacy table renderer.
    fetch(`${RIC_API_BASE}/relations-for/${recordId}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(payload => {
            const flat = [ ...(payload.outgoing || []), ...(payload.incoming || []) ];
            document.querySelector('.ric-count-relations').textContent = flat.length;
            if (typeof renderRelations === 'function') renderRelations(flat);
        })
        .catch(() => {});

    function renderActivities(items) {
        const body = document.getElementById('ric-activities-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="5" class="text-muted">No activities linked</td></tr>'; return; }
        body.innerHTML = items.map(a => `<tr>
            <td>${a.slug ? `<a href="/admin/ric/entities/activities/${a.slug}">${a.name || 'Unnamed'}</a>` : (a.name || 'Unnamed')}</td>
            <td><span class="badge bg-info">${a.type_id || ''}</span></td>
            <td>${a.date_display || [a.start_date, a.end_date].filter(Boolean).join(' - ') || ''}</td>
            <td><code>${a.rico_predicate || ''}</code></td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${a.id})"><i class="fas fa-trash"></i></button></td>
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
            <td><button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${i.id})"><i class="fas fa-trash"></i></button></td>
        </tr>`).join('');
    }

    function renderPlaces(items) {
        const body = document.getElementById('ric-places-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="4" class="text-muted">No places linked</td></tr>'; return; }
        body.innerHTML = items.map(p => `<tr>
            <td>${p.slug ? `<a href="/admin/ric/entities/places/${p.slug}">${p.name || 'Unnamed'}</a>` : (p.name || 'Unnamed')}</td>
            <td><span class="badge bg-success">${p.type_id || ''}</span></td>
            <td>${p.latitude && p.longitude ? p.latitude + ', ' + p.longitude : ''}</td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${p.id})"><i class="fas fa-trash"></i></button></td>
        </tr>`).join('');
    }

    function renderRules(items) {
        const body = document.getElementById('ric-rules-body');
        if (!items.length) { body.innerHTML = '<tr><td colspan="4" class="text-muted">No rules linked</td></tr>'; return; }
        body.innerHTML = items.map(r => `<tr>
            <td>${r.slug ? `<a href="/admin/ric/entities/rules/${r.slug}">${r.title || 'Unnamed'}</a>` : (r.title || 'Unnamed')}</td>
            <td><span class="badge bg-warning text-dark">${r.type_id || ''}</span></td>
            <td>${r.jurisdiction || ''}</td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="ricDeleteEntity(${r.id})"><i class="fas fa-trash"></i></button></td>
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
