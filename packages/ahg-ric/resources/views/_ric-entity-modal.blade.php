{{-- RiC Entity Creation Modal --}}
@php
    $ricDropdowns = [
        'ric_activity_type' => \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'ric_activity_type')->where('is_active', 1)->orderBy('sort_order')->get(),
        'ric_place_type' => \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'ric_place_type')->where('is_active', 1)->orderBy('sort_order')->get(),
        'ric_rule_type' => \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'ric_rule_type')->where('is_active', 1)->orderBy('sort_order')->get(),
        'ric_carrier_type' => \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'ric_carrier_type')->where('is_active', 1)->orderBy('sort_order')->get(),
        'ric_relation_type' => \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy', 'ric_relation_type')->where('is_active', 1)->orderBy('sort_order')->get(),
    ];
@endphp
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
                            <option value="">-- No link --</option>
                            @foreach($ricDropdowns['ric_relation_type'] as $rt)
                            <option value="{{ $rt->code }}">{{ $rt->label }}</option>
                            @endforeach
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
                                <select name="type_id" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($ricDropdowns['ric_activity_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                                </select>
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
                                <select name="type_id" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($ricDropdowns['ric_place_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                                </select>
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
                                <select name="type_id" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($ricDropdowns['ric_rule_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                                </select>
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
                                <select name="carrier_type" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($ricDropdowns['ric_carrier_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                                </select>
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
    // Form submission
    document.getElementById('ricEntityForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch('/admin/ric/entity-api/store', {
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
