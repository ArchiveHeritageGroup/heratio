@extends('theme::layouts.1col')

@section('title', ($isNew ? 'Add' : 'Edit') . ' Rights - ' . ($resource->title ?? $resource->slug))
@section('body-class', 'rights edit')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug }}</h1>
    <span class="small">{{ $isNew ? 'Add rights' : 'Edit rights' }}</span>
  </div>
@endsection

@section('content')
<form method="post" action="{{ $isNew
    ? route('ext-rights.store', $resource->slug)
    : route('ext-rights.update', [$resource->slug, $right->id]) }}"
    id="rightsEditForm">
    @csrf

    <div class="accordion mb-3" id="rightsAccordion">

        {{-- Rights Basis Section --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#basisSection">
                    Rights basis
                </button>
            </h2>
            <div id="basisSection" class="accordion-collapse collapse show">
                <div class="accordion-body">

                    <div class="mb-3">
                        <label class="form-label">Basis <span class="text-danger">*</span></label>
                        <select name="basis" id="basis" class="form-select" required>
                            <option value="">-- Select --</option>
                            @foreach($formOptions['basis_options'] as $value => $label)
                                <option value="{{ $value }}" {{ old('basis', $right->basis ?? '') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rights Statement</label>
                        <select name="rights_statement_id" class="form-select">
                            <option value="">-- Select --</option>
                            @foreach($formOptions['rights_statements'] as $stmt)
                                <option value="{{ $stmt->id }}" {{ old('rights_statement_id', $right->rights_statement_id ?? '') == $stmt->id ? 'selected' : '' }}>
                                    {{ $stmt->name ?? $stmt->code ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Standardized statement from rightsstatements.org</div>
                    </div>

                    {{-- Copyright Fields --}}
                    <div id="copyrightFields" class="basis-fields" style="display: none;">
                        <hr><h6 class="text-muted mb-3">Copyright Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Copyright status</label>
                                <select name="copyright_status" class="form-select">
                                    <option value="">-- Select --</option>
                                    @foreach($formOptions['copyright_status_options'] as $v => $l)
                                        <option value="{{ $v }}" {{ old('copyright_status', $right->copyright_status ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status determination date</label>
                                <input type="date" name="copyright_status_date" class="form-control" value="{{ old('copyright_status_date', $right->copyright_determination_date ?? '') }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jurisdiction</label>
                                <input type="text" name="copyright_jurisdiction" class="form-control" maxlength="2" placeholder="ZA" value="{{ old('copyright_jurisdiction', $right->copyright_jurisdiction ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry date</label>
                                <input type="date" name="copyright_expiry_date" class="form-control" value="{{ old('copyright_expiry_date', $right->copyright_expiry_date ?? '') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Copyright holder</label>
                            <input type="text" name="copyright_holder" class="form-control" value="{{ old('copyright_holder', $right->copyright_holder ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Copyright note</label>
                            <textarea name="copyright_note" class="form-control" rows="2">{{ old('copyright_note', $right->copyright_note ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- License Fields --}}
                    <div id="licenseFields" class="basis-fields" style="display: none;">
                        <hr><h6 class="text-muted mb-3">License Information</h6>
                        <div class="mb-3">
                            <label class="form-label">License type</label>
                            <select name="license_type" id="license_type" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="cc" {{ old('license_type', $right->license_type ?? '') === 'cc' ? 'selected' : '' }}>Creative Commons</option>
                                <option value="open" {{ old('license_type', $right->license_type ?? '') === 'open' ? 'selected' : '' }}>Other Open License</option>
                                <option value="proprietary" {{ old('license_type', $right->license_type ?? '') === 'proprietary' ? 'selected' : '' }}>Proprietary</option>
                                <option value="custom" {{ old('license_type', $right->license_type ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                            </select>
                        </div>
                        <div id="ccLicenseField" class="mb-3" style="display: none;">
                            <label class="form-label">Creative Commons License</label>
                            <select name="cc_license_id" class="form-select">
                                <option value="">-- Select --</option>
                                @foreach($formOptions['cc_licenses'] as $license)
                                    <option value="{{ $license->id }}" {{ old('cc_license_id', $right->cc_license_id ?? '') == $license->id ? 'selected' : '' }}>
                                        {{ ($license->code ?? '') . ' - ' . ($license->name ?? '') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License identifier</label>
                            <input type="text" name="license_identifier" class="form-control" value="{{ old('license_identifier', $right->license_identifier ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License URL</label>
                            <input type="url" name="license_url" class="form-control" value="{{ old('license_url', $right->license_url ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">License terms</label>
                            <textarea name="license_terms" class="form-control" rows="3">{{ old('license_terms', $right->license_terms ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- Statute Fields --}}
                    <div id="statuteFields" class="basis-fields" style="display: none;">
                        <hr><h6 class="text-muted mb-3">Statute Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jurisdiction</label>
                                <input type="text" name="statute_jurisdiction" class="form-control" maxlength="2" placeholder="ZA" value="{{ old('statute_jurisdiction', $right->statute_jurisdiction ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Determination date</label>
                                <input type="date" name="statute_determination_date" class="form-control" value="{{ old('statute_determination_date', $right->statute_determination_date ?? '') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statute citation</label>
                            <input type="text" name="statute_citation" class="form-control" value="{{ old('statute_citation', $right->statute_citation ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statute note</label>
                            <textarea name="statute_note" class="form-control" rows="2">{{ old('statute_note', $right->statute_note ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- Donor Fields --}}
                    <div id="donorFields" class="basis-fields" style="display: none;">
                        <hr><h6 class="text-muted mb-3">Donor Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Donor name</label>
                            <input type="text" name="donor_name" class="form-control" value="{{ old('donor_name', $right->donor_name ?? '') }}">
                        </div>
                    </div>

                    {{-- Policy Fields --}}
                    <div id="policyFields" class="basis-fields" style="display: none;">
                        <hr><h6 class="text-muted mb-3">Policy Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Policy identifier</label>
                            <input type="text" name="policy_identifier" class="form-control" value="{{ old('policy_identifier', $right->policy_identifier ?? '') }}">
                        </div>
                    </div>

                    {{-- Common Date Fields --}}
                    <hr>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Start date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $right->start_date ?? '') }}">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label">End date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $right->end_date ?? '') }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="end_date_open" id="end_date_open" value="1" {{ old('end_date_open', $right->end_date_open ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="end_date_open">Open</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rights holder</label>
                        <input type="text" name="rights_holder_name" class="form-control" value="{{ old('rights_holder_name', $right->rights_holder_name ?? '') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rights note</label>
                        <textarea name="rights_note" class="form-control" rows="3">{{ old('rights_note', $right->rights_note ?? '') }}</textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- Granted Rights (Acts) Section --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#actsSection">
                    Act / Granted rights
                </button>
            </h2>
            <div id="actsSection" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <p class="text-muted small">Define what actions are allowed or restricted.</p>

                    <div id="grantedRightsContainer">
                        @php
                        $grantedRights = [];
                        if (isset($right) && isset($right->grants) && count($right->grants) > 0) {
                            $grantedRights = $right->grants->toArray();
                        }
                        if (empty($grantedRights)) {
                            $grantedRights = [(object) ['act' => '', 'restriction' => 'allow', 'restriction_note' => '']];
                        }
                        @endphp
                        @foreach($grantedRights as $i => $g)
                        <div class="granted-right-row row mb-2">
                            <div class="col-md-4">
                                <select name="acts[]" class="form-select form-select-sm">
                                    <option value="">-- Select act --</option>
                                    @foreach($formOptions['act_options'] as $v => $l)
                                        <option value="{{ $v }}" {{ (is_object($g) ? ($g->act ?? '') : ($g['act'] ?? '')) === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="restrictions[]" class="form-select form-select-sm">
                                    @foreach($formOptions['restriction_options'] as $v => $l)
                                        <option value="{{ $v }}" {{ (is_object($g) ? ($g->restriction ?? 'allow') : ($g['restriction'] ?? 'allow')) === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="restriction_reasons[]" class="form-control form-control-sm" placeholder="Reason (optional)" value="{{ is_object($g) ? ($g->restriction_note ?? '') : ($g['restriction_reason'] ?? '') }}">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-grant" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary" id="addGrantedRight">
                        <i class="fas fa-plus me-1"></i>Add act
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- Form Actions --}}
    <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <a href="{{ route('ext-rights.index', $resource->slug) }}" class="btn atom-btn-outline-light">Cancel</a>
      <button type="submit" class="btn atom-btn-outline-light"><i class="fas fa-save me-1"></i>Save</button>
    </section>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var basisSelect = document.getElementById('basis');
    var licenseTypeSelect = document.getElementById('license_type');

    function showBasisFields() {
        document.querySelectorAll('.basis-fields').forEach(function(el) {
            el.style.display = 'none';
        });
        var basis = basisSelect.value;
        var fieldMap = {
            'copyright': 'copyrightFields',
            'license': 'licenseFields',
            'statute': 'statuteFields',
            'donor': 'donorFields',
            'policy': 'policyFields'
        };
        if (fieldMap[basis]) {
            document.getElementById(fieldMap[basis]).style.display = 'block';
        }
    }

    function showCcLicense() {
        var ccField = document.getElementById('ccLicenseField');
        if (ccField && licenseTypeSelect) {
            ccField.style.display = licenseTypeSelect.value === 'cc' ? 'block' : 'none';
        }
    }

    basisSelect.addEventListener('change', showBasisFields);
    if (licenseTypeSelect) {
        licenseTypeSelect.addEventListener('change', showCcLicense);
    }

    showBasisFields();
    if (licenseTypeSelect) showCcLicense();

    document.getElementById('addGrantedRight').addEventListener('click', function() {
        var container = document.getElementById('grantedRightsContainer');
        var template = container.querySelector('.granted-right-row').cloneNode(true);
        template.querySelectorAll('input, select').forEach(function(el) {
            el.value = '';
        });
        container.appendChild(template);
    });

    document.getElementById('grantedRightsContainer').addEventListener('click', function(e) {
        if (e.target.closest('.remove-grant')) {
            var rows = document.querySelectorAll('.granted-right-row');
            if (rows.length > 1) {
                e.target.closest('.granted-right-row').remove();
            }
        }
    });
});
</script>
@endpush
