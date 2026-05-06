  {{-- ===== 4. Conditions of access and use area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_conditions_of_access_use_area'))
  <section id="conditionsOfAccessAndUseArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#conditions-collapse">
        {{ __('Conditions of access and use area') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#conditions-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Conditions of access and use area') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="conditions-collapse">

      @if($io->access_conditions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Conditions governing access') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->access_conditions)) !!}</div>
        </div>
      @endif

      @if($io->reproduction_conditions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Conditions governing reproduction') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
        </div>
      @endif

      @if(isset($languages) && $languages->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language of material') }}</h3>
          <div class="col-9 p-2">
            @foreach($languages as $lang)
              {{ $lang->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if(isset($scriptsOfMaterial) && $scriptsOfMaterial->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Script of material') }}</h3>
          <div class="col-9 p-2">
            @foreach($scriptsOfMaterial as $script)
              {{ $script->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @elseif(isset($materialScripts) && (is_countable($materialScripts) ? count($materialScripts) > 0 : !empty($materialScripts)))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Script of material') }}</h3>
          <div class="col-9 p-2">
            @foreach($materialScripts as $script)
              {{ $script }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Language and script notes (note type_id 174) --}}
      @foreach($notes->where('type_id', 174) as $lnote)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language and script notes') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($lnote->content)) !!}</div>
        </div>
      @endforeach

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_physical_condition'))
      @if($io->physical_characteristics)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Physical characteristics and technical requirements') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
        </div>
      @endif
      @endif

      @if($io->finding_aids)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Finding aids') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->finding_aids)) !!}</div>
        </div>
      @endif

      {{-- Finding aid link (generated or uploaded PDF) --}}
      @if(isset($findingAid) && $findingAid)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $findingAid->label }}</h3>
          <div class="findingAidLink col-9 p-2">
            <a href="{{ route('informationobject.findingaid.download', $findingAid->slug) }}" target="_blank">
              <i class="fas fa-file-pdf me-1"></i>{{ $findingAid->slug }}.pdf
            </a>
          </div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_conditions_of_access_use_area visibility --}}
