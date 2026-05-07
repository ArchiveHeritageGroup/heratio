  {{-- ===== 5. Allied materials area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_allied_materials_area'))
  <section id="alliedMaterialsArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#allied-collapse">
        {{ __('Allied materials area') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#allied-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Allied materials area') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="allied-collapse">

      @if($io->location_of_originals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Existence and location of originals') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_originals)) !!}</div>
        </div>
      @endif

      @if($io->location_of_copies)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Existence and location of copies') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_copies)) !!}</div>
        </div>
      @endif

      @if($io->related_units_of_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related units of description') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->related_units_of_description)) !!}</div>
        </div>
      @endif

      {{-- Related material descriptions (relation type_id = 176) --}}
      @if(isset($relatedMaterialDescriptions) && $relatedMaterialDescriptions->isNotEmpty())
        <div class="relatedMaterialDescriptions">
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related descriptions') }}</h3>
            <div class="col-9 p-2">
              <ul class="m-0 ms-1 ps-3">
                @foreach($relatedMaterialDescriptions as $relatedDesc)
                  <li>
                    <a href="{{ route('informationobject.show', $relatedDesc->slug) }}">
                      {{ $relatedDesc->title ?: '[Untitled]' }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>
      @endif

      {{-- Publication notes (type_id = 120) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 120) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Publication note') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>
  @endif {{-- end isad_allied_materials_area visibility --}}
