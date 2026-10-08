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

      {{-- #1538: Wikidata, VIAF and other authority links of the record's
           creators and name access points, as recorded on their authority
           records. Hidden when none has a link; never breaks the page. --}}
      @php
        $__authLinks = collect();
        try {
          if (\Illuminate\Support\Facades\Schema::hasTable('ahg_actor_identifier')) {
            $__actorIds = \Illuminate\Support\Facades\DB::table('event')->where('object_id', $io->id)->where('type_id', 111)->whereNotNull('actor_id')->pluck('actor_id')
              ->merge(\Illuminate\Support\Facades\DB::table('relation')->where('subject_id', $io->id)->where('type_id', 161)->pluck('object_id'))
              ->unique()->values();
            if ($__actorIds->isNotEmpty()) {
              $__culture = app()->getLocale();
              $__authLinks = \Illuminate\Support\Facades\DB::table('ahg_actor_identifier as ai')
                ->leftJoin('actor_i18n as an', function ($j) use ($__culture) { $j->on('an.id', '=', 'ai.actor_id')->where('an.culture', '=', $__culture); })
                ->whereIn('ai.actor_id', $__actorIds)
                ->orderBy('an.authorized_form_of_name')->orderBy('ai.identifier_type')
                ->get(['ai.actor_id', 'an.authorized_form_of_name as name', 'ai.identifier_type', 'ai.identifier_value', 'ai.uri', 'ai.is_verified'])
                ->groupBy('actor_id');
            }
          }
        } catch (\Throwable $e) { $__authLinks = collect(); }
      @endphp
      @if($__authLinks->isNotEmpty())
        <div class="field text-break row g-0 externalAuthorityLinks">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('External authority links') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($__authLinks as $__links)
                <li>
                  {{ $__links->first()->name ?? __('Unnamed authority') }}:
                  @foreach($__links as $__l)
                    @if($__l->uri)
                      <a href="{{ $__l->uri }}" target="_blank" rel="noopener" title="{{ ucfirst($__l->identifier_type) }}">{{ ucfirst($__l->identifier_type) }} {{ $__l->identifier_value }}</a>@else{{ ucfirst($__l->identifier_type) }} {{ $__l->identifier_value }}@endif
                    @if($__l->is_verified)<i class="fas fa-check text-success ms-1" title="{{ __('Verified') }}" aria-label="{{ __('Verified') }}"></i>@endif{{ $loop->last ? '' : ',' }}
                  @endforeach
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_access_points_area visibility --}}
