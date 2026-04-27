@extends('theme::layouts.3col')

@section('title', $repository->authorized_form_of_name ?? config('app.ui_label_repository', 'Archival institution'))
@section('body-class', 'view repository')

@section('sidebar')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  {{-- Repository logo --}}
  @include('ahg-repository-manage::_logo', ['resource' => $repository])

  {{-- Holdings search box --}}
  @include('ahg-repository-manage::_holdings', ['resource' => $repository])

  {{-- Holdings list (paginated top-level IOs) --}}
  @include('ahg-repository-manage::_holdings-list', [
    'resource' => $repository,
    'holdings' => $holdings ?? collect(),
    'holdingsPager' => $holdingsPager ?? null,
  ])

  {{-- Upload limit widget --}}
  @include('ahg-repository-manage::_upload-limit', ['resource' => $repository])

  {{-- Maintained actors list --}}
  @if(isset($maintainedActorsList) && $maintainedActorsList)
    @include('ahg-repository-manage::_maintained-actors', ['list' => $maintainedActorsList])
  @endif
@endsection

@section('right')
  <nav>
    {{-- Clipboard --}}
    <h4 class="h5 mb-2">Clipboard</h4>
    <ul class="list-unstyled mb-3">
      <li>
        @include('ahg-core::clipboard._button', ['slug' => $repository->slug, 'type' => 'repository', 'wide' => true])
      </li>
    </ul>

    {{-- Primary contact in right sidebar (matching AtoM) --}}
    @if($contacts->isNotEmpty())
      @php $primaryContact = $contacts->first(); @endphp
      <section id="primary-contact" class="mb-3">
        <h4 class="h5 mb-2">Primary contact</h4>
        <div class="mb-2">
          @if($primaryContact->street_address)<div>{{ $primaryContact->street_address }}</div>@endif
          @if($primaryContact->city || $primaryContact->region || $primaryContact->postal_code)
            <div>{{ $primaryContact->city ?? '' }}{{ $primaryContact->region ? ', ' . $primaryContact->region : '' }} {{ $primaryContact->postal_code ?? '' }}</div>
          @endif
          @if($primaryContact->country_code)<div>{{ $primaryContact->country_code }}</div>@endif
          @if($primaryContact->telephone)<div>{{ $primaryContact->telephone }}</div>@endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
          @if($primaryContact->website)
            <a class="btn atom-btn-white" href="{{ str_starts_with($primaryContact->website, 'http') ? $primaryContact->website : 'http://' . $primaryContact->website }}" target="_blank" rel="noopener">Website</a>
          @endif
          @if($primaryContact->email)
            <a class="btn atom-btn-white" href="mailto:{{ $primaryContact->email }}">Email</a>
          @endif
        </div>
      </section>
    @endif
  </nav>
@endsection

@section('content')

  @include('ahg-ric::_view-switch')

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-repository', ['repository' => $repository])
  @else

  <h1>{{ $repository->authorized_form_of_name }}</h1>

  {{-- Breadcrumb (matching AtoM) --}}
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('repository.browse') }}">{{ config('app.ui_label_repository', 'Archival institution') }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $repository->authorized_form_of_name }}</li>
    </ol>
  </nav>

  {{-- Banner image (matching AtoM) --}}
  @php
    $bannerRelPath = '/uploads/r/' . ($repository->slug ?? '') . '/conf/banner.png';
    $bannerAbsPath = '/usr/share/nginx/archive' . $bannerRelPath;
    $hasBanner = $repository->slug && is_file($bannerAbsPath);
  @endphp
  @if($hasBanner)
    <div class="row mb-3" id="repository-banner">
      <div class="col-md-9">
        <img src="{{ $bannerRelPath }}" alt="" class="img-fluid rounded">
      </div>
    </div>
  @endif

  {{-- HTML snippet (matching AtoM) --}}
  @if(!empty($htmlSnippet))
    <div class="row" id="repository-html-snippet">
      <div class="col-md-9">
        {!! $htmlSnippet !!}
      </div>
    </div>
  @endif

  @if(!empty($translations))
    @include('ahg-core::_translation-links')
  @endif

  {{-- Google Maps embed (matching AtoM) --}}
  @php
    $mapApiKey = \AhgCore\Services\SettingHelper::get('google_maps_api_key');
    $primaryContactForMap = $contacts->first();
    $mapLatitude = $primaryContactForMap->latitude ?? null;
    $mapLongitude = $primaryContactForMap->longitude ?? null;
  @endphp
  @if($mapLatitude && $mapLongitude && $mapApiKey)
    <div class="p-1 border-bottom">
      <div id="front-map" class="simple-map" data-key="{{ $mapApiKey }}" data-latitude="{{ $mapLatitude }}" data-longitude="{{ $mapLongitude }}" style="height:300px;width:100%"></div>
    </div>
    @push('scripts')
    <script>
      (function() {
        var mapEl = document.getElementById('front-map');
        if (!mapEl) return;
        var key = mapEl.dataset.key, lat = parseFloat(mapEl.dataset.latitude), lng = parseFloat(mapEl.dataset.longitude);
        if (!key || isNaN(lat) || isNaN(lng)) return;
        window.initRepositoryMap = function() {
          var pos = {lat: lat, lng: lng};
          var map = new google.maps.Map(mapEl, {zoom: 14, center: pos});
          new google.maps.Marker({position: pos, map: map});
        };
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + key + '&callback=initRepositoryMap';
        s.async = true; s.defer = true;
        document.head.appendChild(s);
      })();
    </script>
    @endpush
  @endif

  @php
    $editUrl = auth()->check() ? route('repository.edit', $repository->slug) : null;
  @endphp

  {{-- Identity area (ISDIAH 5.1) --}}
  <section id="identifyArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#identity-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Identity area">Identity area</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Identity area</div>
      @endif
    </h2>

    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Identifier</h3><div class="col-9 p-2">{{ $repository->identifier ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Authorized form of name</h3><div class="col-9 p-2">{{ $repository->authorized_form_of_name ?? '' }}</div></div>

    @if($otherNames->isNotEmpty())
      @php $parallelNames = $otherNames->where('type_id', \AhgCore\Constants\TermId::OTHER_NAME_PARALLEL); @endphp
      @if($parallelNames->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Parallel form(s) of name</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($parallelNames as $name)
                <li>{{ $name->name }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @php $otherFormNames = $otherNames->where('type_id', \AhgCore\Constants\TermId::OTHER_NAME_OTHER_FORM); @endphp
      @if($otherFormNames->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Other form(s) of name</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($otherFormNames as $name)
                <li>{{ $name->name }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif
    @endif

    @if($repositoryTypes->isNotEmpty())
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Type</h3>
        <div class="col-9 p-2">
          @foreach($repositoryTypes as $type)
            <span class="badge bg-light text-dark me-1">{{ $type->name }}</span>
          @endforeach
        </div>
      </div>
    @endif
  </section>

  {{-- Contact area (ISDIAH 5.2) --}}
  <section id="contactArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#contact-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Contact area">Contact area</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Contact area</div>
      @endif
    </h2>

    @foreach($contacts as $contact)
      <section class="contact-info">
        @if($contact->contact_person)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">&nbsp;</h3>
            <div class="col-9 p-2">
              <span class="text-primary">{{ $contact->contact_person }}</span>
              @if($contact->primary_contact)
                <span class="badge bg-secondary ms-1">Primary contact</span>
              @endif
            </div>
          </div>
        @endif

        @if($contact->contact_type ?? null)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Type</h3>
            <div class="col-9 p-2">{{ $contact->contact_type }}</div>
          </div>
        @endif

        @if($contact->street_address || ($contact->city ?? null) || ($contact->region ?? null) || $contact->country_code || $contact->postal_code)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Address</h3>
            <div class="col-9 p-2">
              @if($contact->street_address)
                <div class="field row g-0">
                  <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Street address</h4>
                  <div class="col-9 p-1">{{ $contact->street_address }}</div>
                </div>
              @endif
              @if($contact->city ?? null)
                <div class="field row g-0">
                  <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Locality</h4>
                  <div class="col-9 p-1">{{ $contact->city }}</div>
                </div>
              @endif
              @if($contact->region ?? null)
                <div class="field row g-0">
                  <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Region</h4>
                  <div class="col-9 p-1">{{ $contact->region }}</div>
                </div>
              @endif
              @if($contact->country_code)
                <div class="field row g-0">
                  <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Country name</h4>
                  <div class="col-9 p-1">{{ $contact->country_code }}</div>
                </div>
              @endif
              @if($contact->postal_code)
                <div class="field row g-0">
                  <h4 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-1 ps-0" style="font-size:.85rem;">Postal code</h4>
                  <div class="col-9 p-1">{{ $contact->postal_code }}</div>
                </div>
              @endif
            </div>
          </div>
        @endif

        @if($contact->telephone)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Telephone</h3>
            <div class="col-9 p-2">{{ $contact->telephone }}</div>
          </div>
        @endif

        @if($contact->fax)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Fax</h3>
            <div class="col-9 p-2">{{ $contact->fax }}</div>
          </div>
        @endif

        @if($contact->email)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Email</h3>
            <div class="col-9 p-2">{{ $contact->email }}</div>
          </div>
        @endif

        @if($contact->website)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">URL</h3>
            <div class="col-9 p-2"><a href="{{ $contact->website }}" target="_blank" rel="noopener">{{ $contact->website }}</a></div>
          </div>
        @endif

        @if($contact->note ?? null)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Note</h3>
            <div class="col-9 p-2">{{ $contact->note }}</div>
          </div>
        @endif
      </section>
    @endforeach
  </section>

  {{-- Description area (ISDIAH 5.3) --}}
  <section id="descriptionArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#description-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Description area">Description area</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Description area</div>
      @endif
    </h2>

    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">History</h3><div class="col-9 p-2">{!! ($repository->history ?? '') ? nl2br(e($repository->history)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Geographical and cultural context</h3><div class="col-9 p-2">{!! ($repository->geocultural_context ?? '') ? nl2br(e($repository->geocultural_context)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Mandates/Sources of authority</h3><div class="col-9 p-2">{!! ($repository->mandates ?? '') ? nl2br(e($repository->mandates)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Administrative structure</h3><div class="col-9 p-2">{!! ($repository->internal_structures ?? '') ? nl2br(e($repository->internal_structures)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Records management and collecting policies</h3><div class="col-9 p-2">{!! ($repository->collecting_policies ?? '') ? nl2br(e($repository->collecting_policies)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Buildings</h3><div class="col-9 p-2">{!! ($repository->buildings ?? '') ? nl2br(e($repository->buildings)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Holdings</h3><div class="col-9 p-2">{!! ($repository->holdings ?? '') ? nl2br(e($repository->holdings)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Finding aids, guides and publications</h3><div class="col-9 p-2">{!! ($repository->finding_aids ?? '') ? nl2br(e($repository->finding_aids)) : '' !!}</div></div>
  </section>

  {{-- Access area (ISDIAH 5.4) --}}
  <section id="accessArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#access-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Access area">Access area</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Access area</div>
      @endif
    </h2>

    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Opening times</h3><div class="col-9 p-2">{!! ($repository->opening_times ?? '') ? nl2br(e($repository->opening_times)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Access conditions and requirements</h3><div class="col-9 p-2">{!! ($repository->access_conditions ?? '') ? nl2br(e($repository->access_conditions)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Accessibility</h3><div class="col-9 p-2">{!! ($repository->disabled_access ?? '') ? nl2br(e($repository->disabled_access)) : '' !!}</div></div>
  </section>

  {{-- Services area (ISDIAH 5.5) --}}
  <section id="servicesArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#services-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Services area">Services area</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Services area</div>
      @endif
    </h2>

    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Research services</h3><div class="col-9 p-2">{!! ($repository->research_services ?? '') ? nl2br(e($repository->research_services)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Reproduction services</h3><div class="col-9 p-2">{!! ($repository->reproduction_services ?? '') ? nl2br(e($repository->reproduction_services)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Public areas</h3><div class="col-9 p-2">{!! ($repository->public_facilities ?? '') ? nl2br(e($repository->public_facilities)) : '' !!}</div></div>
  </section>

  {{-- Control area (ISDIAH 5.6) --}}
  <section id="controlArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#control-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Control area">Control area</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Control area</div>
      @endif
    </h2>

    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description identifier</h3><div class="col-9 p-2">{{ $repository->desc_identifier ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Institution identifier</h3><div class="col-9 p-2">{{ $repository->desc_institution_identifier ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rules and/or conventions used</h3><div class="col-9 p-2">{!! ($repository->desc_rules ?? '') ? nl2br(e($repository->desc_rules)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Status</h3><div class="col-9 p-2">{{ $descStatusName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of detail</h3><div class="col-9 p-2">{{ $descDetailName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of creation, revision and deletion</h3><div class="col-9 p-2">{!! ($repository->desc_revision_history ?? '') ? nl2br(e($repository->desc_revision_history)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language(s)</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach($languages ?? [] as $lang)<li>{{ $lang }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script(s)</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach($scripts ?? [] as $scr)<li>{{ $scr }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sources</h3><div class="col-9 p-2">{!! ($repository->desc_sources ?? '') ? nl2br(e($repository->desc_sources)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Maintenance notes</h3><div class="col-9 p-2">{!! ($maintenanceNotes ?? '') ? nl2br(e($maintenanceNotes)) : '' !!}</div></div>
    @if(isset($sourceLangName) && $sourceLangName)
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source language</h3><div class="col-9 p-2">{{ $sourceLangName }}</div></div>
    @endif
  </section>

  {{-- Access points --}}
  <section id="accessPointsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      @if($editUrl)
        <a href="{{ $editUrl }}#points-collapse" class="d-flex p-3 border-bottom text-primary text-decoration-none" title="Edit Access points">Access points</a>
      @else
        <div class="d-flex p-3 border-bottom text-primary">Access points</div>
      @endif
    </h2>

    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Access Points</h3>
      <div class="col-9 p-2">
        <ul class="m-0 ms-1 ps-3">
          @foreach($thematicAreas ?? [] as $area)
            <li>{{ $area->name }} (Thematic area)</li>
          @endforeach
          @foreach($geographicSubregions ?? [] as $region)
            <li>{{ $region->name }} (Geographic subregion)</li>
          @endforeach
        </ul>
      </div>
    </div>
  </section>

  @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $repository, 'recordType' => 'repository'])
  @endif
  @endif {{-- end heratio/ric view mode --}}

  {{-- Action buttons (bottom bar, matching AtoM) — shown in both views --}}
  @auth
  @php $isAdmin = \AhgCore\Services\AclService::canAdmin(auth()->id()); @endphp
  <section class="actions">
    <ul class="nav gap-2">
      <li><a class="btn atom-btn-outline-light" href="{{ route('repository.edit', $repository->slug) }}">Edit</a></li>
      @if($isAdmin)
      <li><a class="btn atom-btn-outline-danger" href="{{ route('repository.confirmDelete', $repository->slug) }}">Delete</a></li>
      @endif
      <li><a class="btn atom-btn-outline-light" href="{{ route('repository.create') }}">Add new</a></li>
      <li><a class="btn atom-btn-outline-light" href="{{ route('informationobject.create', ['repository' => $repository->id]) }}">Add description</a></li>
      <li><a class="btn atom-btn-outline-light" href="{{ route('repository.edit', $repository->slug) }}?theme=1">Edit theme</a></li>
    </ul>
  </section>
  @endauth

  {{-- RiC Context Sidebar --}}
  @include('ahg-ric::_context-sidebar', ['resourceId' => $repository->id])

  {{-- RiC Explorer Panel --}}
  @include('ahg-ric::_ric-panel', ['resourceId' => $repository->id])
@endsection
