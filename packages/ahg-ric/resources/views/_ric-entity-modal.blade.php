@once
@include('ahg-ric::_ric-api-base')
@endonce
{{-- RiC Entity Creation Modal --}}
@php
    // Issue #59 Tier 2 — culture-aware dropdowns via the COALESCE helper.
    $ricDropdowns = [
        'ric_activity_type' => \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('ric_activity_type'),
        'ric_place_type'    => \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('ric_place_type'),
        'ric_rule_type'     => \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('ric_rule_type'),
        'ric_carrier_type'  => \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('ric_carrier_type'),
        'ric_relation_type' => \AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('ric_relation_type'),
    ];
@endphp
<div class="modal fade" id="ricEntityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ricEntityModalTitle"><i class="fas fa-plus"></i> {{ __('Create RiC Entity') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Common: link relation type --}}
                <div class="mb-3">
                    <label class="form-label">{{ __('Relation to this record') }}</label>
                    <select class="form-select form-select-sm" id="ricLinkRelationType">
                        <option value="">{{ __('-- No link --') }}</option>
                        @foreach($ricDropdowns['ric_relation_type'] as $rt)
                        <option value="{{ $rt->code }}">{{ $rt->label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Activity fields --}}
                <div id="ricFields-activity">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Activity Name') }} <span class="text-danger">*</span></label>
                            <input type="text" id="ric-f-activity-name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Activity Type') }}</label>
                            <select id="ric-f-activity-type" class="form-select">
                                <option value="">{{ __('-- Select --') }}</option>
                                @foreach($ricDropdowns['ric_activity_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4"><label class="form-label">{{ __('Start Date') }}</label><input type="date" id="ric-f-activity-start" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('End Date') }}</label><input type="date" id="ric-f-activity-end" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Date Display') }}</label><input type="text" id="ric-f-activity-date-display" class="form-control" placeholder="{{ __('e.g. ca. 1920') }}"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea id="ric-f-activity-desc" class="form-control" rows="3"></textarea></div>
                </div>

                {{-- Place fields --}}
                <div id="ricFields-place" style="display:none">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Place Name') }} <span class="text-danger">*</span></label>
                            <input type="text" id="ric-f-place-name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Place Type') }}</label>
                            <select id="ric-f-place-type" class="form-select">
                                <option value="">{{ __('-- Select --') }}</option>
                                @foreach($ricDropdowns['ric_place_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4"><label class="form-label">{{ __('Latitude') }}</label><input type="number" step="any" id="ric-f-place-lat" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Longitude') }}</label><input type="number" step="any" id="ric-f-place-lng" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Authority URI') }}</label><input type="url" id="ric-f-place-uri" class="form-control" placeholder="{{ __('https://www.geonames.org/...') }}"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Address') }}</label><textarea id="ric-f-place-address" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea id="ric-f-place-desc" class="form-control" rows="2"></textarea></div>
                </div>

                {{-- Rule fields --}}
                <div id="ricFields-rule" style="display:none">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Rule Title') }} <span class="text-danger">*</span></label>
                            <input type="text" id="ric-f-rule-title" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Rule Type') }}</label>
                            <select id="ric-f-rule-type" class="form-select">
                                <option value="">{{ __('-- Select --') }}</option>
                                @foreach($ricDropdowns['ric_rule_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4"><label class="form-label">{{ __('Jurisdiction') }}</label><input type="text" id="ric-f-rule-jurisdiction" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Start Date') }}</label><input type="date" id="ric-f-rule-start" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('End Date') }}</label><input type="date" id="ric-f-rule-end" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea id="ric-f-rule-desc" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('Legislation') }}</label><textarea id="ric-f-rule-legislation" class="form-control" rows="2"></textarea></div>
                </div>

                {{-- Instantiation fields --}}
                <div id="ricFields-instantiation" style="display:none">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" id="ric-f-inst-title" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Carrier Type') }}</label>
                            <select id="ric-f-inst-carrier" class="form-select">
                                <option value="">{{ __('-- Select --') }}</option>
                                @foreach($ricDropdowns['ric_carrier_type'] as $d)<option value="{{ $d->code }}" {{ $d->is_default ? 'selected' : '' }}>{{ $d->label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4"><label class="form-label">{{ __('MIME Type') }}</label><input type="text" id="ric-f-inst-mime" class="form-control" placeholder="{{ __('e.g. image/tiff') }}"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Extent') }}</label><input type="number" step="any" id="ric-f-inst-extent" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">{{ __('Unit') }}</label><input type="text" id="ric-f-inst-unit" class="form-control" value="bytes"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea id="ric-f-inst-desc" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('Technical Characteristics') }}</label><textarea id="ric-f-inst-tech" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="ricEntitySaveBtn"><i class="fas fa-save"></i> {{ __('Create') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
const entityTypeLabels = { activity: 'Activity', place: 'Place', rule: 'Rule', instantiation: 'Instantiation' };
const entityTypeIcons = { activity: 'fa-running', place: 'fa-map-marker-alt', rule: 'fa-gavel', instantiation: 'fa-file-alt' };
const defaultRelTypes = { activity: 'results_from', place: 'has_or_had_location', rule: 'has_mandate', instantiation: 'has_instantiation' };

let ricCurrentType = 'activity';

function ricSetEntityType(type) {
    ricCurrentType = type;
    document.getElementById('ricEntityModalTitle').innerHTML = `<i class="fas ${entityTypeIcons[type]}"></i> Create ${entityTypeLabels[type]}`;

    ['activity', 'place', 'rule', 'instantiation'].forEach(t => {
        const el = document.getElementById('ricFields-' + t);
        if (el) el.style.display = t === type ? '' : 'none';
    });

    const relSelect = document.getElementById('ricLinkRelationType');
    if (relSelect && defaultRelTypes[type]) {
        relSelect.value = defaultRelTypes[type];
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('ricEntitySaveBtn').addEventListener('click', function() {
        const type = ricCurrentType;
        const recordId = '{{ $recordId ?? '' }}';
        const linkRelType = document.getElementById('ricLinkRelationType').value;

        // Build data based on current entity type
        let data = {
            entity_type: type,
            link_to_record_id: recordId,
            link_relation_type: linkRelType,
        };

        if (type === 'activity') {
            data.name = document.getElementById('ric-f-activity-name').value;
            data.type_id = document.getElementById('ric-f-activity-type').value;
            data.start_date = document.getElementById('ric-f-activity-start').value;
            data.end_date = document.getElementById('ric-f-activity-end').value;
            data.date_display = document.getElementById('ric-f-activity-date-display').value;
            data.description = document.getElementById('ric-f-activity-desc').value;
            if (!data.name) { alert('Activity name is required'); return; }
        } else if (type === 'place') {
            data.name = document.getElementById('ric-f-place-name').value;
            data.type_id = document.getElementById('ric-f-place-type').value;
            data.latitude = document.getElementById('ric-f-place-lat').value;
            data.longitude = document.getElementById('ric-f-place-lng').value;
            data.authority_uri = document.getElementById('ric-f-place-uri').value;
            data.address = document.getElementById('ric-f-place-address').value;
            data.description = document.getElementById('ric-f-place-desc').value;
            if (!data.name) { alert('Place name is required'); return; }
        } else if (type === 'rule') {
            data.title = document.getElementById('ric-f-rule-title').value;
            data.type_id = document.getElementById('ric-f-rule-type').value;
            data.jurisdiction = document.getElementById('ric-f-rule-jurisdiction').value;
            data.start_date = document.getElementById('ric-f-rule-start').value;
            data.end_date = document.getElementById('ric-f-rule-end').value;
            data.description = document.getElementById('ric-f-rule-desc').value;
            data.legislation = document.getElementById('ric-f-rule-legislation').value;
            if (!data.title) { alert('Rule title is required'); return; }
        } else if (type === 'instantiation') {
            data.title = document.getElementById('ric-f-inst-title').value;
            data.carrier_type = document.getElementById('ric-f-inst-carrier').value;
            data.mime_type = document.getElementById('ric-f-inst-mime').value;
            data.extent_value = document.getElementById('ric-f-inst-extent').value;
            data.extent_unit = document.getElementById('ric-f-inst-unit').value;
            data.description = document.getElementById('ric-f-inst-desc').value;
            data.technical_characteristics = document.getElementById('ric-f-inst-tech').value;
            data.record_id = recordId;
            if (!data.title) { alert('Title is required'); return; }
        }

        // POST /api/ric/v1/{type-plural} for the entity (LOCAL Heratio — writes
        // never go cross-origin to OpenRiC; per the separation plan ric_*
        // tables live here and OpenRiC is read-only public). Then if a
        // link-to-record was specified, POST /api/ric/v1/relations. Both
        // routes are gated by api.auth:write which accepts the logged-in
        // admin's session cookie via credentials: 'same-origin'.
        const entityPayload = Object.assign({}, data);
        delete entityPayload.entity_type;
        delete entityPayload.link_to_record_id;
        delete entityPayload.link_relation_type;
        const entityType = data.entity_type; // 'place' | 'rule' | 'activity' | 'instantiation'
        const plural = entityType === 'activity' ? 'activities' : (entityType + 's');
        const typeUrl = `/api/ric/v1/${plural}`;
        fetch(typeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(entityPayload)
        })
        .then(r => r.json().then(body => ({ ok: r.ok, body })))
        .then(async ({ ok, body }) => {
            if (!ok) { alert('Error: ' + (body.error || body.message || 'Unknown')); return; }
            const createdId = body.id;
            if (data.link_to_record_id && data.link_relation_type && createdId) {
                const relResp = await fetch(`/api/ric/v1/relations`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        subject_id: parseInt(data.link_to_record_id),
                        object_id: createdId,
                        relation_type: data.link_relation_type,
                    })
                });
                if (!relResp.ok) {
                    const msg = await relResp.text();
                    alert('Entity created but relation failed: ' + msg);
                    // Still close + reload so the new entity is visible.
                }
            }
            bootstrap.Modal.getInstance(document.getElementById('ricEntityModal')).hide();
            location.reload();
        })
        .catch(err => alert('Error: ' + err.message));
    });
});
</script>
