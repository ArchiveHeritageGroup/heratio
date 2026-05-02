@extends('theme::layouts.3col')

@section('title', $actor->authorized_form_of_name ?? config('app.ui_label_actor', 'Authority record'))
@section('body-class', 'view actor')

@section('sidebar')
  @if(count($relatedActors) > 0)
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Related authority records') }}</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedActors as $related)
          <li class="list-group-item">
            <a href="{{ route('actor.show', $related->slug) }}">{{ $related->name ?: '[Untitled]' }}</a>@if(!empty($related->dates_of_existence)) <span class="text-muted">({{ $related->dates_of_existence }})</span>@endif
            @if(!empty($related->identifier))
              <br><small class="text-muted">Identifier: {{ $related->identifier }}</small>
            @endif
            @if(!empty($related->type_id) && isset($relationCategoryNames[$related->type_id]))
              <br><small class="text-muted">Category: {{ $relationCategoryNames[$related->type_id] }}</small>
            @endif
            @if(!empty($related->type_id) && isset($relationTypeNames[$related->type_id]))
              <br><small class="text-muted">Type: {{ $relationTypeNames[$related->type_id] }}</small>
            @endif
            @if(!empty($related->relation_description))
              <br><small class="text-muted">{{ $related->relation_description }}</small>
            @endif
            @if(!empty($related->relation_date))
              <br><small class="text-muted">Dates: {{ $related->relation_date }}</small>
            @elseif(!empty($related->start_date) || !empty($related->end_date))
              <br><small class="text-muted">Dates: {{ $related->start_date ?? '?' }} - {{ $related->end_date ?? '?' }}</small>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(($relatedResourcesTotal ?? 0) > 0)
    <div class="card mb-3" id="creators">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Creator of') }}</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedResources as $resource)
          <li class="list-group-item">
            <a href="{{ route('informationobject.show', $resource->slug) }}">
              {{ $resource->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
      </ul>
      @if($relatedResourcesLastPage > 1)
        <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center border-top">
          @if($relatedResourcesPage > 1)
            <a href="{{ request()->fullUrlWithQuery(['creator_page' => $relatedResourcesPage - 1]) }}#creators" class="btn btn-sm btn-outline-secondary">&laquo; Previous</a>
          @else
            <span class="btn btn-sm btn-outline-secondary disabled">&laquo; Previous</span>
          @endif
          <small class="text-muted">Page {{ $relatedResourcesPage }} of {{ $relatedResourcesLastPage }}</small>
          @if($relatedResourcesPage < $relatedResourcesLastPage)
            <a href="{{ request()->fullUrlWithQuery(['creator_page' => $relatedResourcesPage + 1]) }}#creators" class="btn btn-sm btn-outline-secondary">Next &raquo;</a>
          @else
            <span class="btn btn-sm btn-outline-secondary disabled">{{ __('Next &raquo;') }}</span>
          @endif
        </div>
      @endif
      <div class="card-body p-0">
        <a class="btn atom-btn-white border-0 w-100" href="{{ route('glam.browse') }}?topLevel=0&creator={{ $actor->id }}">
          <i class="fas fa-search me-1" aria-hidden="true"></i>
          Browse {{ $relatedResourcesTotal }} result{{ $relatedResourcesTotal !== 1 ? 's' : '' }}
        </a>
      </div>
    </div>
  @endif

  @if(($subjectOfResourcesTotal ?? 0) > 0)
    <div class="card mb-3" id="subjects">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Subject of') }}</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($subjectOfResources as $resource)
          <li class="list-group-item">
            <a href="{{ route('informationobject.show', $resource->slug) }}">
              {{ $resource->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
      </ul>
      @if($subjectOfResourcesLastPage > 1)
        <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center border-top">
          @if($subjectOfResourcesPage > 1)
            <a href="{{ request()->fullUrlWithQuery(['subject_page' => $subjectOfResourcesPage - 1]) }}#subjects" class="btn btn-sm btn-outline-secondary">&laquo; Previous</a>
          @else
            <span class="btn btn-sm btn-outline-secondary disabled">&laquo; Previous</span>
          @endif
          <small class="text-muted">Page {{ $subjectOfResourcesPage }} of {{ $subjectOfResourcesLastPage }}</small>
          @if($subjectOfResourcesPage < $subjectOfResourcesLastPage)
            <a href="{{ request()->fullUrlWithQuery(['subject_page' => $subjectOfResourcesPage + 1]) }}#subjects" class="btn btn-sm btn-outline-secondary">Next &raquo;</a>
          @else
            <span class="btn btn-sm btn-outline-secondary disabled">{{ __('Next &raquo;') }}</span>
          @endif
        </div>
      @endif
      <div class="card-body p-0">
        <a class="btn atom-btn-white border-0 w-100" href="{{ route('glam.browse') }}?topLevel=0&names={{ $actor->id }}">
          <i class="fas fa-search me-1" aria-hidden="true"></i>
          Browse {{ $subjectOfResourcesTotal }} result{{ $subjectOfResourcesTotal !== 1 ? 's' : '' }}
        </a>
      </div>
    </div>
  @endif

  @if($relatedFunctions->isNotEmpty() && \AhgCore\Services\AhgSettingsService::getBool('authority_function_linking_enabled', true))
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Related functions') }}</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedFunctions as $fn)
          <li class="list-group-item">
            <a href="{{ route('function.show', $fn->slug) }}">{{ $fn->name ?: '[Untitled]' }}</a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif
@endsection

@section('right')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  <nav>
    {{-- Clipboard --}}
    <h4 class="h5 mb-2">{{ __('Clipboard') }}</h4>
    <ul class="list-unstyled mb-3">
      <li>
        @include('ahg-core::clipboard._button', ['slug' => $actor->slug, 'type' => 'actor', 'wide' => true])
      </li>
    </ul>

    {{-- Export (matching AtoM) --}}
    <h4 class="h5 mb-2">{{ __('Export') }}</h4>
    <ul class="list-unstyled mb-3">
      <li>
        <a class="atom-icon-link" href="{{ url('/' . $actor->slug . '/sfEacPlugin') }}">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true"></i>EAC
        </a>
      </li>
    </ul>

    {{-- Subject access points in sidebar (matching AtoM) --}}
    @if(isset($subjects) && count($subjects) > 0)
      <h4 class="h5 mb-2">{{ __('Subject access points') }}</h4>
      <ul class="list-unstyled mb-3">
        @foreach($subjects as $subject)
          <li><span class="badge bg-light text-dark me-1">{{ $subject->name }}</span></li>
        @endforeach
      </ul>
    @endif

    {{-- Place access points in sidebar (matching AtoM) --}}
    @if(isset($places) && count($places) > 0)
      <h4 class="h5 mb-2">{{ __('Place access points') }}</h4>
      <ul class="list-unstyled mb-3">
        @foreach($places as $place)
          <li><span class="badge bg-light text-dark me-1">{{ $place->name }}</span></li>
        @endforeach
      </ul>
    @endif
  </nav>

  <div class="d-flex gap-1 mb-3">
    <a class="btn btn-sm atom-btn-white" href="{{ route('actor.print', $actor->slug) }}" target="_blank" title="{{ __('Print') }}">
      <i class="fas fa-print"></i>
    </a>
  </div>
@endsection

@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'ISAAR(CPF)'])

  {{-- Translation-provenance bulk-load for AI-disclosure badges (issue #36 Phase 4) --}}
  @php
    $translationSources = \AhgTranslation\Helpers\TranslationProvenance::forRecord(
        (int) $actor->id,
        app()->getLocale()
    );
  @endphp

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-actor', ['actor' => $actor])
  @else

  <h1>
    {{ $actor->authorized_form_of_name }}@include('ahg-translation::components.badge', ['source' => $translationSources['authorized_form_of_name'] ?? null])
    {{-- ICIP cultural-sensitivity badge (issue #36 Phase 2b). --}}
    @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $actor->icip_sensitivity ?? null])
    @if($completeness ?? null)
      @php
        $levelColors = ['stub' => 'danger', 'minimal' => 'warning', 'partial' => 'info', 'full' => 'success'];
        $color = $levelColors[$completeness->completeness_level] ?? 'secondary';
      @endphp
      <span class="badge bg-{{ $color }} ms-2" title="Completeness: {{ $completeness->completeness_score }}%">{{ $completeness->completeness_score }}%</span>
    @endif
  </h1>

  {{-- Breadcrumb (matching AtoM) --}}
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('actor.browse') }}">{{ config('app.ui_label_actor', 'Authority record') }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $actor->authorized_form_of_name }}</li>
    </ol>
  </nav>

  @if(!empty($translations))
    @include('ahg-core::_translation-links')
  @endif

  {{-- Identity area --}}
  <section id="identityArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('actor.edit', $actor->slug) }}#identity-collapse" class="text-primary text-decoration-none">Identity area</a><a href="{{ route('actor.edit', $actor->slug) }}#identity-collapse" class="ms-auto text-muted" title="{{ __('Edit Identity area') }}"><i class="fas fa-pencil-alt fa-sm"></i></a>@else Identity area @endauth</div></h2>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Type of entity') }}</h3><div class="col-9 p-2">{{ $entityTypeName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Authorized form of name') }}</h3><div class="col-9 p-2">{{ $actor->authorized_form_of_name ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Parallel form(s) of name') }}</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach(($otherNames ?? collect())->where('type_id', 148) as $n)<li>{{ $n->name }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Standardized form(s) of name according to other rules') }}</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach(($otherNames ?? collect())->where('type_id', 165) as $n)<li>{{ $n->name }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Other form(s) of name') }}</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach(($otherNames ?? collect())->where('type_id', 149) as $n)<li>{{ $n->name }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Identifiers for corporate bodies') }}</h3><div class="col-9 p-2">{{ $actor->corporate_body_identifiers ?? '' }}</div></div>
  </section>

  {{-- Description area --}}
  <section id="descriptionArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('actor.edit', $actor->slug) }}#description-collapse" class="text-primary text-decoration-none">Description area</a><a href="{{ route('actor.edit', $actor->slug) }}#description-collapse" class="ms-auto text-muted" title="{{ __('Edit Description area') }}"><i class="fas fa-pencil-alt fa-sm"></i></a>@else Description area @endauth</div></h2>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dates of existence') }}</h3><div class="col-9 p-2">{{ $actor->dates_of_existence ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('History') }}</h3><div class="col-9 p-2">{!! ($actor->history ?? '') ? nl2br(e($actor->history)) : '' !!}@include('ahg-translation::components.badge', ['source' => $translationSources['history'] ?? null])</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Places') }}</h3><div class="col-9 p-2">{{ $actor->places ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Legal status') }}</h3><div class="col-9 p-2">{{ $actor->legal_status ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Functions, occupations and activities') }}</h3><div class="col-9 p-2">{{ $actor->functions ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Mandates/sources of authority') }}</h3><div class="col-9 p-2">{{ $actor->mandates ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Internal structures/genealogy') }}</h3><div class="col-9 p-2">{{ $actor->internal_structures ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('General context') }}</h3><div class="col-9 p-2">{{ $actor->general_context ?? '' }}</div></div>
  </section>

  {{-- Relationships area --}}
  <section id="relationshipsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('actor.edit', $actor->slug) }}#relationships-collapse" class="text-primary text-decoration-none">Relationships area</a><a href="{{ route('actor.edit', $actor->slug) }}#relationships-collapse" class="ms-auto text-muted" title="{{ __('Edit Relationships area') }}"><i class="fas fa-pencil-alt fa-sm"></i></a>@else Relationships area @endauth</div></h2>
    @foreach($relatedActors ?? [] as $related)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related entity') }}</h3>
        <div class="col-9 p-2">
          <a href="{{ route('actor.show', $related->slug) }}">{{ $related->name ?: '[Untitled]' }}</a>@if(!empty($related->dates_of_existence)) <span class="note2">({{ $related->dates_of_existence }})</span>@endif

          {{-- Identifier of related entity --}}
          @if(!empty($related->identifier))
            <div class="field row g-0 mt-1">
              <h4 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 ps-0" style="font-size:.85rem;color:var(--ahg-primary);">{{ __('Identifier') }}</h4>
              <div class="col-8 p-1">{{ $related->identifier }}</div>
            </div>
          @endif

          {{-- Category of relationship --}}
          @if(!empty($related->type_id) && isset($relationCategoryNames[$related->type_id]))
            <div class="field row g-0">
              <h4 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 ps-0" style="font-size:.85rem;color:var(--ahg-primary);">{{ __('Category of relationship') }}</h4>
              <div class="col-8 p-1">{{ $relationCategoryNames[$related->type_id] }}</div>
            </div>
          @endif

          {{-- Type of relationship (with converse term) --}}
          @if(!empty($related->type_id) && isset($relationTypeNames[$related->type_id]))
            <div class="field row g-0">
              <h4 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 ps-0" style="font-size:.85rem;color:var(--ahg-primary);">{{ __('Type of relationship') }}</h4>
              <div class="col-8 p-1">
                {{ $relationTypeNames[$related->type_id] }}
                @if(!empty($converseRelationTypeNames[$related->type_id]))
                  <span class="text-muted">(converse: {{ $converseRelationTypeNames[$related->type_id] }})</span>
                @endif
              </div>
            </div>
          @endif

          {{-- Dates of relationship --}}
          @if(!empty($related->relation_date) || !empty($related->start_date) || !empty($related->end_date))
            <div class="field row g-0">
              <h4 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 ps-0" style="font-size:.85rem;color:var(--ahg-primary);">{{ __('Dates of relationship') }}</h4>
              <div class="col-8 p-1">
                @if(!empty($related->relation_date))
                  {{ $related->relation_date }}
                @elseif(!empty($related->start_date) || !empty($related->end_date))
                  {{ $related->start_date ?? '?' }} - {{ $related->end_date ?? '?' }}
                @endif
              </div>
            </div>
          @endif

          {{-- Description of relationship --}}
          @if(!empty($related->relation_description))
            <div class="field row g-0">
              <h4 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 ps-0" style="font-size:.85rem;color:var(--ahg-primary);">{{ __('Description of relationship') }}</h4>
              <div class="col-8 p-1">{{ $related->relation_description }}</div>
            </div>
          @endif
        </div>
      </div>
    @endforeach

    {{-- Related functions (matching AtoM) --}}
    @if(\AhgCore\Services\AhgSettingsService::getBool('authority_function_linking_enabled', true))
    @foreach($relatedFunctions ?? [] as $fn)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related function') }}</h3>
        <div class="col-9 p-2">
          <a href="{{ route('function.show', $fn->slug) }}">{{ $fn->name ?: '[Untitled]' }}</a>
        </div>
      </div>
    @endforeach
    @endif
  </section>

  {{-- Contact information --}}
  <section id="contactArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('actor.edit', $actor->slug) }}#contact-collapse" class="text-primary text-decoration-none">Contact information</a>@else Contact information @endauth</div></h2>
    @foreach($contacts ?? [] as $contact)
      @if(!$loop->first)
        <hr class="my-3">
      @endif
      <div class="p-2">
        @if($contact->primary_contact) <span class="badge bg-success mb-1">{{ __('Primary') }}</span> @endif
        @if($contact->contact_person) <div>{{ $contact->contact_person }}</div> @endif
        @if($contact->street_address) <div>{{ $contact->street_address }}</div> @endif
        @if($contact->city || $contact->region || $contact->postal_code)
          <div>{{ $contact->city ?? '' }}{{ $contact->region ? ', ' . $contact->region : '' }} {{ $contact->postal_code ?? '' }}</div>
        @endif
        @if($contact->country_code) <div>{{ $contact->country_code }}</div> @endif
        @if($contact->telephone) <div><a href="tel:{{ $contact->telephone }}">{{ $contact->telephone }}</a></div> @endif
        @if($contact->fax) <div>Fax: {{ $contact->fax }}</div> @endif
        @if($contact->email) <div><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></div> @endif
        @if($contact->website) <div><a href="{{ $contact->website }}" target="_blank">{{ $contact->website }} <i class="fas fa-external-link-alt"></i></a></div> @endif
        @if($contact->note) <div class="text-muted mt-1">{{ $contact->note }}</div> @endif
      </div>
    @endforeach
  </section>

  {{-- Access points area --}}
  <section id="accessPointsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('actor.edit', $actor->slug) }}#access-collapse" class="text-primary text-decoration-none">Access points area</a>@else Access points area @endauth</div></h2>
    <div class="field row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject access points') }}</h3>
      <div class="col-9 p-2">
        @foreach($subjects ?? [] as $subject)
          <span class="badge bg-light text-dark me-1">{{ $subject->name }}</span>
        @endforeach
      </div>
    </div>
    <div class="field row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place access points') }}</h3>
      <div class="col-9 p-2">
        @foreach($places ?? [] as $place)
          <span class="badge bg-light text-dark me-1">{{ $place->name }}</span>
        @endforeach
      </div>
    </div>
    <div class="field row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Occupations') }}</h3>
      <div class="col-9 p-2">
        @foreach($occupations ?? [] as $occ)
          <div>
            @if($occ->slug)
              <a href="{{ route('term.show', $occ->slug) }}">{{ $occ->name }}</a>
            @else
              {{ $occ->name }}
            @endif
            @if(isset($occupationNotes[$occ->relation_id]) && !empty($occupationNotes[$occ->relation_id]))
              <div class="field row g-0 mt-1">
                <h4 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-1 ps-0" style="font-size:.85rem;color:var(--ahg-primary);">{{ __('Note') }}</h4>
                <div class="col-8 p-1">{{ $occupationNotes[$occ->relation_id] }}</div>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- External identifiers area --}}
  @if(($externalIdentifiers ?? collect())->isNotEmpty())
  <section id="externalIdentifiersArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('External identifiers') }}</div></h2>
    @foreach($externalIdentifiers as $eid)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ ucfirst($eid->identifier_type) }}</h3>
        <div class="col-9 p-2">
          @if($eid->uri)
            <a href="{{ $eid->uri }}" target="_blank" rel="noopener">{{ $eid->identifier_value }}</a>
          @else
            {{ $eid->identifier_value }}
          @endif
          @if($eid->label)
            <br><small class="text-muted">{{ $eid->label }}</small>
          @endif
          @if($eid->is_verified)
            <span class="badge bg-success ms-1" title="Verified{{ $eid->verified_at ? ' on ' . $eid->verified_at : '' }}"><i class="fas fa-check"></i> {{ __('Verified') }}</span>
          @endif
        </div>
      </div>
    @endforeach
  </section>
  @endif

  {{-- Structured occupations area --}}
  @if(($structuredOccupations ?? collect())->isNotEmpty())
  <section id="structuredOccupationsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Structured occupations') }}</div></h2>
    @foreach($structuredOccupations as $socc)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Occupation') }}</h3>
        <div class="col-9 p-2">
          {{ $socc->term_name ?? $socc->occupation_text ?? '' }}
          @if($socc->date_from || $socc->date_to)
            <br><small class="text-muted">{{ $socc->date_from ?? '?' }} - {{ $socc->date_to ?? '?' }}</small>
          @endif
          @if($socc->notes)
            <br><small class="text-muted">{{ $socc->notes }}</small>
          @endif
        </div>
      </div>
    @endforeach
  </section>
  @endif

  {{-- Completeness details area --}}
  @if($completeness ?? null)
  <section id="completenessArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Completeness') }}</div></h2>
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Score') }}</h3>
      <div class="col-9 p-2">
        @php
          $levelColors = ['stub' => 'danger', 'minimal' => 'warning', 'partial' => 'info', 'full' => 'success'];
          $color = $levelColors[$completeness->completeness_level] ?? 'secondary';
        @endphp
        <div class="progress" style="height: 20px;">
          <div class="progress-bar bg-{{ $color }}" role="progressbar" style="width: {{ $completeness->completeness_score }}%;" aria-valuenow="{{ $completeness->completeness_score }}" aria-valuemin="0" aria-valuemax="100">{{ $completeness->completeness_score }}%</div>
        </div>
      </div>
    </div>
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Level') }}</h3>
      <div class="col-9 p-2"><span class="badge bg-{{ $color }}">{{ ucfirst($completeness->completeness_level) }}</span></div>
    </div>
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Has external IDs') }}</h3>
      <div class="col-9 p-2">{{ $completeness->has_external_ids ? 'Yes' : 'No' }}</div>
    </div>
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Has relations') }}</h3>
      <div class="col-9 p-2">{{ $completeness->has_relations ? 'Yes' : 'No' }}</div>
    </div>
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Has resources') }}</h3>
      <div class="col-9 p-2">{{ $completeness->has_resources ? 'Yes' : 'No' }}</div>
    </div>
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Has contacts') }}</h3>
      <div class="col-9 p-2">{{ $completeness->has_contacts ? 'Yes' : 'No' }}</div>
    </div>
    @if($completeness->scored_at)
    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Last scored') }}</h3>
      <div class="col-9 p-2">{{ $completeness->scored_at }}</div>
    </div>
    @endif
  </section>
  @endif

  {{-- Control area --}}
  <section id="controlArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('actor.edit', $actor->slug) }}#control-collapse" class="text-primary text-decoration-none">Control area</a>@else Control area @endauth</div></h2>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Authority record identifier') }}</h3><div class="col-9 p-2">{{ $actor->description_identifier ?? '' }}</div></div>
    @if($maintainingRepository ?? null)
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Maintained by') }}</h3><div class="col-9 p-2"><a href="{{ route('repository.show', $maintainingRepository->slug) }}">{{ $maintainingRepository->name ?: '[Untitled]' }}</a></div></div>
    @endif
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Institution identifier') }}</h3><div class="col-9 p-2">{{ $actor->institution_responsible_identifier ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rules and/or conventions used') }}</h3><div class="col-9 p-2">{!! ($actor->rules ?? '') ? nl2br(e($actor->rules)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Status') }}</h3><div class="col-9 p-2">{{ $descriptionStatusName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Level of detail') }}</h3><div class="col-9 p-2">{{ $descriptionDetailName ?? '' }}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dates of creation, revision and deletion') }}</h3><div class="col-9 p-2">{!! ($actor->revision_history ?? '') ? nl2br(e($actor->revision_history)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language(s)') }}</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach($languages ?? [] as $lang)<li>{{ $lang }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Script(s)') }}</h3><div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">@foreach($scripts ?? [] as $scr)<li>{{ $scr }}</li>@endforeach</ul></div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Sources') }}</h3><div class="col-9 p-2">{!! ($actor->sources ?? '') ? nl2br(e($actor->sources)) : '' !!}</div></div>
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Maintenance notes') }}</h3><div class="col-9 p-2">{!! ($maintenanceNotes ?? '') ? nl2br(e($maintenanceNotes)) : '' !!}</div></div>
    @if($actor->source_standard ?? null)
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source standard') }}</h3><div class="col-9 p-2">{{ $actor->source_standard }}</div></div>
    @endif
    @if(isset($sourceLangName) && $sourceLangName)
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source language') }}</h3><div class="col-9 p-2">{{ $sourceLangName }}</div></div>
    @endif
    @if(isset($parentActor) && $parentActor)
    <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Parent authority record') }}</h3><div class="col-9 p-2"><a href="{{ route('actor.show', $parentActor->slug) }}">{{ $parentActor->authorized_form_of_name ?: '[Untitled]' }}</a></div></div>
    @endif
  </section>

  {{-- ===== Digital object metadata (matching AtoM sfIsaarPlugin) ===== --}}
  @if(isset($digitalObjects) && $digitalObjects['master'])
    @php
      $doMaster = $digitalObjects['master'];
      $doReference = $digitalObjects['reference'];
      $doThumbnail = $digitalObjects['thumbnail'];
      $doMasterUrl = \AhgCore\Services\DigitalObjectService::getUrl($doMaster);
      $doRefUrl = $doReference ? \AhgCore\Services\DigitalObjectService::getUrl($doReference) : '';
      $doThumbUrl = $doThumbnail ? \AhgCore\Services\DigitalObjectService::getUrl($doThumbnail) : '';
      $doMediaTypeName = \AhgCore\Services\DigitalObjectService::getMediaType($doMaster);
      $doUploadedAt = \Illuminate\Support\Facades\DB::table('object')->where('id', $doMaster->id)->value('created_at');
    @endphp
    <section class="digitalObjectMetadata border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Digital object metadata') }}</div></h2>

      {{-- Master file --}}
      <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">{{ __('Master file') }}</h4>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filename') }}</h3>
        <div class="col-9 p-2">
          @auth
            <a href="{{ $doMasterUrl }}" target="_blank">{{ $doMaster->name }}</a>
          @else
            {{ $doMaster->name }}
          @endauth
        </div>
      </div>
      @if($doMaster->media_type_id)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Media type') }}</h3>
          <div class="col-9 p-2">{{ ucfirst($doMediaTypeName) }}</div>
        </div>
      @endif
      @if($doMaster->mime_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('MIME type') }}</h3>
          <div class="col-9 p-2">{{ $doMaster->mime_type }}</div>
        </div>
      @endif
      @if($doMaster->byte_size)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filesize') }}</h3>
          <div class="col-9 p-2">
            @if($doMaster->byte_size > 1048576)
              {{ number_format($doMaster->byte_size / 1048576, 1) }} MB
            @else
              {{ number_format($doMaster->byte_size / 1024, 1) }} KB
            @endif
          </div>
        </div>
      @endif
      @if($doUploadedAt)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Uploaded') }}</h3>
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($doUploadedAt)->format('F j, Y') }}</div>
        </div>
      @endif
      @if($doMaster->checksum)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Checksum') }}</h3>
          <div class="col-9 p-2"><code class="small">{{ $doMaster->checksum }}</code></div>
        </div>
      @endif

      {{-- Reference copy --}}
      @if($doReference)
        <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">{{ __('Reference copy') }}</h4>
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filename') }}</h3>
          <div class="col-9 p-2">
            @auth
              <a href="{{ $doRefUrl }}" target="_blank">{{ $doReference->name }}</a>
            @else
              {{ $doReference->name }}
            @endauth
          </div>
        </div>
        @if($doReference->mime_type)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('MIME type') }}</h3>
            <div class="col-9 p-2">{{ $doReference->mime_type }}</div>
          </div>
        @endif
        @if($doReference->byte_size)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filesize') }}</h3>
            <div class="col-9 p-2">
              @if($doReference->byte_size > 1048576)
                {{ number_format($doReference->byte_size / 1048576, 1) }} MB
              @else
                {{ number_format($doReference->byte_size / 1024, 1) }} KB
              @endif
            </div>
          </div>
        @endif
      @endif

      {{-- Thumbnail copy --}}
      @if($doThumbnail)
        <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">{{ __('Thumbnail copy') }}</h4>
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filename') }}</h3>
          <div class="col-9 p-2">
            <a href="{{ $doThumbUrl }}" target="_blank">{{ $doThumbnail->name }}</a>
          </div>
        </div>
        @if($doThumbnail->mime_type)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('MIME type') }}</h3>
            <div class="col-9 p-2">{{ $doThumbnail->mime_type }}</div>
          </div>
        @endif
        @if($doThumbnail->byte_size)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filesize') }}</h3>
            <div class="col-9 p-2">
              @if($doThumbnail->byte_size > 1048576)
                {{ number_format($doThumbnail->byte_size / 1048576, 1) }} MB
              @else
                {{ number_format($doThumbnail->byte_size / 1024, 1) }} KB
              @endif
            </div>
          </div>
        @endif
      @endif

    </section>
  @endif

  @endif {{-- end heratio/ric view mode --}}

  {{-- Action buttons (bottom bar) — shown in both views --}}
  @auth
  @php $isAdmin = \AhgCore\Services\AclService::check($actor, 'update'); @endphp
  @if($isAdmin)
  <section class="actions">
    <ul class="nav gap-2">
      <li><a class="btn atom-btn-outline-light" href="{{ route('actor.edit', $actor->slug) }}">Edit</a></li>
      <li><a class="btn atom-btn-outline-danger" href="{{ route('actor.confirmDelete', $actor->slug) }}">Delete</a></li>
      <li><a class="btn atom-btn-outline-light" href="{{ route('actor.add') }}">Add new</a></li>
      <li><a class="btn atom-btn-outline-light" href="{{ route('actor.edit', $actor->slug) }}?rename=1"><i class="fas fa-i-cursor me-1"></i>{{ __('Rename') }}</a></li>
      <li>
        <div class="dropup">
          <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            {{ __('More') }}
          </button>
          <ul class="dropdown-menu mb-2">
            @if(isset($digitalObject) && $digitalObject)
              <li><a href="{{ url('/' . $actor->slug . '/editDigitalObject') }}" class="dropdown-item">Edit digital object</a></li>
            @else
              <li><a href="{{ url('/' . $actor->slug . '/linkDigitalObject') }}" class="dropdown-item">Link digital object</a></li>
            @endif
          </ul>
        </div>
      </li>
    </ul>
  </section>
  @endif
  @endauth

  {{-- RiC Context Sidebar --}}
  @include('ahg-ric::_context-sidebar', ['resourceId' => $actor->id])

  {{-- RiC Explorer Panel + RiC Context — only visible in RiC view mode --}}
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-panel', ['resourceId' => $actor->id])

    @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
      @include('ahg-ric::_ric-entities-panel', ['record' => $actor, 'recordType' => 'actor'])
    @endif
  @endif
@endsection
