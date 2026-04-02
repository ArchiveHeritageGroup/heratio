{{-- RiC Entity Creation Modal --}}
<div class="modal fade" id="ricEntityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="ricEntityForm">
                @csrf
                <input type="hidden" name="entity_type" id="ricEntityType" value="activity">
                <input type="hidden" name="link_to_record_id" value="{{ $recordId ?? '' }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="ricEntityModalTitle"><i class="fas fa-plus"></i> Create RiC Entity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Common: link relation type --}}
                    <div class="mb-3">
                        <label class="form-label">Relation to this record</label>
                        <select name="link_relation_type" class="form-select form-select-sm" id="ricLinkRelationType">
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    {{-- Activity fields --}}
                    <div id="ricFields-activity">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Activity Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Activity Type</label>
                                <select name="type_id" class="form-select ric-dropdown" data-taxonomy="ric_activity_type"></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Date Display</label><input type="text" name="date_display" class="form-control" placeholder="e.g. ca. 1920"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    </div>

                    {{-- Place fields --}}
                    <div id="ricFields-place" style="display:none">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Place Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Place Type</label>
                                <select name="type_id" class="form-select ric-dropdown" data-taxonomy="ric_place_type"></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><label class="form-label">Latitude</label><input type="number" step="any" name="latitude" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Longitude</label><input type="number" step="any" name="longitude" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Authority URI</label><input type="url" name="authority_uri" class="form-control" placeholder="https://www.geonames.org/..."></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    </div>

                    {{-- Rule fields --}}
                    <div id="ricFields-rule" style="display:none">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Rule Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rule Type</label>
                                <select name="type_id" class="form-select ric-dropdown" data-taxonomy="ric_rule_type"></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><label class="form-label">Jurisdiction</label><input type="text" name="jurisdiction" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                        <div class="mb-3"><label class="form-label">Legislation</label><textarea name="legislation" class="form-control" rows="2"></textarea></div>
                    </div>

                    {{-- Instantiation fields --}}
                    <div id="ricFields-instantiation" style="display:none">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Carrier Type</label>
                                <select name="carrier_type" class="form-select ric-dropdown" data-taxonomy="ric_carrier_type"></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><label class="form-label">MIME Type</label><input type="text" name="mime_type" class="form-control" placeholder="e.g. image/tiff"></div>
                            <div class="col-md-4"><label class="form-label">Extent</label><input type="number" step="any" name="extent_value" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Unit</label><input type="text" name="extent_unit" class="form-control" value="bytes"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Technical Characteristics</label><textarea name="technical_characteristics" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const entityTypeLabels = { activity: 'Activity', place: 'Place', rule: 'Rule', instantiation: 'Instantiation' };
const entityTypeIcons = { activity: 'fa-running', place: 'fa-map-marker-alt', rule: 'fa-gavel', instantiation: 'fa-file-alt' };

// Default relation type per entity type
const defaultRelTypes = { activity: 'results_from', place: 'has_or_had_location', rule: 'has_mandate', instantiation: 'has_instantiation' };

function ricSetEntityType(type) {
    document.getElementById('ricEntityType').value = type;
    document.getElementById('ricEntityModalTitle').innerHTML = `<i class="fas ${entityTypeIcons[type]}"></i> Create ${entityTypeLabels[type]}`;

    // Show/hide field groups
    ['activity', 'place', 'rule', 'instantiation'].forEach(t => {
        const el = document.getElementById('ricFields-' + t);
        if (el) el.style.display = t === type ? '' : 'none';
    });

    // Set default relation type
    const relSelect = document.getElementById('ricLinkRelationType');
    if (relSelect && defaultRelTypes[type]) {
        relSelect.value = defaultRelTypes[type];
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Load dropdown options for all ric-dropdown selects
    document.querySelectorAll('.ric-dropdown').forEach(select => {
        const taxonomy = select.dataset.taxonomy;
        if (!taxonomy) return;
        fetch(`/ric-api/entities/dropdown/${taxonomy}`)
            .then(r => r.json())
            .then(items => {
                select.innerHTML = '<option value="">-- Select --</option>' +
                    items.map(i => `<option value="${i.code}" ${i.is_default ? 'selected' : ''}>${i.label}</option>`).join('');
            });
    });

    // Load relation types for the link dropdown
    fetch('/ric-api/relations/types')
        .then(r => r.json())
        .then(types => {
            const select = document.getElementById('ricLinkRelationType');
            select.innerHTML = '<option value="">-- No link --</option>' +
                types.map(t => `<option value="${t.code}">${t.label}</option>`).join('');
        });

    // Form submission
    document.getElementById('ricEntityForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch('/ric-api/entities/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('ricEntityModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
});
</script>
