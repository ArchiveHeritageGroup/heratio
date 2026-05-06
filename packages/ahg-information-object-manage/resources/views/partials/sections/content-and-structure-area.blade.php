  {{-- ===== 3. Content and structure area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_content_and_structure_area'))
  <section id="contentAndStructureArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#content-collapse">
        {{ __('Content and structure area') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#content-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Content and structure area') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="content-collapse">

      @if($io->scope_and_content)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Scope and content') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $io->scope_and_content))) !!}@include('ahg-translation::components.badge', ['source' => $translationSources['scope_and_content'] ?? null])</div>
        </div>
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_appraisal_destruction'))
      @if($io->appraisal)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Appraisal, destruction and scheduling') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->appraisal)) !!}</div>
        </div>
      @endif
      @endif

      @if($io->accruals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Accruals') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->accruals)) !!}</div>
        </div>
      @endif

      @if($io->arrangement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('System of arrangement') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->arrangement)) !!}</div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_content_and_structure_area visibility --}}
