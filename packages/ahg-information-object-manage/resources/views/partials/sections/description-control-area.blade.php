  {{-- ===== 8. Description control area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_description_control_area'))
  <section id="descriptionControlArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#description-collapse">
        {{ __('Description control area') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#description-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Description control area') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="description-collapse">

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_description_identifier'))
      @if($io->description_identifier ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Description identifier') }}</h3>
          <div class="col-9 p-2">{{ $io->description_identifier }}</div>
        </div>
      @endif
      @endif {{-- end isad_control_description_identifier --}}

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_institution_identifier'))
      @if($io->institution_responsible_identifier ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Institution identifier') }}</h3>
          <div class="col-9 p-2">{{ $io->institution_responsible_identifier }}</div>
        </div>
      @endif
      @endif {{-- end isad_control_institution_identifier --}}

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_rules_conventions'))
      @if($io->rules ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rules and/or conventions used') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->rules)) !!}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_status'))
      @if(isset($descriptionStatusName) && $descriptionStatusName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Status') }}</h3>
          <div class="col-9 p-2">{{ $descriptionStatusName }}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_level_of_detail'))
      @if(isset($descriptionDetailName) && $descriptionDetailName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Level of detail') }}</h3>
          <div class="col-9 p-2">{{ $descriptionDetailName }}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_dates'))
      @if($io->revision_history ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dates of creation revision deletion') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->revision_history)) !!}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_languages'))
      @if(isset($languagesOfDescription) && (is_countable($languagesOfDescription) ? count($languagesOfDescription) > 0 : !empty($languagesOfDescription)))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language(s)') }}</h3>
          <div class="col-9 p-2">
            @foreach($languagesOfDescription as $lang)
              {{ $lang }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_scripts'))
      @if(isset($scriptsOfDescription) && (is_countable($scriptsOfDescription) ? count($scriptsOfDescription) > 0 : !empty($scriptsOfDescription)))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Script(s)') }}</h3>
          <div class="col-9 p-2">
            @foreach($scriptsOfDescription as $script)
              {{ $script }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_sources'))
      @if($io->sources ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Sources') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->sources)) !!}</div>
        </div>
      @endif
      @endif

      {{-- Archivist's note (type_id = 124) --}}
      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_archivists_notes'))
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 124) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __("Archivist's note") }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif
      @endif

      @if($io->source_standard ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source standard') }}</h3>
          <div class="col-9 p-2">{{ $io->source_standard }}</div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_description_control_area visibility --}}
