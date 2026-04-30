@extends('theme::layouts.3col')

@section('title', 'View accession record')
@section('body-class', 'view accession')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">{{ __('View accession record') }}</h1>
    <span class="small" id="heading-label">{{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}</span>
  </div>
@endsection

@section('right')
  {{-- Physical storage --}}
  @if(config('atom.app_element_visibility_physical_storage', true))
    <nav>
      @include('ahg-accession-manage::partials._physical-storage-context-menu', ['resource' => $accession])
    </nav>
  @endif

  {{-- Print + Clipboard --}}
  <div class="d-flex gap-1 mb-3">
    <button class="btn btn-sm atom-btn-white" onclick="window.print()" title="{{ __('Print') }}">
      <i class="fas fa-print me-1"></i>Print
    </button>
    @include('ahg-core::clipboard._button', ['slug' => $accession->slug, 'type' => 'accession'])
  </div>

  {{-- Linked Information Objects --}}
  @if(\Illuminate\Support\Facades\Schema::hasTable('relation'))
    @php
      $linkedIos = \Illuminate\Support\Facades\DB::table('relation')
          ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
          ->join('information_object_i18n', function ($j) { $j->on('information_object.id', '=', 'information_object_i18n.id')->where('information_object_i18n.culture', '=', 'en'); })
          ->join('slug', 'information_object.id', '=', 'slug.object_id')
          ->where('relation.object_id', $accession->id)
          ->where('relation.type_id', 116)
          ->select('information_object_i18n.title', 'slug.slug')
          ->limit(20)
          ->get();
    @endphp
    @if($linkedIos->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
          <i class="fas fa-link me-1"></i> Linked descriptions
        </div>
        <div class="list-group list-group-flush">
          @foreach($linkedIos as $io)
            <a href="{{ url('/' . $io->slug) }}" class="list-group-item list-group-item-action small">{{ $io->title ?: '[Untitled]' }}</a>
          @endforeach
        </div>
      </div>
    @endif
  @endif

  {{-- Donor info --}}
  @if(isset($donor) && $donor)
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-user me-1"></i> Donor
      </div>
      <div class="card-body py-2">
        <strong>{{ $donor->authorized_form_of_name ?? '' }}</strong>
        @if($donor->email ?? null)<br><small><i class="fas fa-envelope me-1"></i>{{ $donor->email }}</small>@endif
      </div>
    </div>
  @endif

@endsection

@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'Spectrum'])

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-accession', ['accession' => $accession])
  @else

  @if(!empty($translations))
    @include('ahg-core::_translation-links')
  @endif

  {{-- ===== Basic info ===== --}}
  <section class="section border-bottom" id="basicInfo">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        Basic info
        @auth
          <a href="{{ route('accession.edit', $accession->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </div>
    </h2>
    <div id="basic-collapse">

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Accession number') }}</h3>
        <div class="col-9 p-2">{{ $accession->identifier }}</div>
      </div>

      {{-- Alternative identifiers --}}
      @if(isset($alternativeIdentifiers) && $alternativeIdentifiers->isNotEmpty())
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Alternative identifier(s)') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($alternativeIdentifiers as $altId)
                <li>{{ $altId->label ?? '' }}: {{ $altId->identifier ?? '' }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Acquisition date') }}</h3>
        <div class="col-9 p-2">{{ $accession->date ? \Carbon\Carbon::parse($accession->date)->format('Y-m-d') : '' }}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Immediate source of acquisition') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->source_of_acquisition ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Location information') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->location_information ?? '')) !!}</div>
      </div>

    </div>
  </section>

  {{-- ===== Donor/Transferring body area ===== --}}
  <section class="section border-bottom" id="donorArea">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        Donor/Transferring body area
        @auth
          <a href="{{ route('accession.edit', $accession->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </div>
    </h2>
    <div id="donor-collapse">

      @if(isset($donors) && count($donors) > 0)
        @foreach($donors as $donorItem)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related donor') }}</h3>
            <div class="col-9 p-2">
              <a href="{{ route('donor.show', $donorItem->slug) }}">{{ $donorItem->name }}</a>
            </div>
          </div>
          @if(isset($donorItem->contacts) && count($donorItem->contacts) > 0)
            @foreach($donorItem->contacts as $contactItem)
              <div class="ms-4 mb-2 ps-3 border-start">
                @if($contactItem->contact_person)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Contact person') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->contact_person }}</div>
                  </div>
                @endif
                @if($contactItem->street_address)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Street address') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->street_address }}</div>
                  </div>
                @endif
                @if($contactItem->city)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('City') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->city }}</div>
                  </div>
                @endif
                @if($contactItem->region)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Region/province') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->region }}</div>
                  </div>
                @endif
                @if($contactItem->postal_code)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Postal code') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->postal_code }}</div>
                  </div>
                @endif
                @if($contactItem->country_code)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Country') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->country_code }}</div>
                  </div>
                @endif
                @if($contactItem->telephone)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Telephone') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->telephone }}</div>
                  </div>
                @endif
                @if($contactItem->fax)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Fax') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->fax }}</div>
                  </div>
                @endif
                @if($contactItem->email)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Email') }}</h3>
                    <div class="col-9 p-2">{{ $contactItem->email }}</div>
                  </div>
                @endif
                @if($contactItem->website)
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Website') }}</h3>
                    <div class="col-9 p-2"><a href="{{ $contactItem->website }}" target="_blank">{{ $contactItem->website }}</a></div>
                  </div>
                @endif
              </div>
            @endforeach
          @endif
        @endforeach
      @elseif(isset($donor) && $donor)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related donor') }}</h3>
          <div class="col-9 p-2">
            <a href="{{ route('donor.show', $donor->slug) }}">{{ $donor->name }}</a>
          </div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== Administrative area ===== --}}
  <section class="section border-bottom" id="administrativeArea">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        Administrative area
        @auth
          <a href="{{ route('accession.edit', $accession->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </div>
    </h2>
    <div id="admin-collapse">

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Acquisition type') }}</h3>
        <div class="col-9 p-2">{{ ($accession->acquisition_type_id && isset($termNames[$accession->acquisition_type_id])) ? $termNames[$accession->acquisition_type_id] : '' }}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Resource type') }}</h3>
        <div class="col-9 p-2">{{ ($accession->resource_type_id && isset($termNames[$accession->resource_type_id])) ? $termNames[$accession->resource_type_id] : '' }}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Title') }}</h3>
        <div class="col-9 p-2">{{ $accession->title ?? '' }}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creators') }}</h3>
        <div class="col-9 p-2">
          @if(isset($creators) && count($creators) > 0)
            <ul class="m-0 ms-1 ps-3">
              @foreach($creators as $creator)
                <li><a href="{{ route('actor.show', $creator->slug) }}">{{ $creator->name ?? '[Untitled]' }}</a></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date(s)') }}</h3>
        <div class="col-9 p-2">
          @if(isset($dates) && count($dates) > 0)
            <ul class="m-0 ms-1 ps-3">
              @foreach($dates as $dateItem)
                <li>{{ $dateItem->date_display ?? '' }} ({{ $dateItem->type_name ?? '' }})</li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Event(s)') }}</h3>
        <div class="col-9 p-2">
          @if(isset($accessionEvents) && count($accessionEvents) > 0)
            <ul class="m-0 ms-1 ps-3">
              @foreach($accessionEvents as $event)
                <li>
                  {{ $event->date ?? '' }} ({{ $event->type_name ?? '' }}): {{ $event->agent ?? '' }}
                  @if($event->note ?? null)
                    <p class="mb-0 mt-1">{{ $event->note }}</p>
                  @endif
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Archival/Custodial history') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->archival_history ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Scope and content') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->scope_and_content ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Appraisal, destruction and scheduling') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->appraisal ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Physical condition') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->physical_characteristics ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Received extent units') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->received_extent_units ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Processing status') }}</h3>
        <div class="col-9 p-2">{{ ($accession->processing_status_id && isset($termNames[$accession->processing_status_id])) ? $termNames[$accession->processing_status_id] : '' }}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Processing priority') }}</h3>
        <div class="col-9 p-2">{{ ($accession->processing_priority_id && isset($termNames[$accession->processing_priority_id])) ? $termNames[$accession->processing_priority_id] : '' }}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Processing notes') }}</h3>
        <div class="col-9 p-2">{!! nl2br(e($accession->processing_notes ?? '')) !!}</div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Accruals') }}</h3>
        <div class="col-9 p-2">
          @if(isset($accruals) && count($accruals) > 0)
            <ul class="m-0 ms-1 ps-3">
              @foreach($accruals as $accrual)
                <li><a href="{{ route('accession.show', $accrual->slug) }}">{{ $accrual->title ?: $accrual->identifier ?: '[Untitled]' }}</a></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Accrual to') }}</h3>
        <div class="col-9 p-2">
          @if(isset($accrualTo) && count($accrualTo) > 0)
            <ul class="m-0 ms-1 ps-3">
              @foreach($accrualTo as $accrualItem)
                <li><a href="{{ route('accession.show', $accrualItem->slug) }}">{{ $accrualItem->title ?: $accrualItem->identifier ?: '[Untitled]' }}</a></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>

      @if($accession->created_at ?? null)
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date created') }}</h3>
        <div class="col-9 p-2">{{ \Carbon\Carbon::parse($accession->created_at)->format('j F Y') }}</div>
      </div>
      @endif

      @if($accession->updated_at ?? null)
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date modified') }}</h3>
        <div class="col-9 p-2">{{ \Carbon\Carbon::parse($accession->updated_at)->format('j F Y') }}</div>
      </div>
      @endif

      @if(isset($sourceLangName) && $sourceLangName)
      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source language') }}</h3>
        <div class="col-9 p-2">{{ $sourceLangName }}</div>
      </div>
      @endif

    </div>
  </section>

  {{-- ===== Rights area ===== --}}
  <section class="section border-bottom" id="rightsArea">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        Rights area
      </div>
    </h2>
    <div>
      @if(isset($rights) && count($rights) > 0)
        @foreach($rights as $right)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights') }}</h3>
            <div class="col-9 p-2">
              @if($right->basis_name ?? null) <div>Basis: {{ $right->basis_name }}</div> @endif
              @if($right->start_date || $right->end_date) <div>{{ $right->start_date ?? '?' }} - {{ $right->end_date ?? '?' }}</div> @endif
              @if($right->rights_note ?? null) <div>{!! nl2br(e($right->rights_note)) !!}</div> @endif
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </section>

  {{-- ===== Information object area ===== --}}
  <section class="section border-bottom" id="informationObjectArea">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        {{ config('app.ui_label_informationobject', 'Archival description') }} area
        @auth
          <a href="{{ route('accession.edit', $accession->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </div>
    </h2>
    <div id="io-collapse">
      @if(isset($informationObjects) && count($informationObjects) > 0)
        @foreach($informationObjects as $io)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ config('app.ui_label_informationobject', 'Archival description') }}</h3>
            <div class="col-9 p-2">
              <a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?: '[Untitled]' }}</a>
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </section>

  {{-- ===== Deaccession area ===== --}}
  <section class="section border-bottom" id="deaccessionArea">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        Deaccession area
      </div>
    </h2>
    <div>
      @if(isset($deaccessions) && $deaccessions->isNotEmpty())
        @foreach($deaccessions as $deaccession)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Deaccession') }}</h3>
            <div class="col-9 p-2">
              <a href="{{ url('/deaccession/' . $deaccession->slug) }}">{{ $deaccession->identifier ?: $deaccession->description ?: '[Untitled]' }}</a>
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </section>

  @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $accession, 'recordType' => 'accession'])
  @endif
  @endif {{-- end heratio/ric view mode --}}

  {{-- RiC Context Sidebar --}}
  @include('ahg-ric::_context-sidebar', ['resourceId' => $accession->id])

  {{-- RiC Explorer Panel --}}
  @include('ahg-ric::_ric-panel', ['resourceId' => $accession->id])

@endsection

@section('after-content')
  @include('ahg-core::partials._ner-modal', ['objectId' => $accession->id, 'objectTitle' => $accession->title])
  @auth
    @php $isAdmin = auth()->user()->is_admin; @endphp
    <ul class="actions mb-3 nav gap-2">
      {{-- Edit: any authenticated user --}}
      <li><a href="{{ route('accession.edit', $accession->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>

      {{-- Delete: admin only --}}
      @if($isAdmin)
      <li><a href="{{ route('accession.confirmDelete', $accession->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      @endif

      {{-- Deaccession: admin only --}}
      @if($isAdmin)
      <li><a href="{{ url('/deaccession/add?accession=' . $accession->id) }}" class="btn atom-btn-outline-light">Deaccession</a></li>
      @endif

      @if(!isset($accrualTo) || count($accrualTo) === 0)
        <li><a href="{{ route('accession.create', ['accession' => $accession->slug]) }}" class="btn atom-btn-outline-light">Add accrual</a></li>
      @endif

      <li>
        <div class="dropup">
          <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            {{ __('More') }}
          </button>
          <ul class="dropdown-menu mb-2">
            <li><a href="{{ route('informationobject.create', ['accession' => $accession->id]) }}" class="dropdown-item">Create {{ config('atom.ui_label_informationobject', 'archival description') }}</a></li>
            <li><a href="{{ url('/right/add?slug=' . $accession->slug) }}" class="dropdown-item">Create new rights</a></li>
            <li><a href="{{ url('/physicalobject/link?slug=' . $accession->slug) }}" class="dropdown-item">Link physical storage</a></li>
          </ul>
        </div>
      </li>
    </ul>
  @endauth
@endsection
