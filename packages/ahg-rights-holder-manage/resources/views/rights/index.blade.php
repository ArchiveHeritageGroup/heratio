@extends('theme::layouts.1col')

@section('title', 'Rights')
@section('body-class', 'rights index')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug ?? 'Rights' }}</h1>
    <span class="small">Rights</span>
  </div>
@endsection

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p class="mb-0">{{ $error }}</p>
      @endforeach
    </div>
  @endif

  {{-- Existing rights records --}}
  @if(isset($rights) && count($rights) > 0)
    @foreach($rights as $right)
      <div class="card mb-3">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">{{ $right['basis_label'] ?? 'Rights record' }}</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <dl>
                @if($right['basis'] ?? null)<dt>Basis</dt><dd>{{ $right['basis'] }}</dd>@endif
                @if($right['start_date'] ?? null)<dt>Start date</dt><dd>{{ $right['start_date'] }}</dd>@endif
                @if($right['end_date'] ?? null)<dt>End date</dt><dd>{{ $right['end_date'] }}</dd>@endif
                @if($right['rights_holder_name'] ?? null)<dt>Rights holder</dt><dd>{{ $right['rights_holder_name'] }}</dd>@endif
              </dl>
            </div>
            <div class="col-md-6">
              <dl>
                @if($right['rights_note'] ?? null)<dt>Rights note</dt><dd>{{ $right['rights_note'] }}</dd>@endif
                @if($right['copyright_status'] ?? null)<dt>Copyright status</dt><dd>{{ $right['copyright_status'] }}</dd>@endif
                @if($right['copyright_status_date'] ?? null)<dt>Copyright status date</dt><dd>{{ $right['copyright_status_date'] }}</dd>@endif
                @if($right['copyright_jurisdiction'] ?? null)<dt>Copyright jurisdiction</dt><dd>{{ $right['copyright_jurisdiction'] }}</dd>@endif
                @if($right['copyright_note'] ?? null)<dt>Copyright note</dt><dd>{{ $right['copyright_note'] }}</dd>@endif
                @if($right['license_terms'] ?? null)<dt>License terms</dt><dd>{{ $right['license_terms'] }}</dd>@endif
                @if($right['license_note'] ?? null)<dt>License note</dt><dd>{{ $right['license_note'] }}</dd>@endif
                @if($right['statute_jurisdiction'] ?? null)<dt>Statute jurisdiction</dt><dd>{{ $right['statute_jurisdiction'] }}</dd>@endif
                @if($right['statute_note'] ?? null)<dt>Statute note</dt><dd>{{ $right['statute_note'] }}</dd>@endif
                @if($right['statute_determination_date'] ?? null)<dt>Statute determination date</dt><dd>{{ $right['statute_determination_date'] }}</dd>@endif
              </dl>
            </div>
          </div>

          {{-- Documentation identifier --}}
          @if(($right['identifier_type'] ?? null) || ($right['identifier_value'] ?? null) || ($right['identifier_role'] ?? null))
            <h6 class="text-muted mt-2">{{ __('Documentation identifier') }}</h6>
            <dl class="row">
              @if($right['identifier_type'] ?? null)<dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ $right['identifier_type'] }}</dd>@endif
              @if($right['identifier_value'] ?? null)<dt class="col-sm-3">Value</dt><dd class="col-sm-9">{{ $right['identifier_value'] }}</dd>@endif
              @if($right['identifier_role'] ?? null)<dt class="col-sm-3">Role</dt><dd class="col-sm-9">{{ $right['identifier_role'] }}</dd>@endif
            </dl>
          @endif

          {{-- Granted rights --}}
          @if(!empty($right['granted_rights']))
            <h6 class="text-muted mt-3">{{ __('Granted rights') }}</h6>
            <table class="table table-sm table-bordered">
              <thead>
                <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
                  <th>{{ __('Act') }}</th><th>{{ __('Restriction') }}</th><th>{{ __('Start date') }}</th><th>{{ __('End date') }}</th><th>{{ __('Notes') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($right['granted_rights'] as $gr)
                  <tr>
                    <td>{{ $gr['act'] ?? '' }}</td>
                    <td>
                      @if(($gr['restriction'] ?? null) === 0 || ($gr['restriction'] ?? null) === '0')
                        Allow
                      @elseif(($gr['restriction'] ?? null) === 1 || ($gr['restriction'] ?? null) === '1')
                        Disallow
                      @elseif(($gr['restriction'] ?? null) === 2 || ($gr['restriction'] ?? null) === '2')
                        Conditional
                      @else
                        {{ $gr['restriction'] ?? '' }}
                      @endif
                    </td>
                    <td>{{ $gr['start_date'] ?? '' }}</td>
                    <td>{{ $gr['end_date'] ?? '' }}</td>
                    <td>{{ $gr['notes'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    @endforeach
  @else
    <div class="alert alert-info">No rights records found for this object.</div>
  @endif

  {{-- Add new rights record form --}}
  @auth
    <div class="accordion mb-3" id="addRightsAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="addRightsHeading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#addRightsCollapse" aria-expanded="false" aria-controls="addRightsCollapse">
            {{ __('Add new rights record') }}
          </button>
        </h2>
        <div id="addRightsCollapse" class="accordion-collapse collapse" aria-labelledby="addRightsHeading" data-bs-parent="#addRightsAccordion">
          <div class="accordion-body">

            <form action="{{ route('rights.store', $resource->slug) }}" method="POST" id="rights-create-form">
              @csrf

              {{-- Section 1: Rights Basis --}}
              <div class="accordion mb-3" id="rightsBasisAccordion">
                <div class="accordion-item">
                  <h2 class="accordion-header" id="rightsBasisHeading">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rightsBasisCollapse" aria-expanded="true" aria-controls="rightsBasisCollapse">
                      {{ __('Rights basis') }}
                    </button>
                  </h2>
                  <div id="rightsBasisCollapse" class="accordion-collapse collapse show" aria-labelledby="rightsBasisHeading">
                    <div class="accordion-body">

                      <div class="mb-3">
                        <label for="basis_id" class="form-label">{{ __('Basis') }}</label>
                        <select class="form-select" id="basis_id" name="basis_id" required>
                          <option value="">-- Select basis --</option>
                          @if(isset($basisTerms))
                            @foreach($basisTerms as $term)
                              <option value="{{ $term->id }}" data-name="{{ strtolower($term->name) }}">{{ $term->name }}</option>
                            @endforeach
                          @endif
                        </select>
                      </div>

                      {{-- Copyright fields (conditional) --}}
                      <div id="copyright-fields" class="d-none">
                        <hr>
                        <h6>{{ __('Copyright') }}</h6>
                        <div class="mb-3">
                          <label for="copyright_status_id" class="form-label">{{ __('Copyright status') }}</label>
                          <select class="form-select" id="copyright_status_id" name="copyright_status_id">
                            <option value="">-- Select --</option>
                            @if(isset($copyrightStatusTerms))
                              @foreach($copyrightStatusTerms as $term)
                                <option value="{{ $term->id }}">{{ $term->name }}</option>
                              @endforeach
                            @endif
                          </select>
                        </div>
                        <div class="mb-3">
                          <label for="copyright_status_date" class="form-label">{{ __('Copyright status date') }}</label>
                          <input type="date" class="form-control" id="copyright_status_date" name="copyright_status_date">
                        </div>
                        <div class="mb-3">
                          <label for="copyright_jurisdiction" class="form-label">{{ __('Copyright jurisdiction') }}</label>
                          <input type="text" class="form-control" id="copyright_jurisdiction" name="copyright_jurisdiction" maxlength="1024">
                        </div>
                        <div class="mb-3">
                          <label for="copyright_note" class="form-label">{{ __('Copyright note') }}</label>
                          <textarea class="form-control" id="copyright_note" name="copyright_note" rows="2"></textarea>
                        </div>
                      </div>

                      {{-- License fields (conditional) --}}
                      <div id="license-fields" class="d-none">
                        <hr>
                        <h6>{{ __('License') }}</h6>
                        <div class="mb-3">
                          <label for="license_terms" class="form-label">{{ __('License terms') }}</label>
                          <textarea class="form-control" id="license_terms" name="license_terms" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                          <label for="license_note" class="form-label">{{ __('License note') }}</label>
                          <textarea class="form-control" id="license_note" name="license_note" rows="2"></textarea>
                        </div>
                      </div>

                      {{-- Statute fields (conditional) --}}
                      <div id="statute-fields" class="d-none">
                        <hr>
                        <h6>{{ __('Statute') }}</h6>
                        <div class="mb-3">
                          <label for="statute_jurisdiction" class="form-label">{{ __('Statute jurisdiction') }}</label>
                          <input type="text" class="form-control" id="statute_jurisdiction" name="statute_jurisdiction">
                        </div>
                        <div class="mb-3">
                          <label for="statute_determination_date" class="form-label">{{ __('Statute determination date') }}</label>
                          <input type="date" class="form-control" id="statute_determination_date" name="statute_determination_date">
                        </div>
                        <div class="mb-3">
                          <label for="statute_note" class="form-label">{{ __('Statute note') }}</label>
                          <textarea class="form-control" id="statute_note" name="statute_note" rows="2"></textarea>
                        </div>
                      </div>

                      <hr>

                      <div class="row">
                        <div class="col-md-6">
                          <div class="mb-3">
                            <label for="start_date" class="form-label">{{ __('Start date') }}</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="mb-3">
                            <label for="end_date" class="form-label">{{ __('End date') }}</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                          </div>
                        </div>
                      </div>

                      <div class="mb-3">
                        <label for="rights_holder_name" class="form-label">{{ __('Rights holder') }}</label>
                        <input type="text" class="form-control" id="rights_holder_name" name="rights_holder_name" autocomplete="off" placeholder="{{ __('Type to search rights holders...') }}">
                        <div id="rights-holder-autocomplete" class="list-group mt-1" style="position:relative;z-index:1000;"></div>
                      </div>

                      <div class="mb-3">
                        <label for="rights_note" class="form-label">{{ __('Rights note') }}</label>
                        <textarea class="form-control" id="rights_note" name="rights_note" rows="2"></textarea>
                      </div>

                      <hr>
                      <h6>{{ __('Documentation identifier') }}</h6>
                      <div class="row">
                        <div class="col-md-4">
                          <div class="mb-3">
                            <label for="identifier_type" class="form-label">{{ __('Type') }}</label>
                            <input type="text" class="form-control" id="identifier_type" name="identifier_type">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="mb-3">
                            <label for="identifier_value" class="form-label">{{ __('Value') }}</label>
                            <input type="text" class="form-control" id="identifier_value" name="identifier_value">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="mb-3">
                            <label for="identifier_role" class="form-label">{{ __('Role') }}</label>
                            <input type="text" class="form-control" id="identifier_role" name="identifier_role">
                          </div>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
              </div>

              {{-- Section 2: Granted Rights --}}
              <div class="accordion mb-3" id="grantedRightsAccordion">
                <div class="accordion-item">
                  <h2 class="accordion-header" id="grantedRightsHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#grantedRightsCollapse" aria-expanded="false" aria-controls="grantedRightsCollapse">
                      {{ __('Granted rights') }}
                    </button>
                  </h2>
                  <div id="grantedRightsCollapse" class="accordion-collapse collapse" aria-labelledby="grantedRightsHeading">
                    <div class="accordion-body">

                      <div id="granted-rights-container">
                        <div class="granted-right-row border rounded p-3 mb-3">
                          <div class="row">
                            <div class="col-md-4">
                              <div class="mb-3">
                                <label class="form-label">{{ __('Act') }}</label>
                                <select class="form-select" name="granted_act[]">
                                  <option value="">-- Select act --</option>
                                  @if(isset($actTerms))
                                    @foreach($actTerms as $term)
                                      <option value="{{ $term->id }}">{{ $term->name }}</option>
                                    @endforeach
                                  @endif
                                </select>
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="mb-3">
                                <label class="form-label">{{ __('Restriction') }}</label>
                                <select class="form-select" name="granted_restriction[]">
                                  <option value="0">{{ __('Allow') }}</option>
                                  <option value="1" selected>{{ __('Disallow') }}</option>
                                  <option value="2">{{ __('Conditional') }}</option>
                                </select>
                              </div>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-3">
                              <div class="mb-3">
                                <label class="form-label">{{ __('Start date') }}</label>
                                <input type="date" class="form-control" name="granted_start_date[]">
                              </div>
                            </div>
                            <div class="col-md-3">
                              <div class="mb-3">
                                <label class="form-label">{{ __('End date') }}</label>
                                <input type="date" class="form-control" name="granted_end_date[]">
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="mb-3">
                                <label class="form-label">{{ __('Notes') }}</label>
                                <textarea class="form-control" name="granted_notes[]" rows="1"></textarea>
                              </div>
                            </div>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-danger remove-granted-right">{{ __('Delete') }}</button>
                        </div>
                      </div>

                      <button type="button" class="btn btn-sm btn-outline-secondary" id="add-granted-right">{{ __('Add granted right') }}</button>

                    </div>
                  </div>
                </div>
              </div>

              {{-- Action bar --}}
              <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
                <li>
                  @php
                    $cancelRoute = 'informationobject.show';
                    if (($resource->level_of_description_id ?? null) && \Illuminate\Support\Facades\Schema::hasTable('level_of_description_sector')) {
                        $sector = \Illuminate\Support\Facades\DB::table('level_of_description_sector')
                            ->where('term_id', $resource->level_of_description_id)
                            ->whereNotIn('sector', ['archive'])
                            ->orderBy('display_order')
                            ->value('sector');
                        $sectorRoutes = ['library' => 'library.show', 'museum' => 'museum.show', 'gallery' => 'gallery.show', 'dam' => 'dam.show'];
                        if ($sector && isset($sectorRoutes[$sector]) && \Illuminate\Support\Facades\Route::has($sectorRoutes[$sector])) {
                            $cancelRoute = $sectorRoutes[$sector];
                        }
                    }
                  @endphp
                  <a href="{{ route($cancelRoute, $resource->slug) }}" class="btn atom-btn-outline-light">Cancel</a>
                </li>
                <li>
                  <input type="submit" class="btn atom-btn-outline-success" value="Save">
                </li>
              </ul>

            </form>

          </div>
        </div>
      </div>
    </div>
  @endauth

@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <li><a href="{{ route('rights.add', $resource->slug ?? '') }}" class="btn atom-btn-outline-light">Add new rights</a></li>
    </ul>
  @endauth
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basis field conditional show/hide
    var basisSelect = document.getElementById('basis_id');
    var copyrightFields = document.getElementById('copyright-fields');
    var licenseFields = document.getElementById('license-fields');
    var statuteFields = document.getElementById('statute-fields');

    function updateBasisFields() {
        var selected = basisSelect.options[basisSelect.selectedIndex];
        var basisName = selected ? (selected.dataset.name || '').toLowerCase() : '';

        copyrightFields.classList.add('d-none');
        licenseFields.classList.add('d-none');
        statuteFields.classList.add('d-none');

        if (basisName === 'copyright') {
            copyrightFields.classList.remove('d-none');
        } else if (basisName === 'license') {
            licenseFields.classList.remove('d-none');
        } else if (basisName === 'statute') {
            statuteFields.classList.remove('d-none');
        }
    }

    basisSelect.addEventListener('change', updateBasisFields);
    updateBasisFields();

    // Add granted right row
    var container = document.getElementById('granted-rights-container');
    var addBtn = document.getElementById('add-granted-right');

    addBtn.addEventListener('click', function() {
        var firstRow = container.querySelector('.granted-right-row');
        var newRow = firstRow.cloneNode(true);
        // Clear values
        newRow.querySelectorAll('select').forEach(function(s) { s.selectedIndex = 0; });
        newRow.querySelectorAll('input').forEach(function(i) { i.value = ''; });
        newRow.querySelectorAll('textarea').forEach(function(t) { t.value = ''; });
        container.appendChild(newRow);
        bindRemoveButtons();
    });

    // Remove granted right row
    function bindRemoveButtons() {
        container.querySelectorAll('.remove-granted-right').forEach(function(btn) {
            btn.onclick = function() {
                if (container.querySelectorAll('.granted-right-row').length > 1) {
                    btn.closest('.granted-right-row').remove();
                }
            };
        });
    }
    bindRemoveButtons();

    // Rights holder autocomplete
    var rhInput = document.getElementById('rights_holder_name');
    var rhResults = document.getElementById('rights-holder-autocomplete');
    var rhTimer = null;

    if (rhInput) {
        rhInput.addEventListener('input', function() {
            var q = this.value.trim();
            if (q.length < 2) { rhResults.innerHTML = ''; return; }
            clearTimeout(rhTimer);
            rhTimer = setTimeout(function() {
                fetch('/rightsholder/browse?format=json&query=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        rhResults.innerHTML = '';
                        var items = data.hits || data.data || data;
                        if (!Array.isArray(items)) items = [];
                        items.forEach(function(item) {
                            var a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = item.name || item.authorized_form_of_name || '';
                            a.addEventListener('click', function(e) {
                                e.preventDefault();
                                rhInput.value = this.textContent;
                                rhResults.innerHTML = '';
                            });
                            rhResults.appendChild(a);
                        });
                    })
                    .catch(function() { rhResults.innerHTML = ''; });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!rhInput.contains(e.target) && !rhResults.contains(e.target)) {
                rhResults.innerHTML = '';
            }
        });
    }
});
</script>
@endpush
