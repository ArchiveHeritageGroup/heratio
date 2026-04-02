{{-- RiC Relation Editor Widget --}}
@php $recordId = $recordId ?? null; @endphp

<div id="ric-relation-editor">
    <table class="table table-sm table-striped mb-2">
        <thead><tr><th>Direction</th><th>Related Entity</th><th>Relation Type</th><th>Dates</th><th></th></tr></thead>
        <tbody id="ric-relations-body"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody>
    </table>

    <div class="card card-body bg-light p-2 mt-2">
        <h6 class="mb-2"><i class="fas fa-plus-circle"></i> Add Relation</h6>
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label form-label-sm">Target Entity</label>
                <input type="text" id="ric-rel-target-search" class="form-control form-control-sm" placeholder="Search entities..." autocomplete="off">
                <input type="hidden" id="ric-rel-target-id">
                <div id="ric-rel-autocomplete" class="list-group position-absolute" style="z-index:1050; display:none; max-height:200px; overflow-y:auto;"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Relation Type</label>
                <select id="ric-rel-type" class="form-select form-select-sm">
                    <option value="">Loading...</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Start</label>
                <input type="date" id="ric-rel-start" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">End</label>
                <input type="date" id="ric-rel-end" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-primary w-100" onclick="ricCreateRelation()">
                    <i class="fas fa-link"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recordId = {{ $recordId ?? 0 }};

    // Load relation types
    fetch('/ric-api/relations/types')
        .then(r => r.json())
        .then(types => {
            const select = document.getElementById('ric-rel-type');
            select.innerHTML = '<option value="">-- Select --</option>' +
                types.map(t => `<option value="${t.code}">${t.label}</option>`).join('');
        });

    // Autocomplete for target entity
    let debounce;
    const searchInput = document.getElementById('ric-rel-target-search');
    const acList = document.getElementById('ric-rel-autocomplete');
    const targetIdInput = document.getElementById('ric-rel-target-id');

    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { acList.style.display = 'none'; return; }
        debounce = setTimeout(() => {
            fetch(`/ric-api/entities/autocomplete?q=${encodeURIComponent(q)}`)
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

    // Render relations
    window.renderRelations = function(relations) {
        const body = document.getElementById('ric-relations-body');
        if (!relations.length) { body.innerHTML = '<tr><td colspan="5" class="text-muted">No relations</td></tr>'; return; }
        body.innerHTML = relations.map(r => `<tr>
            <td><span class="badge ${r.direction === 'outgoing' ? 'bg-primary' : 'bg-info'}">${r.direction}</span></td>
            <td><span class="badge bg-secondary me-1">${r.target_type || ''}</span> ${r.target_name || 'Entity #' + r.target_id}</td>
            <td>${r.relation_label || r.rico_predicate || r.legacy_type_name || ''}</td>
            <td>${[r.start_date, r.end_date].filter(Boolean).join(' - ') || ''}</td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="ricDeleteRelation(${r.id})"><i class="fas fa-unlink"></i></button></td>
        </tr>`).join('');
    };

    window.ricCreateRelation = function() {
        const targetId = targetIdInput.value;
        const relType = document.getElementById('ric-rel-type').value;
        if (!targetId || !relType) { alert('Select a target entity and relation type'); return; }

        fetch('/ric-api/relations/store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({
                subject_id: recordId,
                object_id: parseInt(targetId),
                relation_type: relType,
                start_date: document.getElementById('ric-rel-start').value || null,
                end_date: document.getElementById('ric-rel-end').value || null
            })
        })
        .then(r => r.json())
        .then(result => { if (result.success) location.reload(); else alert(result.error); });
    };

    window.ricDeleteRelation = function(id) {
        if (!confirm('Remove this relation?')) return;
        fetch(`/ric-api/relations/${id}`, { method: 'DELETE', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content} })
            .then(r => r.json())
            .then(() => location.reload());
    };
});
</script>
