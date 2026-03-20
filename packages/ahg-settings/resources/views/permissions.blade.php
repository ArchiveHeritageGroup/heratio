@extends('theme::layouts.1col')
@section('title', 'Permissions')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Permissions</h1>

    <form method="post" action="{{ route('settings.permissions') }}" autocomplete="off">
      @csrf

      <div class="accordion mb-3" id="permissionsAccordion">

        {{-- PREMIS access permissions --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="permissions-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#permissions-collapse" aria-expanded="false" aria-controls="permissions-collapse">
              PREMIS access permissions
            </button>
          </h2>
          <div id="permissions-collapse" class="accordion-collapse collapse" aria-labelledby="permissions-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">PREMIS act</label>
                <select name="granted_right" class="form-select">
                  @foreach ($acts as $act)
                    <option value="{{ $act->id }}">{{ $act->name }}</option>
                  @endforeach
                </select>
              </div>

              <h3 class="fs-6 mb-2">Permissions</h3>

              <div class="table-responsive mb-3">
                <table class="table table-bordered mb-0">
                  <colgroup><col></colgroup>
                  <colgroup span="3"></colgroup>
                  <colgroup span="3"></colgroup>
                  <colgroup span="3"></colgroup>
                  <tr>
                    <th rowspan="2" scope="colgroup" class="text-center">Basis</th>
                    <th colspan="3" scope="colgroup" class="text-center">Allow</th>
                    <th colspan="3" scope="colgroup" class="text-center">Conditional</th>
                    <th colspan="3" scope="colgroup" class="text-center">Disallow</th>
                  </tr>
                  <tr>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Master</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Reference</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Thumb</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Master</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Reference</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Thumb</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Master</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Reference</span></th>
                    <th scope="col" class="text-center"><span class="btn btn-sm btn-outline-secondary w-100">Thumb</span></th>
                  </tr>
                  @foreach ($basis as $basisSlug => $basisName)
                    <tr>
                      <th class="text-end" scope="row">{{ $basisName }}</th>
                      @foreach (['allow', 'conditional', 'disallow'] as $perm)
                        @foreach (['master', 'reference', 'thumb'] as $repr)
                          <td class="text-center">
                            <input type="radio" name="permissions[{{ $basisSlug }}][{{ $repr }}]" value="{{ $perm }}" class="form-check-input">
                          </td>
                        @endforeach
                      @endforeach
                    </tr>
                  @endforeach
                </table>
              </div>

              <div class="text-end">
                <div class="btn-group" role="group" aria-label="Permission toggles">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('#permissions-collapse input[type=radio]').forEach(r=>r.checked=true)">All</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('#permissions-collapse input[type=radio]').forEach(r=>r.checked=false)">None</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- PREMIS access statements --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="statements-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#statements-collapse" aria-expanded="false" aria-controls="statements-collapse">
              PREMIS access statements
            </button>
          </h2>
          <div id="statements-collapse" class="accordion-collapse collapse" aria-labelledby="statements-heading">
            <div class="accordion-body">
              @php $firstKey = array_key_first($basis); @endphp

              <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach ($basis as $basisSlug => $basisName)
                  <li class="nav-item" role="presentation">
                    <button
                      class="nav-link{{ $firstKey === $basisSlug ? ' active' : '' }}"
                      id="{{ $basisSlug }}-tab"
                      type="button"
                      role="tab"
                      aria-controls="{{ $basisSlug }}-pane"
                      aria-selected="{{ $firstKey === $basisSlug ? 'true' : 'false' }}"
                      data-bs-toggle="tab"
                      data-bs-target="#{{ $basisSlug }}-pane">
                      {{ $basisName }}
                    </button>
                  </li>
                @endforeach
              </ul>

              <div class="tab-content">
                @foreach ($basis as $basisSlug => $basisName)
                  <div
                    class="tab-pane fade{{ $firstKey === $basisSlug ? ' show active' : '' }}"
                    id="{{ $basisSlug }}-pane"
                    role="tabpanel"
                    aria-labelledby="{{ $basisSlug }}-tab"
                  >
                    <div class="mb-3">
                      <label class="form-label">Disallow statement</label>
                      <textarea name="access_statements[{{ $basisSlug }}_disallow]" class="form-control" rows="3">{{ e($accessStatements["{$basisSlug}_disallow"] ?? '') }}</textarea>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Conditional statement</label>
                      <textarea name="access_statements[{{ $basisSlug }}_conditional]" class="form-control" rows="3">{{ e($accessStatements["{$basisSlug}_conditional"] ?? '') }}</textarea>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>

        {{-- Copyright statement --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="copyright-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#copyright-collapse" aria-expanded="false" aria-controls="copyright-collapse">
              Copyright statement
            </button>
          </h2>
          <div id="copyright-collapse" class="accordion-collapse collapse" aria-labelledby="copyright-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable copyright statement</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="copyrightStatementEnabled" id="copyright_enabled_no" value="0" {{ $copyrightStatementEnabled != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="copyright_enabled_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="copyrightStatementEnabled" id="copyright_enabled_yes" value="1" {{ $copyrightStatementEnabled == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="copyright_enabled_yes">Yes</label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Copyright statement</label>
                <textarea name="copyrightStatement" class="form-control" rows="5">{{ e($copyrightStatement) }}</textarea>
                <small class="text-muted">When enabled the following text will appear whenever a user tries to download a digital object master with an associated rights statement where the Basis = copyright and the Restriction = conditional. You can style and customize the text as in a static page.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Apply to every digital object</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="copyrightStatementApplyGlobally" id="copyright_global_no" value="0" {{ $copyrightStatementApplyGlobally != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="copyright_global_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="copyrightStatementApplyGlobally" id="copyright_global_yes" value="1" {{ $copyrightStatementApplyGlobally == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="copyright_global_yes">Yes</label>
                  </div>
                </div>
                <small class="text-muted">When enabled, the copyright pop-up will be applied to every digital object, regardless of whether there is an accompanying Rights statement.</small>
              </div>
            </div>
          </div>
        </div>

        {{-- Preservation system access statement --}}
        <div class="accordion-item">
          <h2 class="accordion-header" id="preservation-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#preservation-collapse" aria-expanded="false" aria-controls="preservation-collapse">
              Preservation system access statement
            </button>
          </h2>
          <div id="preservation-collapse" class="accordion-collapse collapse" aria-labelledby="preservation-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable access statement</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="preservationStatementEnabled" id="pres_enabled_no" value="0" {{ $preservationEnabled != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="pres_enabled_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="preservationStatementEnabled" id="pres_enabled_yes" value="1" {{ $preservationEnabled == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="pres_enabled_yes">Yes</label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Access statement</label>
                <textarea name="preservationStatement" class="form-control" rows="5">{{ e($preservationStatement) }}</textarea>
                <small class="text-muted">When enabled the text above will appear in the digital object metadata section to describe how a user may access the original and preservation copy of the file stored in a linked digital preservation system. The text appears in the "Permissions" field. When disabled, the "Permissions" field is not displayed.</small>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="actions mb-3" style="background:#495057 !important;border-radius:.375rem;padding:1rem;display:block;">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </div>

    </form>
  </div>
</div>
@endsection
