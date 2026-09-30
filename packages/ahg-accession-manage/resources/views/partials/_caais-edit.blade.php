{{--
  CAAIS 1.0 profile - accession edit form (heratio#1514).
  The repository link (CAAIS 1.1) is shown whenever the tables exist; the rest
  only when the profile is switched on (ahg_settings accession_caais_enabled).
  Every option list comes from ahg_dropdown via CaaisProfileService::choices().
--}}
@if($caaisInstalled ?? false)
@php
  $cz = $caaisChoices;
  $rowsFor = function (string $key, array $fields) use ($caais) {
      $rows = array_values((array) old('caais.'.$key, $caais[$key] ?? []));
      $blank = array_fill_keys($fields, '');
      $rows[] = $blank; // one empty row to type into
      return $rows;
  };
  $caaisErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'caais'))->flatten();
@endphp
      <div class="accordion-item">
        <h2 class="accordion-header" id="caais-heading">
          <button class="accordion-button {{ $caaisErrors->isNotEmpty() ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#caais-collapse" aria-expanded="{{ $caaisErrors->isNotEmpty() ? 'true' : 'false' }}" aria-controls="caais-collapse">
            {{ ($caaisEnabled ?? false) ? __('Repository and CAAIS profile') : __('Repository') }}
          </button>
        </h2>
        <div id="caais-collapse" class="accordion-collapse collapse {{ $caaisErrors->isNotEmpty() ? 'show' : '' }}" aria-labelledby="caais-heading">
          <div class="accordion-body">
            @if($caaisErrors->isNotEmpty())
              <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                  @foreach($caaisErrors as $msg)<li>{{ $msg }}</li>@endforeach
                </ul>
              </div>
            @endif

            <div class="mb-3">
              <label for="caais_repository_id" class="form-label">{{ __('Repository') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <select name="caais[repository_id]" id="caais_repository_id" class="form-select @error('caais.repository_id') is-invalid @enderror">
                <option value=""></option>
                @foreach($caaisRepositories as $rid => $rname)
                  <option value="{{ $rid }}" @selected((string) old('caais.repository_id', $caais['repository_id'] ?? '') === (string) $rid)>{{ $rname }}</option>
                @endforeach
              </select>
              <div class="form-text">{{ __('The institution that accepts legal responsibility for the accessioned material (CAAIS 1.1).') }}</div>
            </div>

            @if($caaisEnabled ?? false)
            <input type="hidden" name="caais[_profile]" value="1">

            {{-- 2.1.6 Source confidentiality --}}
            <h3 class="fs-6 mt-4 mb-2">{{ __('Source confidentiality') }} <small class="text-muted">(CAAIS 2.1.6)</small></h3>
            @if(empty($caaisSources))
              <p class="form-text">{{ __('Link a donor and save first; each linked source can then be marked confidential here.') }}</p>
            @else
              <div class="table-responsive mb-3">
                <table class="table table-bordered mb-0">
                  <thead><tr><th id="caais-src-name">{{ __('Source') }}</th><th id="caais-src-conf" class="w-50">{{ __('Confidentiality') }}</th></tr></thead>
                  <tbody>
                    @foreach($caaisSources as $src)
                      <tr>
                        <td>{{ $src['name'] }}</td>
                        <td>
                          <select name="caais[sources][{{ $src['id'] }}]" class="form-select form-select-sm" aria-labelledby="caais-src-conf">
                            <option value="">{{ __('None - may be shared') }}</option>
                            @foreach($cz['confidentiality'] as $code => $label)
                              <option value="{{ $code }}" @selected(old('caais.sources.'.$src['id'], $caais['sources'][$src['id']] ?? '') === $code)>{{ $label }}</option>
                            @endforeach
                          </select>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif

            {{-- 3.2 Extent statements --}}
            <h3 class="fs-6 mt-4 mb-2">{{ __('Extent statements') }} <small class="text-muted">(CAAIS 3.2)</small></h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0 caais-repeat" data-caais-key="extents">
                <thead>
                  <tr>
                    <th id="caais-ext-type">{{ __('Extent type') }}</th>
                    <th id="caais-ext-qty">{{ __('Quantity') }}</th>
                    <th id="caais-ext-unit">{{ __('Unit') }}</th>
                    <th id="caais-ext-content">{{ __('Content type') }}</th>
                    <th id="caais-ext-carrier">{{ __('Carrier type') }}</th>
                    <th id="caais-ext-formats">{{ __('Digital file formats') }}</th>
                    <th id="caais-ext-note">{{ __('Note') }}</th>
                    <th><span class="visually-hidden">{{ __('Actions') }}</span></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($rowsFor('extents', ['extent_type', 'quantity', 'is_estimate', 'unit', 'content_type', 'carrier_type', 'digital_file_formats', 'note']) as $i => $row)
                    <tr>
                      <td>
                        <select name="caais[extents][{{ $i }}][extent_type]" class="form-select form-select-sm" aria-labelledby="caais-ext-type">
                          <option value=""></option>
                          @foreach($cz['extent_type'] as $code => $label)<option value="{{ $code }}" @selected(($row['extent_type'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td>
                        <input type="number" step="any" min="0" name="caais[extents][{{ $i }}][quantity]" value="{{ \AhgAccessionManage\Services\CaaisProfileService::formatQuantity($row['quantity'] ?? null) }}" class="form-control form-control-sm" aria-labelledby="caais-ext-qty">
                        <div class="form-check mt-1">
                          <input type="hidden" name="caais[extents][{{ $i }}][is_estimate]" value="0">
                          <input type="checkbox" class="form-check-input" name="caais[extents][{{ $i }}][is_estimate]" value="1" id="caais-ext-est-{{ $i }}" @checked(! empty($row['is_estimate']))>
                          <label class="form-check-label small" for="caais-ext-est-{{ $i }}">{{ __('ca. (estimate)') }}</label>
                        </div>
                      </td>
                      <td>
                        <select name="caais[extents][{{ $i }}][unit]" class="form-select form-select-sm" aria-labelledby="caais-ext-unit">
                          <option value=""></option>
                          @foreach($cz['unit'] as $code => $label)<option value="{{ $code }}" @selected(($row['unit'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td>
                        <select name="caais[extents][{{ $i }}][content_type]" class="form-select form-select-sm" aria-labelledby="caais-ext-content">
                          <option value=""></option>
                          @foreach($cz['content_type'] as $code => $label)<option value="{{ $code }}" @selected(($row['content_type'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td>
                        <select name="caais[extents][{{ $i }}][carrier_type]" class="form-select form-select-sm" aria-labelledby="caais-ext-carrier">
                          <option value=""></option>
                          @foreach($cz['carrier_type'] as $code => $label)<option value="{{ $code }}" @selected(($row['carrier_type'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td><input type="text" name="caais[extents][{{ $i }}][digital_file_formats]" value="{{ $row['digital_file_formats'] ?? '' }}" maxlength="1024" class="form-control form-control-sm" aria-labelledby="caais-ext-formats"></td>
                      <td><textarea name="caais[extents][{{ $i }}][note]" rows="1" class="form-control form-control-sm" aria-labelledby="caais-ext-note">{{ $row['note'] ?? '' }}</textarea></td>
                      <td><button type="button" class="btn atom-btn-white caais-remove-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">{{ __('Delete row') }}</span></button></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="text-end mb-1"><button type="button" class="btn atom-btn-white caais-add-row" data-caais-target="extents"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('Add extent statement') }}</button></div>
            <div class="form-text mb-3">{{ __('Record at least the extent received. Add a row per material type if you work at that level of detail. The free-text Received extent units field above is exported as an extent note when no statement is recorded here.') }}</div>

            {{-- 3.4 Language of material --}}
            <h3 class="fs-6 mt-4 mb-2">{{ __('Language of material') }} <small class="text-muted">(CAAIS 3.4)</small></h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0 caais-repeat" data-caais-key="languages">
                <thead><tr><th id="caais-lang" class="w-30">{{ __('Language') }}</th><th id="caais-lang-note">{{ __('Statement') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                  @foreach($rowsFor('languages', ['language', 'note']) as $i => $row)
                    <tr>
                      <td>
                        <select name="caais[languages][{{ $i }}][language]" class="form-select form-select-sm" aria-labelledby="caais-lang">
                          <option value=""></option>
                          @foreach($cz['language'] as $code => $label)<option value="{{ $code }}" @selected(($row['language'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td><input type="text" name="caais[languages][{{ $i }}][note]" value="{{ $row['note'] ?? '' }}" maxlength="1024" class="form-control form-control-sm" placeholder="{{ __('e.g. with partial English translation') }}" aria-labelledby="caais-lang-note"></td>
                      <td><button type="button" class="btn atom-btn-white caais-remove-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">{{ __('Delete row') }}</span></button></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="text-end mb-3"><button type="button" class="btn atom-btn-white caais-add-row" data-caais-target="languages"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('Add language') }}</button></div>

            {{-- 4.3 Preservation requirements --}}
            <h3 class="fs-6 mt-4 mb-2">{{ __('Preservation requirements') }} <small class="text-muted">(CAAIS 4.3)</small></h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0 caais-repeat" data-caais-key="preservation">
                <thead><tr><th id="caais-pres-type" class="w-25">{{ __('Type') }}</th><th id="caais-pres-value">{{ __('Requirement') }}</th><th id="caais-pres-note">{{ __('Note') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                  @foreach($rowsFor('preservation', ['requirement_type', 'requirement_value', 'note']) as $i => $row)
                    <tr>
                      <td>
                        <select name="caais[preservation][{{ $i }}][requirement_type]" class="form-select form-select-sm" aria-labelledby="caais-pres-type">
                          <option value=""></option>
                          @foreach($cz['requirement_type'] as $code => $label)<option value="{{ $code }}" @selected(($row['requirement_type'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td><textarea name="caais[preservation][{{ $i }}][requirement_value]" rows="1" class="form-control form-control-sm" aria-labelledby="caais-pres-value">{{ $row['requirement_value'] ?? '' }}</textarea></td>
                      <td><textarea name="caais[preservation][{{ $i }}][note]" rows="1" class="form-control form-control-sm" aria-labelledby="caais-pres-note">{{ $row['note'] ?? '' }}</textarea></td>
                      <td><button type="button" class="btn atom-btn-white caais-remove-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">{{ __('Delete row') }}</span></button></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="text-end mb-3"><button type="button" class="btn atom-btn-white caais-add-row" data-caais-target="preservation"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('Add preservation requirement') }}</button></div>

            {{-- 5.1 Events --}}
            <h3 class="fs-6 mt-4 mb-2">{{ __('Transfer and accessioning events') }} <small class="text-muted">(CAAIS 5.1)</small></h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0 caais-repeat" data-caais-key="events">
                <thead><tr><th id="caais-ev-type" class="w-25">{{ __('Event type') }}</th><th id="caais-ev-date">{{ __('Date') }}</th><th id="caais-ev-agent">{{ __('Agent') }}</th><th id="caais-ev-note">{{ __('Note') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                  @foreach($rowsFor('events', ['event_type', 'event_date', 'agent', 'note']) as $i => $row)
                    <tr>
                      <td>
                        <select name="caais[events][{{ $i }}][event_type]" class="form-select form-select-sm" aria-labelledby="caais-ev-type">
                          <option value=""></option>
                          @foreach($cz['event_type'] as $code => $label)<option value="{{ $code }}" @selected(($row['event_type'] ?? '') === $code)>{{ $label }}</option>@endforeach
                        </select>
                      </td>
                      <td><input type="date" name="caais[events][{{ $i }}][event_date]" value="{{ $row['event_date'] ?? '' }}" class="form-control form-control-sm" aria-labelledby="caais-ev-date"></td>
                      <td><input type="text" name="caais[events][{{ $i }}][agent]" value="{{ $row['agent'] ?? '' }}" maxlength="255" class="form-control form-control-sm" aria-labelledby="caais-ev-agent"></td>
                      <td><textarea name="caais[events][{{ $i }}][note]" rows="1" class="form-control form-control-sm" aria-labelledby="caais-ev-note">{{ $row['note'] ?? '' }}</textarea></td>
                      <td><button type="button" class="btn atom-btn-white caais-remove-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">{{ __('Delete row') }}</span></button></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="text-end mb-1"><button type="button" class="btn atom-btn-white caais-add-row" data-caais-target="events"><i class="fas fa-plus me-1" aria-hidden="true"></i>{{ __('Add event') }}</button></div>
            <div class="form-text mb-3">{{ __('CAAIS requires at least the date the material was physically transferred and, if different, the date legal control passed to the repository.') }}</div>

            {{-- 7.1 Rules or conventions --}}
            <div class="mb-3">
              <label for="caais_rules" class="form-label">{{ __('Rules or conventions') }} <small class="text-muted">(CAAIS 7.1)</small> <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="text" name="caais[rules_or_conventions]" id="caais_rules" maxlength="1024" class="form-control @error('caais.rules_or_conventions') is-invalid @enderror"
                     value="{{ old('caais.rules_or_conventions', $caais['rules_or_conventions'] ?? '') }}" placeholder="{{ __('e.g. Canadian Archival Accession Information Standard 1.0') }}">
              <div class="form-text">{{ __('Creation and revision dates and agents (CAAIS 7.2) are recorded automatically on every save.') }}</div>
            </div>
            @endif
          </div>
        </div>
      </div>

@push('js')
<script>
(function () {
  // Repeatable CAAIS rows: clone the last row, clear it, renumber its
  // caais[key][N] names. Removing the only row just clears it.
  document.querySelectorAll('.caais-add-row').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tbody = document.querySelector('table.caais-repeat[data-caais-key="' + btn.dataset.caaisTarget + '"] tbody');
      var rows = tbody.querySelectorAll('tr');
      // A counter, not rows.length: after a delete, rows.length can repeat an index still in use.
      var next = parseInt(tbody.dataset.next || rows.length, 10);
      tbody.dataset.next = next + 1;
      var tr = rows[rows.length - 1].cloneNode(true);
      tr.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.name = el.name.replace(/\[(extents|languages|preservation|events)\]\[\d+\]/, '[$1][' + next + ']');
        if (el.id) { el.id = el.id.replace(/\d+$/, next); }
        if (el.type === 'checkbox') { el.checked = false; }
        else if (el.type !== 'hidden') { el.value = ''; }
      });
      tr.querySelectorAll('label[for]').forEach(function (l) { l.htmlFor = l.htmlFor.replace(/\d+$/, next); });
      tbody.appendChild(tr);
    });
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.caais-remove-row');
    if (!btn) { return; }
    var tr = btn.closest('tr');
    if (tr.parentNode.querySelectorAll('tr').length > 1) { tr.remove(); return; }
    tr.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(function (el) {
      if (el.type === 'checkbox') { el.checked = false; } else { el.value = ''; }
    });
  });
})();
</script>
@endpush
@endif
