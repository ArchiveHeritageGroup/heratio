@extends('theme::layouts.2col')

@section('title', 'Edit: ' . ($io->title ?? 'Untitled'))
@section('body-class', 'edit informationobject')

@section('sidebar')
  @if(isset($io->repository_id) && $io->repository_id)
    @php
      $repoLogo = \Illuminate\Support\Facades\DB::table('slug')
          ->where('object_id', $io->repository_id)
          ->value('slug');
    @endphp
    @if($repoLogo)
      <div class="repository-logo mb-3 mx-auto">
        <a class="text-decoration-none" href="{{ url('/repository/' . $repoLogo) }}">
          <img alt="{{ __('Go to repository') }}" class="img-fluid img-thumbnail border-4 shadow-sm bg-white"
               src="/uploads/r/{{ $repoLogo }}/conf/logo.png"
               onerror="this.parentElement.style.display='none'">
        </a>
      </div>
    @endif
  @endif
@endsection

@section('content')
  <h1>Item {{ $io->identifier ? $io->identifier . ' - ' : '' }}{{ $io->title ?? '[Untitled]' }}</h1>

  @php
    // Form-templates banner: list active templates for information_object so the user can switch
    // to a template-driven edit. Auto-resolves the same template the dispatcher would pick.
    $__formTemplates = collect();
    $__defaultTpl = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_form_template')) {
            $__svc = app(\AhgForms\Services\FormService::class);
            $__formTemplates = $__svc->getActiveTemplates('information_object');
            $__defaultTpl = $__svc->resolveTemplate('information_object', [
                'repository_id'           => $io->repository_id ?? null,
                'level_of_description_id' => $io->level_of_description_id ?? null,
            ]);
        }
    } catch (\Throwable $e) { /* ahg-forms not installed yet */ }
  @endphp
  @if($__formTemplates->isNotEmpty())
    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3 py-2 px-3">
      <div>
        <i class="fas fa-clipboard-list me-2 text-primary"></i>
        <strong>{{ __('Form templates available:') }}</strong>
        @if($__defaultTpl)
          <a href="{{ url('/forms/edit/information_object/' . $io->id . '/' . $__defaultTpl->id) }}"
             class="btn btn-sm btn-primary ms-2">
            Edit with “{{ $__defaultTpl->name }}”
          </a>
        @endif
        @if($__formTemplates->count() > 1)
          <div class="dropdown d-inline-block ms-1">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              {{ __('Other templates') }}
            </button>
            <ul class="dropdown-menu">
              @foreach($__formTemplates as $tpl)
                @if(!$__defaultTpl || (int)$tpl->id !== (int)$__defaultTpl->id)
                  <li>
                    <a class="dropdown-item" href="{{ url('/forms/edit/information_object/' . $io->id . '/' . $tpl->id) }}">
                      {{ $tpl->name }}
                      @if($tpl->is_default)<span class="badge bg-success ms-1">default</span>@endif
                    </a>
                  </li>
                @endif
              @endforeach
            </ul>
          </div>
        @endif
      </div>
      <small class="text-muted">{{ __('Or continue editing with the standard ISAD form below.') }}</small>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('informationobject.update', $io->slug) }}" id="editForm">
    @csrf
    @method('PUT')

    <div class="accordion mb-3">

      {{-- ===== 1. Identity area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="false" aria-controls="identity-collapse">
            {{ __('Identity area') }}
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse" aria-labelledby="identity-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="input-group">
                <input type="text" class="form-control" id="identifier" name="identifier"
                       value="{{ old('identifier', $io->identifier) }}">
                <button type="button" class="btn atom-btn-white" id="generate-identifier"
                        data-url="{{ url('/informationobject/generateIdentifier') }}">
                  <i class="fas fa-cog me-1" aria-hidden="true"></i>{{ __('Generate') }}
                </button>
              </div>
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Provide a specific local reference code, control number, or other unique identifier. The country and repository code will be automatically added from the linked repository record to form a full reference code. (ISAD 3.1.1)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            {{-- Alternative identifiers multi-row --}}
            <div class="mb-3">
              <label class="form-label">Alternative identifier(s) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <table class="table table-sm" id="altids-table">
                <thead>
                  <tr>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Value') }}</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($alternativeIdentifiers as $aiIdx => $ai)
                    <tr>
                      <td><input type="text" class="form-control form-control-sm" name="altIds[{{ $aiIdx }}][label]" value="{{ $ai->value ?? '' }}" placeholder="{{ __('e.g. Former reference') }}"></td>
                      <td><input type="text" class="form-control form-control-sm" name="altIds[{{ $aiIdx }}][value]" value="{{ $ai->value ?? '' }}"></td>
                      <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">{{ __('Remove') }}</button></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-altid-row">{{ __('Add alternative identifier') }}</button>
            </div>

            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="form-required text-danger" title="{{ __('This is a mandatory element.') }}">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                     value="{{ old('title', $io->title) }}" required>
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Provide either a formal title or a concise supplied title in accordance with the rules of multilevel description and national conventions. (ISAD 3.1.2)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            <div class="mb-3">
              <label for="alternate_title" class="form-label">Alternate title <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Use this field to record any alternative or parallel title(s) for the unit of description."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <input type="text" class="form-control" id="alternate_title" name="alternate_title"
                     value="{{ old('alternate_title', $io->alternate_title) }}">
            </div>

            <div class="mb-3">
              <label for="edition" class="form-label">Edition <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the edition statement for the unit of description, if applicable."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <input type="text" class="form-control" id="edition" name="edition"
                     value="{{ old('edition', $io->edition) }}">
            </div>

            {{-- Events (dates) multi-row --}}
            <div class="mb-3">
              <label class="form-label">Date(s) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Identify and record the date(s) of the unit of description. Identify the type of date given. Record as a single date or a range of dates as appropriate. (ISAD 3.1.3). The Date display field can be used to enter free-text date information, including typographical marks to express approximation, uncertainty, or qualification. Use the start and end fields to make the dates searchable."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <table class="table table-sm" id="events-table">
                <thead>
                  <tr>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Start') }}</th>
                    <th>{{ __('End') }}</th>
                    <th>{{ __('Actor') }}</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($events as $idx => $evt)
                    <tr>
                      <td>
                        <select class="form-select form-select-sm" name="events[{{ $idx }}][typeId]">
                          <option value="">- Select -</option>
                          @foreach($eventTypes as $et)
                            <option value="{{ $et->id }}" @selected($et->id == $evt->type_id)>{{ $et->name }}</option>
                          @endforeach
                        </select>
                      </td>
                      <td><input type="text" class="form-control form-control-sm" name="events[{{ $idx }}][date]" value="{{ $evt->date_display ?? '' }}" placeholder="{{ __('e.g. ca. 1900') }}"></td>
                      <td><input type="date" class="form-control form-control-sm" name="events[{{ $idx }}][startDate]" value="{{ $evt->start_date ?? '' }}"></td>
                      <td><input type="date" class="form-control form-control-sm" name="events[{{ $idx }}][endDate]" value="{{ $evt->end_date ?? '' }}"></td>
                      <td>
                        <input type="text" class="form-control form-control-sm" name="events[{{ $idx }}][actorName]" value="{{ $evt->actor_name ?? '' }}" placeholder="{{ __('Actor name') }}">
                        <input type="hidden" name="events[{{ $idx }}][actorId]" value="{{ $evt->actor_id ?? 0 }}">
                      </td>
                      <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">{{ __('Remove') }}</button></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-event-row">{{ __('Add date') }}</button>
            </div>

            <div class="mb-3">
              <label for="level_of_description_id" class="form-label">Level of description <span class="form-required text-danger" title="{{ __('This is a mandatory element.') }}">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the level of this unit of description. (ISAD 3.1.4)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <select class="form-select" id="level_of_description_id" name="level_of_description_id">
                <option value="">- Select -</option>
                @foreach($levels as $level)
                  <option value="{{ $level->id }}" @selected(old('level_of_description_id', $io->level_of_description_id) == $level->id)>
                    {{ $level->name }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Add new child levels (edit only) --}}
            <div class="mb-3">
              <label class="form-label">Add new child levels <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Identifier: Provide a specific local reference code, control number, or other unique identifier. Level of description: Record the level of this unit of description. Title: Provide either a formal title or a concise supplied title in accordance with the rules of multilevel description and national conventions."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <table class="table table-sm" id="childlevels-table">
                <thead>
                  <tr>
                    <th>{{ __('Identifier') }}</th>
                    <th>{{ __('Level') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-childlevel-row">{{ __('Add child level') }}</button>
            </div>

            <div class="mb-3">
              <label for="extent_and_medium" class="form-label">Extent and medium <span class="form-required text-danger" title="{{ __('This is a mandatory element.') }}">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the extent of the unit of description by giving the number of physical or logical units in arabic numerals and the unit of measurement. Give the specific medium (media) of the unit of description. Separate multiple extents with a linebreak. (ISAD 3.1.5)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="3">{{ old('extent_and_medium', $io->extent_and_medium) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 2. Context area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="context-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#context-collapse" aria-expanded="false" aria-controls="context-collapse">
            {{ __('Context area') }}
          </button>
        </h2>
        <div id="context-collapse" class="accordion-collapse collapse" aria-labelledby="context-heading">
          <div class="accordion-body">

            {{-- Name of creator(s) multi-row --}}
            <input type="hidden" name="_creatorsIncluded" value="1">
            @php
              $creatorItems = ($creators ?? collect())->map(function ($c) {
                  return ['id' => $c->id ?? 0, 'name' => $c->name ?? ''];
              })->toArray();
            @endphp
            @include('ahg-core::components.autocomplete', [
                'name'          => 'creators',
                'label'         => 'Name of creator(s)',
                'route'         => 'actor.autocomplete',
                'placeholder'   => 'Type to add creator...',
                'required'      => true,
                'helpText'      => 'Record the name of the organization(s) or the individual(s) responsible for the creation, accumulation and maintenance of the records in the unit of description. Search for an existing name in the authority records by typing the first few characters of the name. Alternatively, type a new name to create and link to a new authority record. (ISAD 3.2.1)',
                'idField'       => 'id',
                'nameField'     => 'name',
                'multi'         => true,
                'multiName'     => 'creatorIds[]',
                'existingItems' => $creatorItems,
            ])

            @php
              $repoDisplayName = '';
              if (old('repository_id', $io->repository_id)) {
                  $repoDisplayName = ($repositories ?? collect())->firstWhere('id', old('repository_id', $io->repository_id))->name ?? '';
              }
            @endphp
            @include('ahg-core::components.autocomplete', [
                'name'         => 'repository_id',
                'label'        => 'Repository',
                'route'        => 'repository.autocomplete',
                'value'        => old('repository_id', $io->repository_id ?? ''),
                'displayValue' => $repoDisplayName,
                'placeholder'  => 'Type to search repositories...',
                'required'     => false,
                'helpText'     => 'Record the name of the organization which has custody of the archival material. Search for an existing name in the archival institution records by typing the first few characters of the name. Alternatively, type a new name to create and link to a new archival institution record.',
                'idField'      => 'id',
                'nameField'    => 'name',
            ])

            <div class="mb-3">
              <label for="archival_history" class="form-label">Archival history <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the successive transfers of ownership, responsibility and/or custody of the unit of description and indicate those actions, such as history of the arrangement, production of contemporary finding aids, re-use of the records for other purposes or software migrations, that have contributed to its present structure and arrangement. Give the dates of these actions, insofar as they can be ascertained. If the archival history is unknown, record that information. (ISAD 3.2.3)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="archival_history" name="archival_history" rows="3">{{ old('archival_history', $io->archival_history) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="acquisition" class="form-label">Immediate source of acquisition or transfer <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the source from which the unit of description was acquired and the date and/or method of acquisition if any or all of this information is not confidential. If the source is unknown, record that information. Optionally, add accession numbers or codes. (ISAD 3.2.4)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="acquisition" name="acquisition" rows="3">{{ old('acquisition', $io->acquisition) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 3. Content and structure area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="content-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-collapse" aria-expanded="false" aria-controls="content-collapse">
            {{ __('Content and structure area') }}
          </button>
        </h2>
        <div id="content-collapse" class="accordion-collapse collapse" aria-labelledby="content-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Give a summary of the scope (such as, time periods, geography) and content, (such as documentary forms, subject matter, administrative processes) of the unit of description, appropriate to the level of description. (ISAD 3.3.1)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content', $io->scope_and_content) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record appraisal, destruction and scheduling actions taken on or planned for the unit of description, especially if they may affect the interpretation of the material. (ISAD 3.3.2)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="appraisal" name="appraisal" rows="3">{{ old('appraisal', $io->appraisal) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="accruals" class="form-label">Accruals <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Indicate if accruals are expected. Where appropriate, give an estimate of their quantity and frequency. (ISAD 3.3.3)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="accruals" name="accruals" rows="3">{{ old('accruals', $io->accruals) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="arrangement" class="form-label">System of arrangement <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Specify the internal structure, order and/or the system of classification of the unit of description. Note how these have been treated by the archivist. For electronic records, record or reference information on system design. (ISAD 3.3.4)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="arrangement" name="arrangement" rows="3">{{ old('arrangement', $io->arrangement) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 4. Conditions of access and use area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="conditions-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#conditions-collapse" aria-expanded="false" aria-controls="conditions-collapse">
            {{ __('Conditions of access and use area') }}
          </button>
        </h2>
        <div id="conditions-collapse" class="accordion-collapse collapse" aria-labelledby="conditions-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="access_conditions" class="form-label">Conditions governing access <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Specify the law or legal status, contract, regulation or policy that affects access to the unit of description. Indicate the extent of the period of closure and the date at which the material will open when appropriate. (ISAD 3.4.1)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="access_conditions" name="access_conditions" rows="3">{{ old('access_conditions', $io->access_conditions) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="reproduction_conditions" class="form-label">Conditions governing reproduction <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Give information about conditions, such as copyright, governing the reproduction of the unit of description after access has been provided. If the existence of such conditions is unknown, record this. If there are no conditions, no statement is necessary. (ISAD 3.4.2)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="3">{{ old('reproduction_conditions', $io->reproduction_conditions) }}</textarea>
            </div>

            {{-- Language(s) of material - multi-row select --}}
            @php
              $languageOptions = [
                'en' => 'English', 'af' => 'Afrikaans', 'nl' => 'Dutch', 'de' => 'German',
                'fr' => 'French', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Sesotho',
                'tn' => 'Setswana', 'nso' => 'Sepedi', 'ts' => 'Tsonga', 'ss' => 'Swati',
                've' => 'Venda', 'nr' => 'Ndebele', 'pt' => 'Portuguese', 'es' => 'Spanish',
                'it' => 'Italian', 'la' => 'Latin', 'grc' => 'Ancient Greek', 'he' => 'Hebrew',
                'ar' => 'Arabic', 'fa' => 'Persian', 'hi' => 'Hindi', 'zh' => 'Chinese',
                'ja' => 'Japanese', 'ko' => 'Korean', 'ru' => 'Russian', 'sw' => 'Swahili',
              ];
            @endphp
            <div class="mb-3">
              <label class="form-label">Language(s) of material <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="languages-list">
                @foreach($materialLanguages as $lIdx => $langCode)
                  <div class="input-group input-group-sm mb-1">
                    <select class="form-select form-select-sm" name="languages[]">
                      <option value="">-- Select language --</option>
                      @foreach($languageOptions as $code => $name)
                        <option value="{{ $code }}" @selected($langCode === $code)>{{ $name }} ({{ $code }})</option>
                      @endforeach
                      @if($langCode && !array_key_exists($langCode, $languageOptions))
                        <option value="{{ $langCode }}" selected>{{ $langCode }}</option>
                      @endif
                    </select>
                    <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary btn-add-lang-row" data-target="languages-list" data-name="languages[]">{{ __('Add language') }}</button>
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the language(s) of the materials comprising the unit of description. (ISAD 3.4.3)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            {{-- Script(s) of material - multi-row select --}}
            @php
              $scriptOptions = [
                'Latn' => 'Latin', 'Cyrl' => 'Cyrillic', 'Arab' => 'Arabic', 'Grek' => 'Greek',
                'Hebr' => 'Hebrew', 'Deva' => 'Devanagari', 'Hans' => 'Chinese (Simplified)',
                'Hant' => 'Chinese (Traditional)', 'Jpan' => 'Japanese', 'Kore' => 'Korean',
                'Thai' => 'Thai', 'Geor' => 'Georgian', 'Armn' => 'Armenian', 'Ethi' => 'Ethiopic',
              ];
            @endphp
            <div class="mb-3">
              <label class="form-label">Script(s) of material <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="scripts-list">
                @foreach($materialScripts as $sIdx => $scriptCode)
                  <div class="input-group input-group-sm mb-1">
                    <select class="form-select form-select-sm" name="scripts[]">
                      <option value="">-- Select script --</option>
                      @foreach($scriptOptions as $code => $name)
                        <option value="{{ $code }}" @selected($scriptCode === $code)>{{ $name }} ({{ $code }})</option>
                      @endforeach
                      @if($scriptCode && !array_key_exists($scriptCode, $scriptOptions))
                        <option value="{{ $scriptCode }}" selected>{{ $scriptCode }}</option>
                      @endif
                    </select>
                    <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary btn-add-script-row" data-target="scripts-list" data-name="scripts[]">{{ __('Add script') }}</button>
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the script(s) of the materials comprising the unit of description. (ISAD 3.4.3)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            <div class="mb-3">
              <label for="language_notes" class="form-label">Language and script notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Note any distinctive alphabets, scripts, symbol systems or abbreviations employed. (ISAD 3.4.3)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="language_notes" name="language_notes" rows="2">{{ old('language_notes', $io->language_notes ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="physical_characteristics" class="form-label">Physical characteristics and technical requirements <span class="badge bg-danger ms-1">{{ __('Required') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Indicate any important physical conditions, such as preservation requirements, that affect the use of the unit of description. Note any software and/or hardware required to access the unit of description."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="physical_characteristics" name="physical_characteristics" rows="3">{{ old('physical_characteristics', $io->physical_characteristics) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="finding_aids" class="form-label">Finding aids <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Give information about any finding aids that the repository or records creator may have that provide information relating to the context and contents of the unit of description. If appropriate, include information on where to obtain a copy. (ISAD 3.4.5)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="finding_aids" name="finding_aids" rows="3">{{ old('finding_aids', $io->finding_aids) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 5. Allied materials area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="allied-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#allied-collapse" aria-expanded="false" aria-controls="allied-collapse">
            {{ __('Allied materials area') }}
          </button>
        </h2>
        <div id="allied-collapse" class="accordion-collapse collapse" aria-labelledby="allied-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="location_of_originals" class="form-label">Existence and location of originals <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="If the original of the unit of description is available (either in the institution or elsewhere) record its location, together with any significant control numbers. If the originals no longer exist, or their location is unknown, give that information. (ISAD 3.5.1)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="location_of_originals" name="location_of_originals" rows="3">{{ old('location_of_originals', $io->location_of_originals) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="location_of_copies" class="form-label">Existence and location of copies <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="If the copy of the unit of description is available (either in the institution or elsewhere) record its location, together with any significant control numbers. (ISAD 3.5.2)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="location_of_copies" name="location_of_copies" rows="3">{{ old('location_of_copies', $io->location_of_copies) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="related_units_of_description" class="form-label">Related units of description <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record information about units of description in the same repository or elsewhere that are related by provenance or other association(s). Use appropriate introductory wording and explain the nature of the relationship. If the related unit of description is a finding aid, use the finding aids element of description (3.4.5) to make the reference to it. (ISAD 3.5.3)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="related_units_of_description" name="related_units_of_description" rows="3">{{ old('related_units_of_description', $io->related_units_of_description) }}</textarea>
            </div>

            {{-- Related descriptions --}}
            <div class="mb-3">
              <label class="form-label">Related descriptions <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="related-desc-list">
                @foreach($relatedMaterialDescriptions as $rdIdx => $rd)
                  <div class="input-group input-group-sm mb-1">
                    <input type="text" class="form-control" value="{{ $rd->title ?? '' }}" readonly>
                    <input type="hidden" name="relatedDescriptions[{{ $rdIdx }}][id]" value="{{ $rd->id }}">
                    <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                  </div>
                @endforeach
              </div>
              <div class="input-group input-group-sm mt-1">
                <input type="text" class="form-control" placeholder="{{ __('Type to add related description...') }}" autocomplete="off">
              </div>
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="To create a relationship between this description and another description held in the system, begin typing the name of the related description and select it from the autocomplete drop-down menu when it appears below. Multiple relationships can be created."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            {{-- Publication notes multi-row --}}
            <div class="mb-3">
              <label class="form-label">Publication notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="pubnotes-list">
                @foreach($publicationNotes as $pnIdx => $pn)
                  <div class="mb-1">
                    <div class="input-group input-group-sm">
                      <textarea class="form-control form-control-sm" name="publicationNotes[{{ $pnIdx }}][content]" rows="2">{{ $pn->content ?? '' }}</textarea>
                      <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                    </div>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-pubnote-row">{{ __('Add publication note') }}</button>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 6. Notes area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="notes-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#notes-collapse" aria-expanded="false" aria-controls="notes-collapse">
            {{ __('Notes area') }}
          </button>
        </h2>
        <div id="notes-collapse" class="accordion-collapse collapse" aria-labelledby="notes-heading">
          <div class="accordion-body">
            <table class="table table-sm" id="notes-table">
              <thead>
                <tr>
                  <th style="width:30%">{{ __('Type') }}</th>
                  <th>{{ __('Content') }}</th>
                  <th style="width:80px"></th>
                </tr>
              </thead>
              <tbody>
                @foreach($notes as $nIdx => $note)
                  <tr>
                    <td>
                      <select class="form-select form-select-sm" name="notes[{{ $nIdx }}][typeId]">
                        <option value="">- Select -</option>
                        @foreach($noteTypes as $nt)
                          <option value="{{ $nt->id }}" @selected($nt->id == $note->type_id)>{{ $nt->name }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td><textarea class="form-control form-control-sm" name="notes[{{ $nIdx }}][content]" rows="2">{{ $note->content ?? '' }}</textarea></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">{{ __('Remove') }}</button></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-note-row">{{ __('Add note') }}</button>
          </div>
        </div>
      </div>

      {{-- ===== 7. Access points ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            {{ __('Access points') }}
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">

            {{-- Subject access points --}}
            @php
              $subjectItems = ($subjects ?? collect())->map(function ($s) {
                  return ['id' => $s->term_id, 'name' => $s->name ?? ''];
              })->toArray();
            @endphp
            @include('ahg-core::components.autocomplete', [
                'name'          => 'subjectAccessPoints',
                'label'         => 'Subject access points',
                'route'         => 'term.autocomplete',
                'placeholder'   => 'Type to add subject...',
                'required'      => false,
                'idField'       => 'id',
                'nameField'     => 'name',
                'multi'         => true,
                'multiName'     => 'subjectAccessPointIds[]',
                'existingItems' => $subjectItems,
                'inputClass'    => 'form-control-sm',
                'extraParams'   => ['taxonomy_id' => 35],
            ])

            {{-- Place access points --}}
            @php
              $placeItems = ($places ?? collect())->map(function ($p) {
                  return ['id' => $p->term_id, 'name' => $p->name ?? ''];
              })->toArray();
            @endphp
            @include('ahg-core::components.autocomplete', [
                'name'          => 'placeAccessPoints',
                'label'         => 'Place access points',
                'route'         => 'term.autocomplete',
                'placeholder'   => 'Type to add place...',
                'required'      => false,
                'idField'       => 'id',
                'nameField'     => 'name',
                'multi'         => true,
                'multiName'     => 'placeAccessPointIds[]',
                'existingItems' => $placeItems,
                'inputClass'    => 'form-control-sm',
                'extraParams'   => ['taxonomy_id' => 42],
            ])

            {{-- Genre access points --}}
            @php
              $genreItems = ($genres ?? collect())->map(function ($g) {
                  return ['id' => $g->term_id, 'name' => $g->name ?? ''];
              })->toArray();
            @endphp
            @include('ahg-core::components.autocomplete', [
                'name'          => 'genreAccessPoints',
                'label'         => 'Genre access points',
                'route'         => 'term.autocomplete',
                'placeholder'   => 'Type to add genre...',
                'required'      => false,
                'idField'       => 'id',
                'nameField'     => 'name',
                'multi'         => true,
                'multiName'     => 'genreAccessPointIds[]',
                'existingItems' => $genreItems,
                'inputClass'    => 'form-control-sm',
                'extraParams'   => ['taxonomy_id' => 78],
            ])

            {{-- Name access points (subjects) --}}
            @php
              $nameApItems = ($nameAccessPoints ?? collect())->map(function ($n) {
                  return ['id' => $n->actor_id, 'name' => $n->name ?? ''];
              })->toArray();
            @endphp
            @include('ahg-core::components.autocomplete', [
                'name'          => 'nameAccessPoints',
                'label'         => 'Name access points (subjects)',
                'route'         => 'actor.autocomplete',
                'placeholder'   => 'Type to add name...',
                'required'      => false,
                'idField'       => 'id',
                'nameField'     => 'name',
                'multi'         => true,
                'multiName'     => 'nameAccessPointIds[]',
                'existingItems' => $nameApItems,
                'inputClass'    => 'form-control-sm',
            ])

          </div>
        </div>
      </div>

      {{-- ===== 8. Description control area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            {{ __('Description control area') }}
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="description_identifier" class="form-label">Description identifier <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record a unique description identifier in accordance with local and/or national conventions. If the description is to be used internationally, record the code of the country in which the description was created in accordance with the latest version of ISO 3166 - Codes for the representation of names of countries. Where the creator of the description is an international organisation, give the organisational identifier in place of the country code."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <input type="text" class="form-control" id="description_identifier" name="description_identifier"
                     value="{{ old('description_identifier', $io->description_identifier) }}">
            </div>

            <div class="mb-3">
              <label for="institution_responsible_identifier" class="form-label">Institution identifier <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the full authorised form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the description or, alternatively, record a code for the agency in accordance with the national or international agency code standard."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="institution_responsible_identifier" name="institution_responsible_identifier" rows="2">{{ old('institution_responsible_identifier', $io->institution_responsible_identifier) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules or conventions <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the international, national and/or local rules or conventions followed in preparing the description. (ISAD 3.7.2)"><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="rules" name="rules" rows="3">{{ old('rules', $io->rules) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="description_status_id" class="form-label">Status <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the current status of the description, indicating whether it is a draft, finalized and/or revised or deleted."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <select class="form-select" id="description_status_id" name="description_status_id">
                <option value="">-- Select --</option>
                @foreach($descriptionStatuses as $status)
                  <option value="{{ $status->id }}" @selected(old('description_status_id', $io->description_status_id) == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="description_detail_id" class="form-label">Level of detail <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record whether the description consists of a minimal, partial or full level of detail in accordance with relevant international and/or national guidelines and/or rules."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <select class="form-select" id="description_detail_id" name="description_detail_id">
                <option value="">-- Select --</option>
                @foreach($descriptionDetails as $detail)
                  <option value="{{ $detail->id }}" @selected(old('description_detail_id', $io->description_detail_id) == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the date(s) the entry was prepared and/or revised."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="revision_history" name="revision_history" rows="3">{{ old('revision_history', $io->revision_history) }}</textarea>
            </div>

            {{-- Language(s) of description - multi-row --}}
            <div class="mb-3">
              <label class="form-label">Language(s) of description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="langs-of-desc-list">
                @foreach($languagesOfDescription as $ldIdx => $ldCode)
                  <div class="input-group input-group-sm mb-1">
                    <select class="form-select form-select-sm" name="languagesOfDescription[]">
                      <option value="">-- Select language --</option>
                      @foreach($languageOptions as $code => $name)
                        <option value="{{ $code }}" @selected($ldCode === $code)>{{ $name }} ({{ $code }})</option>
                      @endforeach
                      @if($ldCode && !array_key_exists($ldCode, $languageOptions))
                        <option value="{{ $ldCode }}" selected>{{ $ldCode }}</option>
                      @endif
                    </select>
                    <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary btn-add-lang-row" data-target="langs-of-desc-list" data-name="languagesOfDescription[]">{{ __('Add language') }}</button>
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Indicate the language(s) used to create the description of the archival material."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            {{-- Script(s) of description - multi-row --}}
            <div class="mb-3">
              <label class="form-label">Script(s) of description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="scripts-of-desc-list">
                @foreach($scriptsOfDescription as $sdIdx => $sdCode)
                  <div class="input-group input-group-sm mb-1">
                    <select class="form-select form-select-sm" name="scriptsOfDescription[]">
                      <option value="">-- Select script --</option>
                      @foreach($scriptOptions as $code => $name)
                        <option value="{{ $code }}" @selected($sdCode === $code)>{{ $name }} ({{ $code }})</option>
                      @endforeach
                      @if($sdCode && !array_key_exists($sdCode, $scriptOptions))
                        <option value="{{ $sdCode }}" selected>{{ $sdCode }}</option>
                      @endif
                    </select>
                    <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary btn-add-script-row" data-target="scripts-of-desc-list" data-name="scriptsOfDescription[]">{{ __('Add script') }}</button>
              <span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Indicate the script(s) used to create the description of the archival material."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources <span class="badge bg-warning ms-1">{{ __('Recommended') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record citations for any external sources used in the archival description (such as the Scope and Content, Archival History, or Notes fields)."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <textarea class="form-control" id="sources" name="sources" rows="3">{{ old('sources', $io->sources) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="source_standard" class="form-label">Source standard <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span><span class="ms-1" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Record the standard used when entering the description of the archival material (e.g. ISAD(G), RAD, DACS)."><i class="fas fa-question-circle text-muted" style="cursor:help;"></i></span> </label>
              <input type="text" class="form-control" id="source_standard" name="source_standard"
                     value="{{ old('source_standard', $io->source_standard) }}">
            </div>

            {{-- Archivist's notes multi-row --}}
            <div class="mb-3">
              <label class="form-label">Archivist's notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div id="archnotes-list">
                @foreach($archivistNotes as $anIdx => $an)
                  <div class="mb-1">
                    <div class="input-group input-group-sm">
                      <textarea class="form-control form-control-sm" name="archivistNotes[{{ $anIdx }}][content]" rows="2">{{ $an->content ?? '' }}</textarea>
                      <button type="button" class="btn btn-outline-danger btn-remove-ap">{{ __('Remove') }}</button>
                    </div>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-archnote-row">{{ __("Add archivist's note") }}</button>
            </div>

          </div>
        </div>
      </div>

    </div>

    {{-- ===== Security Classification ===== --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="security-heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#security-collapse" aria-expanded="false">
          {{ __('Security Classification') }}
        </button>
      </h2>
      <div id="security-collapse" class="accordion-collapse collapse" aria-labelledby="security-heading">
        <div class="accordion-body">
          <div class="mb-3">
            <label for="security_classification_id" class="form-label">Classification level <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="security_classification_id" id="security_classification_id" class="form-select">
              <option value="">-- None --</option>
              @foreach($formChoices['securityLevels'] ?? [] as $level)
                <option value="{{ $level->id }}" @selected(old('security_classification_id', $io->security_classification_id ?? '') == $level->id)>{{ $level->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label for="security_reason" class="form-label">Reason <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <textarea name="security_reason" id="security_reason" class="form-control" rows="2">{{ old('security_reason', $io->security_reason ?? '') }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="security_review_date" class="form-label">Review date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="date" name="security_review_date" id="security_review_date" class="form-control" value="{{ old('security_review_date', $io->security_review_date ?? '') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label for="security_declassify_date" class="form-label">Declassify date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="date" name="security_declassify_date" id="security_declassify_date" class="form-control" value="{{ old('security_declassify_date', $io->security_declassify_date ?? '') }}">
            </div>
          </div>
          <div class="mb-3">
            <label for="security_handling_instructions" class="form-label">Handling instructions <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <textarea name="security_handling_instructions" id="security_handling_instructions" class="form-control" rows="2">{{ old('security_handling_instructions', $io->security_handling_instructions ?? '') }}</textarea>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="security_inherit_to_children" id="security_inherit_to_children" value="1" {{ old('security_inherit_to_children', $io->security_inherit_to_children ?? '') ? 'checked' : '' }}>
            <label class="form-check-label" for="security_inherit_to_children">Apply classification to children <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== Watermark Settings ===== --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="watermark-heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#watermark-collapse" aria-expanded="false">
          {{ __('Watermark Settings') }}
        </button>
      </h2>
      <div id="watermark-collapse" class="accordion-collapse collapse" aria-labelledby="watermark-heading">
        <div class="accordion-body">
          <p class="text-muted">Watermark settings are managed via the digital object interface.</p>
        </div>
      </div>
    </div>

    {{-- ===== Administration area ===== --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="admin-heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin-collapse" aria-expanded="false">
          {{ __('Administration area') }}
        </button>
      </h2>
      <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
        <div class="accordion-body">

          @if($parentTitle)
            <div class="mb-3">
              <label class="form-label">Part of <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <p class="form-control-plaintext">
                <a href="{{ url('/' . $parentSlug) }}">{{ $parentTitle }}</a>
              </p>
            </div>
          @endif

          <div class="mb-3">
            <label for="publication_status_id" class="form-label">Publication status <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="publication_status_id" id="publication_status_id" class="form-select">
              <option value="159" @selected(($publicationStatusId ?? 159) == 159)>{{ __('Draft') }}</option>
              <option value="160" @selected(($publicationStatusId ?? 159) == 160)>{{ __('Published') }}</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="collection_type_id" class="form-label">Collection type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="collection_type_id" id="collection_type_id" class="form-select">
              <option value="">-- None --</option>
              @foreach($collectionTypes ?? [] as $ct)
                <option value="{{ $ct->id }}" @selected(old('collection_type_id', $io->collection_type_id) == $ct->id)>{{ $ct->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="display_standard_id" class="form-label">Display standard <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="display_standard_id" id="display_standard_id" class="form-select">
              <option value="">- Use global default -</option>
              @foreach($displayStandards as $std)
                <option value="{{ $std->id }}" @selected(old('display_standard_id', $io->display_standard_id) == $std->id)>
                  {{ $std->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="updateDescendants" name="updateDescendants" value="1">
            <label class="form-check-label" for="updateDescendants">Make this the default for existing children <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          </div>

          <div class="mb-3">
            <label class="form-label">Source language <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <p class="form-control-plaintext">{{ $io->source_culture ?? '' }}</p>
          </div>

          @if($io->updated_at)
            <div class="mb-3">
              <label class="form-label">Last updated <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <p class="form-control-plaintext">{{ $io->updated_at }}</p>
            </div>
          @endif

        </div>
      </div>
    </div>

    </div>{{-- end main accordion --}}

    {{-- ===== Form actions ===== --}}
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      </ul>
    </section>
  </form>

  {{-- ===== Digital object upload/manage ===== --}}
  <div class="accordion mb-4" id="digitalObjectAccordion">
    @include('ahg-io-manage::partials._upload-form')
  </div>
@push('css')
<style>
.accordion-button {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button:not(.collapsed) {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
  box-shadow: none;
}
.accordion-button.collapsed {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'//%3e%3c/svg%3e");
}
.accordion-button:focus {
  box-shadow: 0 0 0 0.25rem var(--ahg-input-focus, rgba(0,88,55,0.25));
}
</style>
@endpush
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  // Initialize Bootstrap popovers for ISAD field help text
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
    new bootstrap.Popover(el, { html: false });
  });

  // Generate identifier button
  var genBtn = document.getElementById('generate-identifier');
  if (genBtn) {
    genBtn.addEventListener('click', function() {
      var url = this.getAttribute('data-url');
      fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (data.identifier) {
          document.getElementById('identifier').value = data.identifier;
        }
      });
    });
  }

  // Generic remove row handler
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-remove-row')) {
      e.target.closest('tr').remove();
    }
    if (e.target.classList.contains('btn-remove-ap')) {
      e.target.closest('.input-group, .mb-1').remove();
    }
  });

  // Add event row
  var addEventBtn = document.getElementById('add-event-row');
  if (addEventBtn) {
    addEventBtn.addEventListener('click', function() {
      var tbody = document.querySelector('#events-table tbody');
      var idx = tbody.querySelectorAll('tr').length;
      var eventTypeOptions = '<option value="">- Select -</option>';
      @foreach($eventTypes as $et)
        eventTypeOptions += '<option value="{{ $et->id }}">{{ addslashes($et->name) }}</option>';
      @endforeach
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><select class="form-select form-select-sm" name="events[' + idx + '][typeId]">' + eventTypeOptions + '</select></td>'
        + '<td><input type="text" class="form-control form-control-sm" name="events[' + idx + '][date]" placeholder="e.g. ca. 1900"></td>'
        + '<td><input type="date" class="form-control form-control-sm" name="events[' + idx + '][startDate]"></td>'
        + '<td><input type="date" class="form-control form-control-sm" name="events[' + idx + '][endDate]"></td>'
        + '<td><input type="text" class="form-control form-control-sm" name="events[' + idx + '][actorName]" placeholder="Actor name"><input type="hidden" name="events[' + idx + '][actorId]" value="0"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">Remove</button></td>';
      tbody.appendChild(tr);
    });
  }

  // Add alternative identifier row
  var addAltIdBtn = document.getElementById('add-altid-row');
  if (addAltIdBtn) {
    addAltIdBtn.addEventListener('click', function() {
      var tbody = document.querySelector('#altids-table tbody');
      var idx = tbody.querySelectorAll('tr').length;
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><input type="text" class="form-control form-control-sm" name="altIds[' + idx + '][label]" placeholder="e.g. Former reference"></td>'
        + '<td><input type="text" class="form-control form-control-sm" name="altIds[' + idx + '][value]"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">Remove</button></td>';
      tbody.appendChild(tr);
    });
  }

  // Add child level row
  var addChildBtn = document.getElementById('add-childlevel-row');
  if (addChildBtn) {
    addChildBtn.addEventListener('click', function() {
      var tbody = document.querySelector('#childlevels-table tbody');
      var idx = tbody.querySelectorAll('tr').length;
      var levelOptions = '<option value="">- Select -</option>';
      @foreach($levels as $level)
        levelOptions += '<option value="{{ $level->id }}">{{ addslashes($level->name) }}</option>';
      @endforeach
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><input type="text" class="form-control form-control-sm" name="childLevels[' + idx + '][identifier]"></td>'
        + '<td><select class="form-select form-select-sm" name="childLevels[' + idx + '][levelId]">' + levelOptions + '</select></td>'
        + '<td><input type="text" class="form-control form-control-sm" name="childLevels[' + idx + '][title]"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">Remove</button></td>';
      tbody.appendChild(tr);
    });
  }

  // Add note row
  var addNoteBtn = document.getElementById('add-note-row');
  if (addNoteBtn) {
    addNoteBtn.addEventListener('click', function() {
      var tbody = document.querySelector('#notes-table tbody');
      var idx = tbody.querySelectorAll('tr').length;
      var noteTypeOptions = '<option value="">- Select -</option>';
      @foreach($noteTypes as $nt)
        noteTypeOptions += '<option value="{{ $nt->id }}">{{ addslashes($nt->name) }}</option>';
      @endforeach
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><select class="form-select form-select-sm" name="notes[' + idx + '][typeId]">' + noteTypeOptions + '</select></td>'
        + '<td><textarea class="form-control form-control-sm" name="notes[' + idx + '][content]" rows="2"></textarea></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">Remove</button></td>';
      tbody.appendChild(tr);
    });
  }

  // Add publication note row
  var addPubNoteBtn = document.getElementById('add-pubnote-row');
  if (addPubNoteBtn) {
    addPubNoteBtn.addEventListener('click', function() {
      var list = document.getElementById('pubnotes-list');
      var idx = list.querySelectorAll('.mb-1').length;
      var div = document.createElement('div');
      div.className = 'mb-1';
      div.innerHTML = '<div class="input-group input-group-sm"><textarea class="form-control form-control-sm" name="publicationNotes[' + idx + '][content]" rows="2"></textarea><button type="button" class="btn btn-outline-danger btn-remove-ap">Remove</button></div>';
      list.appendChild(div);
    });
  }

  // Add archivist note row
  var addArchNoteBtn = document.getElementById('add-archnote-row');
  if (addArchNoteBtn) {
    addArchNoteBtn.addEventListener('click', function() {
      var list = document.getElementById('archnotes-list');
      var idx = list.querySelectorAll('.mb-1').length;
      var div = document.createElement('div');
      div.className = 'mb-1';
      div.innerHTML = '<div class="input-group input-group-sm"><textarea class="form-control form-control-sm" name="archivistNotes[' + idx + '][content]" rows="2"></textarea><button type="button" class="btn btn-outline-danger btn-remove-ap">Remove</button></div>';
      list.appendChild(div);
    });
  }

  // Language and script option maps for dynamic row creation
  var languageOptionsMap = {!! json_encode($languageOptions) !!};
  var scriptOptionsMap = {!! json_encode($scriptOptions) !!};

  // Add language/script row (generic) - creates <select> dropdowns
  document.querySelectorAll('.btn-add-lang-row, .btn-add-script-row').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(this.getAttribute('data-target'));
      var name = this.getAttribute('data-name');
      var isScript = btn.classList.contains('btn-add-script-row');
      var optionsMap = isScript ? scriptOptionsMap : languageOptionsMap;
      var placeholder = isScript ? '-- Select script --' : '-- Select language --';
      var div = document.createElement('div');
      div.className = 'input-group input-group-sm mb-1';
      var opts = '<option value="">' + placeholder + '</option>';
      for (var code in optionsMap) {
        opts += '<option value="' + code + '">' + optionsMap[code] + ' (' + code + ')</option>';
      }
      div.innerHTML = '<select class="form-select form-select-sm" name="' + name + '">' + opts + '</select><button type="button" class="btn btn-outline-danger btn-remove-ap">Remove</button>';
      target.appendChild(div);
    });
  });
});
</script>
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash;
    if (hash) {
        var el = document.querySelector(hash);
        if (el && el.classList.contains('accordion-collapse')) {
            var bsCollapse = new bootstrap.Collapse(el, { toggle: false });
            bsCollapse.show();
            setTimeout(function() { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
        }
    }
});
</script>
@endpush
@endsection
