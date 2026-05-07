  {{-- ===== 2. Context area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_context_area'))
  <section id="contextArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#context-collapse">
        {{ __('Context area') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#context-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Context area') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="context-collapse">

      {{-- Creator details --}}
      @if(isset($creators) && $creators->isNotEmpty())
        <div class="creatorHistories">
          @foreach($creators as $creator)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Name of creator(s)') }}</h3>
              <div class="col-9 p-2">
                <a href="{{ route('actor.show', $creator->slug) }}">{{ $creator->name }}</a>
              </div>
            </div>

            @if($creator->dates_of_existence)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dates of existence') }}</h3>
                <div class="col-9 p-2">{{ $creator->dates_of_existence }}</div>
              </div>
            @endif

            @if($creator->history)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">
                  @if(isset($creator->entity_type_id) && $creator->entity_type_id == 131)
                    {{ __('Administrative history') }}
                  @else
                    {{ __('Biographical history') }}
                  @endif
                </h3>
                <div class="col-9 p-2">{!! nl2br(e($creator->history)) !!}</div>
              </div>
            @endif
          @endforeach
        </div>
      @endif

      {{-- Related function --}}
      @if(isset($functionRelations) && (is_countable($functionRelations) ? count($functionRelations) > 0 : !empty($functionRelations)))
        <div class="relatedFunctions">
          @foreach($functionRelations as $item)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related function') }}</h3>
              <div class="col-9 p-2">
                @if(isset($item->slug))
                  <a href="{{ route('function.show', $item->slug) }}">{{ $item->name ?? $item->title ?? '[Untitled]' }}</a>
                @else
                  {{ $item->name ?? $item->title ?? '[Untitled]' }}
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif

      {{-- Repository --}}
      @if($repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Repository') }}</h3>
          <div class="col-9 p-2">
            <a href="{{ route('repository.show', $repository->slug) }}">{{ $repository->name }}</a>
          </div>
        </div>
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_archival_history'))
      @if($io->archival_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Archival history') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $io->archival_history))) !!}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_immediate_source'))
      @if($io->acquisition)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Immediate source of acquisition or transfer') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->acquisition)) !!}</div>
        </div>
      @endif
      @endif

    </div>
  </section>
  @endif {{-- end isad_context_area visibility --}}
