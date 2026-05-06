  {{-- ===== 7. Access points ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_access_points_area'))
  <section id="accessPointsArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#access-collapse">
        {{ __('Access points') }}
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#access-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Access points') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="access-collapse">

      @if(isset($subjects) && $subjects->isNotEmpty())
        <div class="field text-break row g-0 subjectAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject access points') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($subjects as $subject)
                <li>
                  @if(isset($subject->slug))
                    <a href="{{ route('informationobject.browse', ['subject' => $subject->name]) }}">{{ $subject->name }}</a>
                  @else
                    {{ $subject->name }}
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($places) && $places->isNotEmpty())
        <div class="field text-break row g-0 placeAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place access points') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($places as $place)
                <li>
                  @if(isset($place->slug))
                    <a href="{{ route('informationobject.browse', ['place' => $place->name]) }}">{{ $place->name }}</a>
                  @else
                    {{ $place->name }}
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
        <div class="field text-break row g-0 nameAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Name access points') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($nameAccessPoints as $nap)
                <li>
                  @if(isset($nap->slug))
                    <a href="{{ route('actor.show', $nap->slug) }}">{{ $nap->name }}</a>
                  @else
                    {{ $nap->name }}
                  @endif
                  @if(isset($nap->event_type))
                    <span class="text-muted">({{ $nap->event_type }})</span>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($genres) && $genres->isNotEmpty())
        <div class="field text-break row g-0 genreAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Genre access points') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($genres as $genre)
                <li>
                  @if(isset($genre->slug))
                    <a href="{{ route('informationobject.browse', ['genre' => $genre->name]) }}">{{ $genre->name }}</a>
                  @else
                    {{ $genre->name }}
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_access_points_area visibility --}}
