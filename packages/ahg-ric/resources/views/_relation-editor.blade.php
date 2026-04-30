@once
@include('ahg-ric::_ric-api-base')
@endonce
{{-- RiC Relation Editor Widget --}}
@php
    $recordId = $recordId ?? null;
    $ricRelationTypes = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
        ->where('taxonomy', 'ric_relation_type')->where('is_active', 1)->orderBy('sort_order')->get();
    $ricCertaintyLevels = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
        ->where('taxonomy', 'certainty_level')->where('is_active', 1)->orderBy('sort_order')->get();
@endphp

<div id="ric-relation-editor">
    <p class="small text-muted mb-2">
        <i class="fas fa-link me-1"></i>
        Predicate vocabulary follows the <a href="https://openric.org/spec/mapping.html" target="_blank" rel="noopener">OpenRiC</a> mapping —
        each relation written here also serialises as canonical <code>rico:*</code> in the RDF/JSON-LD/Turtle exports.
    </p>
    <table class="table table-sm table-striped mb-2">
        <thead>
            <tr>
                <th>{{ __('Direction') }}</th>
                <th>{{ __('Related Entity') }}</th>
                <th>{{ __('Relation Type') }}</th>
                <th>{{ __('Dates') }}</th>
                <th>{{ __('Certainty') }}</th>
                <th>{{ __('Evidence') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="ric-relations-body"><tr><td colspan="7" class="text-muted">Loading...</td></tr></tbody>
    </table>

    <div class="card card-body bg-light p-2 mt-2">
        <h6 class="mb-2" id="ric-rel-form-title"><i class="fas fa-plus-circle"></i> Add Relation</h6>
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label form-label-sm">{{ __('Target Entity') }}</label>
                <input type="text" id="ric-rel-target-search" class="form-control form-control-sm" placeholder="{{ __('Search entities...') }}" autocomplete="off">
                <input type="hidden" id="ric-rel-target-id">
                <div id="ric-rel-autocomplete" class="list-group position-absolute" style="z-index:1050; display:none; max-height:200px; overflow-y:auto;"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">{{ __('Relation Type') }}</label>
                <select id="ric-rel-type" class="form-select form-select-sm">
                    <option value="">-- Select --</option>
                    @foreach($ricRelationTypes as $rt)
                    <option value="{{ $rt->code }}">{{ $rt->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">{{ __('Start') }}</label>
                <input type="date" id="ric-rel-start" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">{{ __('End') }}</label>
                <input type="date" id="ric-rel-end" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <button type="button" id="ric-rel-submit" class="btn btn-sm btn-primary w-100" onclick="ricSubmitRelation()">
                    <i class="fas fa-link"></i>
                </button>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">{{ __('Certainty') }}</label>
                <select id="ric-rel-certainty" class="form-select form-select-sm">
                    <option value="">-- Unspecified --</option>
                    @foreach($ricCertaintyLevels as $cl)
                    <option value="{{ $cl->code }}">{{ $cl->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label form-label-sm">{{ __('Evidence') }}</label>
                <input type="text" id="ric-rel-evidence" class="form-control form-control-sm" placeholder="{{ __('Source citation, record identifier, or note supporting this relation') }}">
            </div>
            <div class="col-md-1">
                <button type="button" id="ric-rel-cancel" class="btn btn-sm btn-outline-secondary w-100 d-none" onclick="ricCancelEdit()" title="{{ __('Cancel edit') }}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recordId = {{ $recordId ?? 0 }};

    const searchInput = document.getElementById('ric-rel-target-search');
    const acList = document.getElementById('ric-rel-autocomplete');
    const targetIdInput = document.getElementById('ric-rel-target-id');
    const typeSelect = document.getElementById('ric-rel-type');
    const startInput = document.getElementById('ric-rel-start');
    const endInput = document.getElementById('ric-rel-end');
    const certaintyInput = document.getElementById('ric-rel-certainty');
    const evidenceInput = document.getElementById('ric-rel-evidence');
    const submitBtn = document.getElementById('ric-rel-submit');
    const cancelBtn = document.getElementById('ric-rel-cancel');
    const formTitle = document.getElementById('ric-rel-form-title');

    let editingId = null;
    let debounce;

    // ------------------------------------------------------------
    // Autocomplete
    // ------------------------------------------------------------
    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { acList.style.display = 'none'; return; }
        debounce = setTimeout(() => {
            fetch(`${RIC_API_BASE}/autocomplete?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(items => {
                    if (!items.length) { acList.style.display = 'none'; return; }
                    acList.innerHTML = items.map(i =>
                        `<a href="#" class="list-group-item list-group-item-action py-1 px-2" data-id="${i.id}" data-type="${i.type}">
                            <span class="badge bg-secondary me-1">${i.type}</span> ${i.label}
                        </a>`
                    ).join('');
                    acList.style.display = '';
                    acList.querySelectorAll('a').forEach(a => {
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            targetIdInput.value = this.dataset.id;
                            searchInput.value = this.textContent.trim();
                            acList.style.display = 'none';
                        });
                    });
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!acList.contains(e.target) && e.target !== searchInput) acList.style.display = 'none';
    });

    // ------------------------------------------------------------
    // Render relations list
    // ------------------------------------------------------------
    window.renderRelations = function(relations) {
        const body = document.getElementById('ric-relations-body');
        if (!relations.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-muted">No relations</td></tr>';
            return;
        }
        body.innerHTML = relations.map(r => {
            const payload = encodeURIComponent(JSON.stringify({
                id: r.id,
                direction: r.direction,
                target_id: r.target_id,
                target_name: r.target_name || 'Entity #' + r.target_id,
                target_type: r.target_type || '',
                relation_type: r.dropdown_code || '',
                start_date: r.start_date || '',
                end_date: r.end_date || '',
                certainty: r.certainty || '',
                evidence: r.evidence || ''
            }));
            // Only outgoing relations are editable — inverse side comes with a system-inserted mirror.
            const editBtn = r.direction === 'outgoing'
                ? `<button class="btn btn-sm btn-outline-primary me-1" data-rel-payload="${payload}" onclick="ricEditRelation(this)" title="Edit"><i class="fas fa-edit"></i></button>`
                : '';
            return `<tr>
                <td><span class="badge ${r.direction === 'outgoing' ? 'bg-primary' : 'bg-info'}">${r.direction}</span></td>
                <td><span class="badge bg-secondary me-1">${r.target_type || ''}</span> ${r.target_name || 'Entity #' + r.target_id}</td>
                <td>${r.relation_label || r.rico_predicate || r.legacy_type_name || ''}</td>
                <td>${[r.start_date, r.end_date].filter(Boolean).join(' - ') || ''}</td>
                <td>${r.certainty ? `<span class="badge bg-warning">${r.certainty}</span>` : ''}</td>
                <td><small>${(r.evidence || '').substring(0, 60)}${(r.evidence && r.evidence.length > 60) ? '…' : ''}</small></td>
                <td class="text-nowrap">${editBtn}<button class="btn btn-sm btn-outline-danger" onclick="ricDeleteRelation(${r.id})" title="Remove"><i class="fas fa-unlink"></i></button></td>
            </tr>`;
        }).join('');
    };

    // ------------------------------------------------------------
    // Self-load — if no one else has called renderRelations yet, fetch now.
    // ------------------------------------------------------------
    function loadRelations() {
        if (!recordId) return;
        // Public API returns grouped {outgoing, incoming}; flatten for the
        // existing table renderer which expects a flat array with `direction`.
        fetch(`${RIC_API_BASE}/relations-for/${recordId}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(payload => {
                const flat = [
                    ...(payload.outgoing || []),
                    ...(payload.incoming || []),
                ];
                renderRelations(flat);
            })
            .catch(() => {});
    }
    // Defer slightly so external callers (e.g. _ric-entities-panel) can pre-render
    // if they're already fetching. If the body still says "Loading…" we fetch ourselves.
    setTimeout(function () {
        const body = document.getElementById('ric-relations-body');
        if (body && body.textContent.trim().startsWith('Loading')) loadRelations();
    }, 200);

    // ------------------------------------------------------------
    // Create / update / cancel
    // ------------------------------------------------------------
    function currentFormPayload() {
        return {
            subject_id: recordId,
            object_id: parseInt(targetIdInput.value) || null,
            relation_type: typeSelect.value,
            start_date: startInput.value || null,
            end_date: endInput.value || null,
            certainty: certaintyInput.value || null,
            evidence: evidenceInput.value || null,
        };
    }

    function jsonHeaders() {
        return { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    }

    window.ricSubmitRelation = function() {
        const payload = currentFormPayload();
        if (editingId === null) {
            if (!payload.object_id || !payload.relation_type) { alert('Select a target entity and relation type'); return; }
            fetch(`${RIC_API_BASE}/relations`, {
                method: 'POST', credentials: 'same-origin', headers: jsonHeaders(), body: JSON.stringify(payload)
            })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => { if (ok) location.reload(); else alert(body.error || body.message || 'Create failed'); });
        } else {
            fetch(`${RIC_API_BASE}/relations/${editingId}`, {
                method: 'PATCH', credentials: 'same-origin', headers: jsonHeaders(), body: JSON.stringify(payload)
            })
            .then(r => r.json().then(body => ({ ok: r.ok, body })))
            .then(({ ok, body }) => { if (ok) location.reload(); else alert(body.error || body.message || 'Update failed'); });
        }
    };

    window.ricEditRelation = function(btn) {
        const data = JSON.parse(decodeURIComponent(btn.dataset.relPayload));
        editingId = data.id;
        targetIdInput.value = data.target_id;
        searchInput.value = `${data.target_type} ${data.target_name}`.trim();
        typeSelect.value = data.relation_type;
        startInput.value = data.start_date;
        endInput.value = data.end_date;
        certaintyInput.value = data.certainty;
        evidenceInput.value = data.evidence;
        submitBtn.innerHTML = '<i class="fas fa-save"></i>';
        submitBtn.title = 'Save changes';
        cancelBtn.classList.remove('d-none');
        formTitle.innerHTML = '<i class="fas fa-pen"></i> Edit Relation';
        document.getElementById('ric-relation-editor').scrollIntoView({behavior: 'smooth', block: 'center'});
    };

    window.ricCancelEdit = function() {
        editingId = null;
        targetIdInput.value = '';
        searchInput.value = '';
        typeSelect.value = '';
        startInput.value = '';
        endInput.value = '';
        certaintyInput.value = '';
        evidenceInput.value = '';
        submitBtn.innerHTML = '<i class="fas fa-link"></i>';
        submitBtn.title = 'Create relation';
        cancelBtn.classList.add('d-none');
        formTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add Relation';
    };

    window.ricDeleteRelation = function(id) {
        if (!confirm('Remove this relation?')) return;
        fetch(`${RIC_API_BASE}/relations/${id}`, { method: 'DELETE', credentials: 'same-origin', headers: jsonHeaders() })
            .then(() => location.reload());
    };

    // Back-compat alias: older code may still call ricCreateRelation().
    window.ricCreateRelation = window.ricSubmitRelation;
});
</script>
