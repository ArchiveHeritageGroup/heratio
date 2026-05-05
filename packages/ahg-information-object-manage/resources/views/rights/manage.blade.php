@extends('theme::layouts.1col')

@section('title', 'Manage Rights: ' . ($io->title ?? 'Untitled'))

@push('styles')
<link href="/vendor/ahg-theme-b5/css/vendor/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
  .accordion-button:not(.collapsed) { background: var(--ahg-primary); color: #fff; }
  .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
  .granted-row { border: 1px solid #dee2e6; border-radius: .375rem; padding: 1rem; margin-bottom: .75rem; background: #f8f9fa; }
  .basis-fields { display: none; }
  .basis-fields.active { display: block; }
</style>
@endpush

@section('content')

<h1 class="mb-1">{{ __('Manage Rights') }}</h1>
<p class="text-muted mb-3">{{ $io->title ?? 'Untitled' }}</p>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-4">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/' . $io->slug) }}">{{ $io->title ?? $io->slug }}</a></li>
    <li class="breadcrumb-item active">Manage Rights</li>
  </ol>
</nav>

@if(session('notice'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('notice') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('io.rights.manage.store', $io->slug) }}" id="rightsForm">
  @csrf

  <div class="accordion" id="rightsAccordion">

    {{-- ============================================================ --}}
    {{-- SECTION 1: Rights Statement & Licensing (Extended Rights)     --}}
    {{-- ============================================================ --}}
    <div class="accordion-item mb-3">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sectionExtended">
          <i class="fas fa-balance-scale me-2"></i> {{ __('Rights Statement & Licensing') }}
        </button>
      </h2>
      <div id="sectionExtended" class="accordion-collapse collapse show" data-bs-parent="#rightsAccordion">
        <div class="accordion-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="rights_statement_id" class="form-label">{{ __('Rights Statement') }}</label>
                <select name="rights_statement_id" id="rights_statement_id" class="form-select">
                  <option value="">-- None --</option>
                  @foreach($rightsStatements as $rs)
                    <option value="{{ $rs->id }}" @if(old('rights_statement_id', $currentExtended->rights_statement_id ?? '') == $rs->id) selected @endif>
                      {{ $rs->name ?? $rs->code }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label for="cc_license_id" class="form-label">{{ __('Creative Commons License') }}</label>
                <select name="cc_license_id" id="cc_license_id" class="form-select">
                  <option value="">-- None --</option>
                  @foreach($ccLicenses as $cc)
                    <option value="{{ $cc->id }}" @if(old('cc_license_id', $currentExtended->creative_commons_license_id ?? '') == $cc->id) selected @endif>
                      {{ $cc->name ?? $cc->code }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label for="ext_rights_holder" class="form-label">{{ __('Rights Holder') }}</label>
                <input type="text" name="ext_rights_holder" id="ext_rights_holder" class="form-control"
                       value="{{ old('ext_rights_holder', $currentExtended->rights_holder ?? '') }}">
              </div>

              <div class="mb-3">
                <label for="ext_rights_holder_uri" class="form-label">{{ __('Rights Holder URI') }}</label>
                <input type="url" name="ext_rights_holder_uri" id="ext_rights_holder_uri" class="form-control"
                       value="{{ old('ext_rights_holder_uri', $currentExtended->rights_holder_uri ?? '') }}"
                       placeholder="{{ __('https://') }}">
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="ext_rights_date" class="form-label">{{ __('Rights Date') }}</label>
                  <input type="date" name="ext_rights_date" id="ext_rights_date" class="form-control"
                         value="{{ old('ext_rights_date', $currentExtended->rights_date ?? '') }}">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="ext_expiry_date" class="form-label">{{ __('Expiry Date') }}</label>
                  <input type="date" name="ext_expiry_date" id="ext_expiry_date" class="form-control"
                         value="{{ old('ext_expiry_date', $currentExtended->expiry_date ?? '') }}">
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label for="ext_usage_conditions" class="form-label">{{ __('Usage Conditions') }}</label>
                <textarea name="ext_usage_conditions" id="ext_usage_conditions" class="form-control" rows="3">{{ old('ext_usage_conditions', $currentExtended->usage_conditions ?? '') }}</textarea>
              </div>

              <div class="mb-3">
                <label for="ext_copyright_notice" class="form-label">{{ __('Copyright Notice') }}</label>
                <textarea name="ext_copyright_notice" id="ext_copyright_notice" class="form-control" rows="3">{{ old('ext_copyright_notice', $currentExtended->copyright_notice ?? '') }}</textarea>
              </div>

              <div class="mb-3">
                <label for="ext_rights_note" class="form-label">{{ __('Rights Note') }}</label>
                <textarea name="ext_rights_note" id="ext_rights_note" class="form-control" rows="3">{{ old('ext_rights_note', $currentExtended->rights_note ?? '') }}</textarea>
              </div>

              {{-- TK Labels --}}
              @if($tkLabels->isNotEmpty())
              <div class="mb-3">
                <label class="form-label">{{ __('TK Labels') }}</label>
                <div class="border rounded p-2" style="max-height:200px;overflow-y:auto">
                  @foreach($tkLabels as $tk)
                    <div class="form-check">
                      <input type="checkbox" name="tk_label_ids[]" value="{{ $tk->id }}"
                             class="form-check-input" id="tk_{{ $tk->id }}"
                             @if(in_array($tk->id, $currentTkLabels)) checked @endif>
                      <label class="form-check-label" for="tk_{{ $tk->id }}">
                        @if($tk->color)
                          <span class="badge" style="background:{{ $tk->color }}">{{ $tk->category }}</span>
                        @endif
                        {{ $tk->code }}
                      </label>
                    </div>
                  @endforeach
                </div>
              </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 2: Rights Basis (PREMIS)                              --}}
    {{-- ============================================================ --}}
    @php
      $pr = $premisRights->first();
      $pri = $pr ? ($pr) : null;
    @endphp
    <div class="accordion-item mb-3">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionPremis">
          <i class="fas fa-gavel me-2"></i> {{ __('Rights Basis (PREMIS)') }}
        </button>
      </h2>
      <div id="sectionPremis" class="accordion-collapse collapse" data-bs-parent="#rightsAccordion">
        <div class="accordion-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="premis_basis_id" class="form-label">{{ __('Basis') }}</label>
                <select name="premis_basis_id" id="premis_basis_id" class="form-select">
                  <option value="">-- Select basis --</option>
                  @foreach($basisTerms as $bt)
                    <option value="{{ $bt->id }}" data-name="{{ strtolower($bt->name) }}"
                      @if(old('premis_basis_id', $pri->basis_id ?? '') == $bt->id) selected @endif>
                      {{ $bt->name }}
                    </option>
                  @endforeach
                </select>
              </div>

              {{-- Copyright fields --}}
              <div id="basisCopyright" class="basis-fields">
                <div class="mb-3">
                  <label for="premis_copyright_status_id" class="form-label">{{ __('Copyright Status') }}</label>
                  <select name="premis_copyright_status_id" id="premis_copyright_status_id" class="form-select">
                    <option value="">-- Select --</option>
                    @foreach($copyrightStatusTerms as $cs)
                      <option value="{{ $cs->id }}" @if(old('premis_copyright_status_id', $pri->copyright_status_id ?? '') == $cs->id) selected @endif>
                        {{ $cs->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label for="premis_copyright_status_date" class="form-label">{{ __('Copyright Status Date') }}</label>
                  <input type="date" name="premis_copyright_status_date" id="premis_copyright_status_date" class="form-control"
                         value="{{ old('premis_copyright_status_date', $pri->copyright_status_date ?? '') }}">
                </div>
                <div class="mb-3">
                  <label for="premis_copyright_jurisdiction" class="form-label">{{ __('Copyright Jurisdiction') }}</label>
                  <input type="text" name="premis_copyright_jurisdiction" id="premis_copyright_jurisdiction" class="form-control"
                         value="{{ old('premis_copyright_jurisdiction', $pri->copyright_jurisdiction ?? '') }}">
                </div>
                <div class="mb-3">
                  <label for="premis_copyright_note" class="form-label">{{ __('Copyright Note') }}</label>
                  <textarea name="premis_copyright_note" id="premis_copyright_note" class="form-control" rows="2">{{ old('premis_copyright_note', $pri->copyright_note ?? '') }}</textarea>
                </div>
              </div>

              {{-- License fields --}}
              <div id="basisLicense" class="basis-fields">
                <div class="mb-3">
                  <label for="premis_license_terms" class="form-label">{{ __('License Terms') }}</label>
                  <textarea name="premis_license_terms" id="premis_license_terms" class="form-control" rows="2">{{ old('premis_license_terms', $pri->license_terms ?? '') }}</textarea>
                </div>
                <div class="mb-3">
                  <label for="premis_license_note" class="form-label">{{ __('License Note') }}</label>
                  <textarea name="premis_license_note" id="premis_license_note" class="form-control" rows="2">{{ old('premis_license_note', $pri->license_note ?? '') }}</textarea>
                </div>
              </div>

              {{-- Statute fields --}}
              <div id="basisStatute" class="basis-fields">
                <div class="mb-3">
                  <label for="premis_statute_jurisdiction" class="form-label">{{ __('Statute Jurisdiction') }}</label>
                  <input type="text" name="premis_statute_jurisdiction" id="premis_statute_jurisdiction" class="form-control"
                         value="{{ old('premis_statute_jurisdiction', $pri->statute_jurisdiction ?? '') }}">
                </div>
                <div class="mb-3">
                  <label for="premis_statute_determination_date" class="form-label">{{ __('Statute Determination Date') }}</label>
                  <input type="date" name="premis_statute_determination_date" id="premis_statute_determination_date" class="form-control"
                         value="{{ old('premis_statute_determination_date', $pri->statute_determination_date ?? '') }}">
                </div>
                <div class="mb-3">
                  <label for="premis_statute_note" class="form-label">{{ __('Statute Note') }}</label>
                  <textarea name="premis_statute_note" id="premis_statute_note" class="form-control" rows="2">{{ old('premis_statute_note', $pri->statute_note ?? '') }}</textarea>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="premis_start_date" class="form-label">{{ __('Start Date') }}</label>
                  <input type="date" name="premis_start_date" id="premis_start_date" class="form-control"
                         value="{{ old('premis_start_date', $pri->start_date ?? '') }}">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="premis_end_date" class="form-label">{{ __('End Date') }}</label>
                  <input type="date" name="premis_end_date" id="premis_end_date" class="form-control"
                         value="{{ old('premis_end_date', $pri->end_date ?? '') }}">
                </div>
              </div>

              <div class="mb-3">
                {{-- Was a plain <input type="text" placeholder="Actor ID">
                     so users had to know the numeric record id. Swapped to
                     the shared autocomplete component (already used for
                     Creators + Repository on the IO edit form) so users
                     type a name and pick from the live actor.autocomplete
                     dropdown. \$pri->rights_holder_name is populated by
                     ExtendedRightsService::getRightsHolderName() so the
                     existing record's display value pre-fills correctly. --}}
                @include('ahg-core::components.autocomplete', [
                    'name'         => 'premis_rights_holder_id',
                    'label'        => __('Rights Holder (Actor)'),
                    'route'        => 'actor.autocomplete',
                    'value'        => old('premis_rights_holder_id', $pri->rights_holder_id ?? ''),
                    'displayValue' => $pri->rights_holder_name ?? '',
                    'placeholder'  => __('Type to search actors...'),
                    'idField'      => 'id',
                    'nameField'    => 'name',
                ])
              </div>

              <div class="mb-3">
                <label for="premis_rights_note" class="form-label">{{ __('Rights Note') }}</label>
                <textarea name="premis_rights_note" id="premis_rights_note" class="form-control" rows="3">{{ old('premis_rights_note', $pri->rights_note ?? '') }}</textarea>
              </div>

              <h6 class="mt-3 mb-2">{{ __('Documentation Identifier') }}</h6>
              <div class="mb-3">
                <label for="premis_identifier_type" class="form-label">{{ __('Identifier Type') }}</label>
                <input type="text" name="premis_identifier_type" id="premis_identifier_type" class="form-control"
                       value="{{ old('premis_identifier_type', $pri->identifier_type ?? '') }}">
              </div>
              <div class="mb-3">
                <label for="premis_identifier_value" class="form-label">{{ __('Identifier Value') }}</label>
                <input type="text" name="premis_identifier_value" id="premis_identifier_value" class="form-control"
                       value="{{ old('premis_identifier_value', $pri->identifier_value ?? '') }}">
              </div>
              <div class="mb-3">
                <label for="premis_identifier_role" class="form-label">{{ __('Identifier Role') }}</label>
                <input type="text" name="premis_identifier_role" id="premis_identifier_role" class="form-control"
                       value="{{ old('premis_identifier_role', $pri->identifier_role ?? '') }}">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 3: Granted Rights                                     --}}
    {{-- ============================================================ --}}
    <div class="accordion-item mb-3">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionGranted">
          <i class="fas fa-check-circle me-2"></i> {{ __('Granted Rights') }}
        </button>
      </h2>
      <div id="sectionGranted" class="accordion-collapse collapse" data-bs-parent="#rightsAccordion">
        <div class="accordion-body">
          <div id="grantedContainer">
            @php
              $existingGranted = collect();
              if ($premisRights->isNotEmpty()) {
                  $firstPr = $premisRights->first();
                  $existingGranted = $grantedRights[$firstPr->id] ?? collect();
              }
            @endphp

            @forelse($existingGranted as $idx => $gr)
              <div class="granted-row" data-index="{{ $idx }}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <strong>Granted Right #{{ $idx + 1 }}</strong>
                  <button type="button" class="btn btn-sm btn-outline-danger remove-granted"><i class="fas fa-times"></i> {{ __('Remove') }}</button>
                </div>
                <div class="row">
                  <div class="col-md-3 mb-2">
                    <label class="form-label">{{ __('Act') }}</label>
                    <select name="granted[{{ $idx }}][act_id]" class="form-select">
                      <option value="">-- Select --</option>
                      @foreach($actTerms as $at)
                        <option value="{{ $at->id }}" @if($gr->act_id == $at->id) selected @endif>{{ $at->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="form-label">{{ __('Restriction') }}</label>
                    <select name="granted[{{ $idx }}][restriction]" class="form-select">
                      <option value="0" @if($gr->restriction == 0) selected @endif>Allow</option>
                      <option value="1" @if($gr->restriction == 1) selected @endif>Disallow</option>
                      <option value="2" @if($gr->restriction == 2) selected @endif>Conditional</option>
                    </select>
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="form-label">{{ __('Start Date') }}</label>
                    <input type="date" name="granted[{{ $idx }}][start_date]" class="form-control" value="{{ $gr->start_date }}">
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="form-label">{{ __('End Date') }}</label>
                    <input type="date" name="granted[{{ $idx }}][end_date]" class="form-control" value="{{ $gr->end_date }}">
                  </div>
                  <div class="col-md-3 mb-2">
                    <label class="form-label">{{ __('Notes') }}</label>
                    <textarea name="granted[{{ $idx }}][notes]" class="form-control" rows="1">{{ $gr->notes }}</textarea>
                  </div>
                </div>
              </div>
            @empty
              {{-- Empty state: JS will add rows --}}
            @endforelse
          </div>

          <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addGrantedBtn">
            <i class="fas fa-plus me-1"></i> {{ __('Add Granted Right') }}
          </button>
        </div>
      </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 4: Embargo                                            --}}
    {{-- ============================================================ --}}
    <div class="accordion-item mb-3">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sectionEmbargo">
          <i class="fas fa-lock me-2"></i> Embargo
          @if($embargo)
            <span class="badge bg-danger ms-2">{{ __('Active') }}</span>
          @endif
        </button>
      </h2>
      <div id="sectionEmbargo" class="accordion-collapse collapse" data-bs-parent="#rightsAccordion">
        <div class="accordion-body">

          @if($embargo)
            <div class="alert alert-warning mb-3">
              <strong><i class="fas fa-exclamation-triangle me-1"></i> Active Embargo</strong><br>
              Type: <strong>{{ ucfirst($embargo->embargo_type) }}</strong> |
              Since: <strong>{{ $embargo->start_date }}</strong>
              @if($embargo->end_date) | Until: <strong>{{ $embargo->end_date }}</strong> @endif
              @if($embargo->is_perpetual) | <span class="badge bg-dark">{{ __('Perpetual') }}</span> @endif
              <br>
              @if($embargo->reason) Reason: {{ $embargo->reason }} @endif
            </div>

            <div class="card border-danger mb-3">
              <div class="card-header bg-danger text-white"><strong>{{ __('Lift Embargo') }}</strong></div>
              <div class="card-body">
                <input type="hidden" name="lift_embargo_id" value="" id="liftEmbargoId">
                <div class="mb-3">
                  <label for="lift_reason" class="form-label">{{ __('Reason for Lifting') }}</label>
                  <textarea name="lift_reason" id="lift_reason" class="form-control" rows="2"></textarea>
                </div>
                <button type="button" class="btn btn-danger" id="liftEmbargoBtn"
                        onclick="if(confirm('Are you sure you want to lift this embargo?')){document.getElementById('liftEmbargoId').value='{{ $embargo->id }}';document.getElementById('rightsForm').submit();}">
                  <i class="fas fa-unlock me-1"></i> {{ __('Lift Embargo') }}
                </button>
              </div>
            </div>
            <hr>
            <p class="text-muted">Update the current embargo fields below, or lift it above and create a new one:</p>
          @endif

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="embargo_type" class="form-label">{{ __('Embargo Type') }}</label>
              <select name="embargo_type" id="embargo_type" class="form-select">
                <option value="">-- No embargo --</option>
                <option value="full" @if(old('embargo_type', $embargo->embargo_type ?? '') == 'full') selected @endif>Full</option>
                <option value="partial" @if(old('embargo_type', $embargo->embargo_type ?? '') == 'partial') selected @endif>Partial</option>
                <option value="metadata_only" @if(old('embargo_type', $embargo->embargo_type ?? '') == 'metadata_only') selected @endif>Metadata Only</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label for="embargo_start_date" class="form-label">{{ __('Start Date') }}</label>
              <input type="date" name="embargo_start_date" id="embargo_start_date" class="form-control"
                     value="{{ old('embargo_start_date', $embargo->start_date ?? '') }}">
            </div>
            <div class="col-md-4 mb-3" id="embargoEndDateWrap">
              <label for="embargo_end_date" class="form-label">{{ __('End Date') }}</label>
              <input type="date" name="embargo_end_date" id="embargo_end_date" class="form-control"
                     value="{{ old('embargo_end_date', $embargo->end_date ?? '') }}">
            </div>
          </div>

          <div class="row">
            <div class="col-md-12 mb-3">
              <label for="embargo_reason" class="form-label">{{ __('Reason') }}</label>
              <textarea name="embargo_reason" id="embargo_reason" class="form-control" rows="2">{{ old('embargo_reason', $embargo->reason ?? '') }}</textarea>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <div class="form-check">
                <input type="checkbox" name="embargo_is_perpetual" value="1" class="form-check-input" id="embargo_is_perpetual"
                       @if(old('embargo_is_perpetual', $embargo->is_perpetual ?? false)) checked @endif>
                <label class="form-check-label" for="embargo_is_perpetual">{{ __('Perpetual (no end date)') }}</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="form-check">
                <input type="checkbox" name="embargo_notify_on_expiry" value="1" class="form-check-input" id="embargo_notify_on_expiry"
                       @if(old('embargo_notify_on_expiry', $embargo->notify_on_expiry ?? true)) checked @endif>
                <label class="form-check-label" for="embargo_notify_on_expiry">{{ __('Notify on expiry') }}</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="embargo_notify_days_before" class="form-label">{{ __('Notify days before') }}</label>
              <input type="number" name="embargo_notify_days_before" id="embargo_notify_days_before" class="form-control"
                     value="{{ old('embargo_notify_days_before', $embargo->notify_days_before ?? 30) }}" min="1" max="365">
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>{{-- /accordion --}}

  {{-- Bottom bar --}}
  <div class="d-flex gap-2 mt-4 mb-5">
    <button type="submit" class="btn atom-btn-outline-success">
      <i class="fas fa-save me-1"></i> {{ __('Save') }}
    </button>
    <a href="{{ url('/' . $io->slug) }}" class="btn atom-btn-white">Cancel</a>
  </div>
</form>

@endsection

@push('js')
<script src="/vendor/ahg-theme-b5/js/vendor/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ---- Basis conditional fields ----
    var basisSelect = document.getElementById('premis_basis_id');
    var basisMap = { copyright: 'basisCopyright', license: 'basisLicense', statute: 'basisStatute' };

    function updateBasisFields() {
        var sel = basisSelect.options[basisSelect.selectedIndex];
        var basisName = sel ? (sel.getAttribute('data-name') || '') : '';
        Object.keys(basisMap).forEach(function(key) {
            var el = document.getElementById(basisMap[key]);
            if (el) {
                el.classList.toggle('active', basisName === key);
            }
        });
    }
    basisSelect.addEventListener('change', updateBasisFields);
    updateBasisFields();

    // ---- Granted rights: add/remove rows ----
    var grantedContainer = document.getElementById('grantedContainer');
    var addGrantedBtn = document.getElementById('addGrantedBtn');
    var grantedIndex = grantedContainer.querySelectorAll('.granted-row').length;

    var actOptions = '';
    @foreach($actTerms as $at)
        actOptions += '<option value="{{ $at->id }}">{{ addslashes($at->name) }}</option>';
    @endforeach

    addGrantedBtn.addEventListener('click', function() {
        var idx = grantedIndex++;
        var html = '<div class="granted-row" data-index="' + idx + '">'
            + '<div class="d-flex justify-content-between align-items-start mb-2">'
            + '<strong>Granted Right #' + (idx + 1) + '</strong>'
            + '<button type="button" class="btn btn-sm btn-outline-danger remove-granted"><i class="fas fa-times"></i> Remove</button>'
            + '</div>'
            + '<div class="row">'
            + '<div class="col-md-3 mb-2"><label class="form-label">Act</label>'
            + '<select name="granted[' + idx + '][act_id]" class="form-select"><option value="">-- Select --</option>' + actOptions + '</select></div>'
            + '<div class="col-md-2 mb-2"><label class="form-label">Restriction</label>'
            + '<select name="granted[' + idx + '][restriction]" class="form-select">'
            + '<option value="0">Allow</option><option value="1" selected>Disallow</option><option value="2">Conditional</option></select></div>'
            + '<div class="col-md-2 mb-2"><label class="form-label">Start Date</label>'
            + '<input type="date" name="granted[' + idx + '][start_date]" class="form-control"></div>'
            + '<div class="col-md-2 mb-2"><label class="form-label">End Date</label>'
            + '<input type="date" name="granted[' + idx + '][end_date]" class="form-control"></div>'
            + '<div class="col-md-3 mb-2"><label class="form-label">Notes</label>'
            + '<textarea name="granted[' + idx + '][notes]" class="form-control" rows="1"></textarea></div>'
            + '</div></div>';
        grantedContainer.insertAdjacentHTML('beforeend', html);
    });

    grantedContainer.addEventListener('click', function(e) {
        var btn = e.target.closest('.remove-granted');
        if (btn) {
            btn.closest('.granted-row').remove();
        }
    });

    // ---- Embargo: perpetual hides end date ----
    var perpetualCheck = document.getElementById('embargo_is_perpetual');
    var endDateWrap = document.getElementById('embargoEndDateWrap');

    function toggleEndDate() {
        endDateWrap.style.display = perpetualCheck.checked ? 'none' : 'block';
        if (perpetualCheck.checked) {
            document.getElementById('embargo_end_date').value = '';
        }
    }
    perpetualCheck.addEventListener('change', toggleEndDate);
    toggleEndDate();
});
</script>
@endpush
