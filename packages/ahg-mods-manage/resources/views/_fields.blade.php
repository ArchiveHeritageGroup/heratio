{{-- MODS field set - #1425 dynamic-standard form. Accordion only; nullable $io. --}}
@php
  $io = $io ?? (object) [];
  $repositories = $repositories ?? collect();
  $eventTypes = $eventTypes ?? collect();
  $modsTypeOptions = $modsTypeOptions ?? collect();
  $events = $events ?? collect();
  $creationEvents = $creationEvents ?? collect();
  $publicationEvents = $publicationEvents ?? collect();
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $nameAccessPoints = $nameAccessPoints ?? collect();
  $modsTypes = $modsTypes ?? collect();
  $materialLanguages = $materialLanguages ?? collect();
  $placeOfPublicationId = $placeOfPublicationId ?? null;
  $publisherActorId = $publisherActorId ?? null;
  $placeOfPublicationName = $placeOfPublicationName ?? null;
  $publisherFreeText = $publisherFreeText ?? null;
  $publisherActorName = $publisherActorName ?? null;
  $publicationStatusId = $publicationStatusId ?? null;
  $modsNote = $modsNote ?? '';
  $selectedModsTypeIds = $modsTypes->pluck('term_id')->all();
  $firstCreation = $creationEvents->first();
  $firstPublication = $publicationEvents->first();
  $creationDateDisplay = $firstCreation->date_display ?? '';
  $creationStartDate = $firstCreation->start_date ?? '';
  $publicationDateDisplay = $firstPublication->date_display ?? '';
  $publicationStartDate = $firstPublication->start_date ?? '';
  $existingSubjects = $subjects->map(fn($t) => ['id' => $t->term_id, 'name' => $t->name])->all();
  $existingPlaces = $places->map(fn($t) => ['id' => $t->term_id, 'name' => $t->name])->all();
  $existingNames = $nameAccessPoints->map(fn($n) => ['id' => $n->actor_id ?? null, 'name' => $n->name])->all();
@endphp

<input type="hidden" name="_display_standard_code" value="mods">

  <div class="accordion mb-3" id="mods-accordion">

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#mods-core">{{ __('MODS elements') }}</button>
      </h2>
      <div id="mods-core" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">identifier</label>
            <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">titleInfo <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">abstract (scope and content)</label>
            <textarea name="scope_and_content" class="form-control" rows="4">{{ old('scope_and_content', $io->scope_and_content ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">accessCondition</label>
            <textarea name="access_conditions" class="form-control" rows="2">{{ old('access_conditions', $io->access_conditions ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mods-type">typeOfResource</button>
      </h2>
      <div id="mods-type" class="accordion-collapse collapse">
        <div class="accordion-body">
          <select name="modsTypeIds[]" class="form-select" multiple size="8">
            @foreach($modsTypeOptions as $opt)
              <option value="{{ $opt->id }}" @if(in_array($opt->id, $selectedModsTypeIds)) selected @endif>{{ $opt->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    {{-- #662 Phase 2: originInfo block — publisher + dateIssued +
         dateCreated + placeOfPublication. The publisher accepts either an
         existing actor (autocomplete) OR free text (recorded as the
         mods:publisher serialized property). --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mods-origin">originInfo</button>
      </h2>
      <div id="mods-origin" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">{{ __('dateCreated (display)') }}</label>
                <input type="text" name="creation_date" class="form-control" value="{{ old('creation_date', $creationDateDisplay) }}" placeholder="{{ __('e.g. circa 1920s') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">{{ __('dateCreated (ISO 8601)') }}</label>
                <input type="text" name="creation_start_date" class="form-control" value="{{ old('creation_start_date', $creationStartDate) }}" placeholder="{{ __('YYYY-MM-DD') }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">{{ __('dateIssued (display)') }}</label>
                <input type="text" name="publication_date" class="form-control" value="{{ old('publication_date', $publicationDateDisplay) }}" placeholder="{{ __('e.g. 2026 spring') }}">
              </div>
              <div class="mb-3">
                <label class="form-label">{{ __('dateIssued (ISO 8601)') }}</label>
                <input type="text" name="publication_start_date" class="form-control" value="{{ old('publication_start_date', $publicationStartDate) }}" placeholder="{{ __('YYYY-MM-DD') }}">
              </div>
            </div>
          </div>

          <div class="mb-3">
            @include('ahg-core::components.autocomplete', [
                'name'         => 'publisher_id',
                'label'        => __('publisher (actor)'),
                'route'        => 'actor.autocomplete',
                'value'        => old('publisher_id', $publisherActorId),
                'displayValue' => old('publisher_name_input', $publisherActorName),
                'placeholder'  => __('Type to search actors...'),
                'helpText'     => __('Leave blank and use the free-text field below for one-off publishers.'),
            ])
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('publisher (free text)') }}</label>
            <input type="text" name="publisher_name" class="form-control" value="{{ old('publisher_name', $publisherFreeText) }}" placeholder="{{ __('Used only when no actor is selected') }}">
          </div>

          <div class="mb-3">
            @include('ahg-core::components.autocomplete', [
                'name'         => 'place_of_publication_id',
                'label'        => __('placeOfPublication'),
                'route'        => 'term.autocomplete',
                'value'        => old('place_of_publication_id', $placeOfPublicationId),
                'displayValue' => old('place_of_publication_name', $placeOfPublicationName),
                'placeholder'  => __('Type to search places...'),
                'extraParams'  => ['taxonomy_id' => 42],
            ])
          </div>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mods-access">{{ __('Subject / name access points') }}</button>
      </h2>
      <div id="mods-access" class="accordion-collapse collapse">
        <div class="accordion-body">
          {{-- #662 Phase 2: badges replaced with autocomplete-driven
               editable multi-selects. Subjects + Places use term.autocomplete
               with a taxonomy_id filter, Names use actor.autocomplete. --}}
          @include('ahg-core::components.autocomplete', [
              'name'          => 'subjectAccessPoints',
              'multi'         => true,
              'multiName'     => 'subjectAccessPointIds[]',
              'label'         => __('subject (topic)'),
              'route'         => 'term.autocomplete',
              'placeholder'   => __('Type to search subjects...'),
              'existingItems' => $existingSubjects,
              'extraParams'   => ['taxonomy_id' => 35],
          ])
          @include('ahg-core::components.autocomplete', [
              'name'          => 'placeAccessPoints',
              'multi'         => true,
              'multiName'     => 'placeAccessPointIds[]',
              'label'         => __('subject (geographic)'),
              'route'         => 'term.autocomplete',
              'placeholder'   => __('Type to search places...'),
              'existingItems' => $existingPlaces,
              'extraParams'   => ['taxonomy_id' => 42],
          ])
          @include('ahg-core::components.autocomplete', [
              'name'          => 'nameAccessPoints',
              'multi'         => true,
              'multiName'     => 'nameAccessPointIds[]',
              'label'         => __('name'),
              'route'         => 'actor.autocomplete',
              'placeholder'   => __('Type to search actors...'),
              'existingItems' => $existingNames,
          ])
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mods-note">note</button>
      </h2>
      <div id="mods-note" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('General note (mods:note type="general")') }}</label>
            <textarea name="mods_note" class="form-control" rows="4">{{ old('mods_note', $modsNote) }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mods-language">language</button>
      </h2>
      <div id="mods-language" class="accordion-collapse collapse">
        <div class="accordion-body">
          @foreach($materialLanguages as $lang)
            <input type="hidden" name="materialLanguages[]" value="{{ $lang }}">
            <span class="badge bg-secondary me-1">{{ $lang }}</span>
          @endforeach
          @if($materialLanguages->isEmpty())
            <p class="text-muted">None recorded.</p>
          @endif
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mods-admin">{{ __('Administration') }}</button>
      </h2>
      <div id="mods-admin" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Repository') }}</label>
            <select name="repository_id" class="form-select">
              <option value="">—</option>
              @foreach($repositories as $r)
                <option value="{{ $r->id }}" @if(($io->repository_id ?? null) == $r->id) selected @endif>{{ $r->name }}</option>
              @endforeach
            </select>
          </div>
          {{-- Publication status is owned by the host Administration area during a standard swap (#1425); hide it here so it is not duplicated. --}}
          @unless(request()->routeIs('informationobject.standard-fields'))
          <div class="mb-3">
            <label class="form-label">{{ __('Publication status') }}</label>
            <select name="publication_status_id" class="form-select">
              <option value="">—</option>
              <option value="159" @if($publicationStatusId == 159) selected @endif>{{ __('Draft') }}</option>
              <option value="160" @if($publicationStatusId == 160) selected @endif>{{ __('Published') }}</option>
            </select>
          </div>
          @endunless
        </div>
      </div>
    </div>

  </div>
