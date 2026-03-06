@extends('theme::layouts.2col')

@section('title', $io->title ?? 'Archival description')
@section('body-class', 'view informationobject')

@section('sidebar')
  {{-- Digital object thumbnail --}}
  @if($digitalObjects->isNotEmpty())
    @php $firstDo = $digitalObjects->first(); @endphp
    <div class="mb-3">
      <img src="/uploads/r/{{ $firstDo->path ?? '' }}/{{ $firstDo->name ?? '' }}"
           class="img-fluid rounded"
           alt="{{ $io->title }}"
           onerror="this.style.display='none'">
    </div>
  @endif

  {{-- Children tree --}}
  @if($children->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ $children->count() }} child record{{ $children->count() !== 1 ? 's' : '' }}</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($children->take(20) as $child)
          <li class="list-group-item">
            <a href="{{ route('informationobject.show', $child->slug) }}">
              {{ $child->title ?: '[Untitled]' }}
            </a>
            @if($child->level_of_description_id && isset($childLevelNames[$child->level_of_description_id]))
              <span class="badge bg-secondary ms-1">{{ $childLevelNames[$child->level_of_description_id] }}</span>
            @endif
          </li>
        @endforeach
        @if($children->count() > 20)
          <li class="list-group-item text-muted">
            ... and {{ $children->count() - 20 }} more
          </li>
        @endif
      </ul>
    </div>
  @endif
@endsection

@section('content')
  {{-- Breadcrumb --}}
  @if(!empty($breadcrumbs))
    <nav aria-label="Hierarchy">
      <ol class="breadcrumb">
        @foreach($breadcrumbs as $crumb)
          <li class="breadcrumb-item">
            <a href="{{ route('informationobject.show', $crumb->slug) }}">
              {{ $crumb->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">
          {{ $io->title ?: '[Untitled]' }}
        </li>
      </ol>
    </nav>
  @endif

  {{-- Title block --}}
  <h1>{{ $io->title }}</h1>

  @if($levelName)
    <span class="badge bg-primary mb-2">{{ $levelName }}</span>
  @endif

  @if($repository)
    <span class="badge bg-secondary mb-2">
      <a href="{{ route('repository.show', $repository->slug) }}" class="text-white text-decoration-none">
        {{ $repository->name }}
      </a>
    </span>
  @endif

  @if($publicationStatus)
    <span class="badge bg-info mb-2">{{ $publicationStatus }}</span>
  @endif

  {{-- ===== ISAD(G) 3.1 Identity area ===== --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($io->identifier)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Reference code(s)</div>
        <div class="col-md-9">{{ $io->identifier }}</div>
      </div>
    @endif

    @if($io->title)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Title</div>
        <div class="col-md-9">{{ $io->title }}</div>
      </div>
    @endif

    @if($events->isNotEmpty())
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Date(s)</div>
        <div class="col-md-9">
          @foreach($events as $event)
            <div>
              @if($event->type_id && isset($eventTypeNames[$event->type_id]))
                <strong>{{ $eventTypeNames[$event->type_id] }}:</strong>
              @endif
              {{ $event->date_display ?? '' }}
              @if($event->start_date || $event->end_date)
                <span class="text-muted small">
                  ({{ $event->start_date ?? '?' }} - {{ $event->end_date ?? '?' }})
                </span>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endif

    @if($levelName)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Level of description</div>
        <div class="col-md-9">{{ $levelName }}</div>
      </div>
    @endif

    @if($io->extent_and_medium)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Extent and medium</div>
        <div class="col-md-9">{!! nl2br(e($io->extent_and_medium)) !!}</div>
      </div>
    @endif
  </section>

  {{-- ===== ISAD(G) 3.2 Context area ===== --}}
  @if($creators->isNotEmpty() || $repository || $io->archival_history || $io->acquisition)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Context area</h2>

      @if($creators->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Name of creator(s)</div>
          <div class="col-md-9">
            @foreach($creators as $creator)
              <div>
                <a href="{{ route('actor.show', $creator->slug) }}">{{ $creator->name }}</a>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if($repository)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Repository</div>
          <div class="col-md-9">
            <a href="{{ route('repository.show', $repository->slug) }}">{{ $repository->name }}</a>
          </div>
        </div>
      @endif

      @if($io->archival_history)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Archival history</div>
          <div class="col-md-9">{!! nl2br(e($io->archival_history)) !!}</div>
        </div>
      @endif

      @if($io->acquisition)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Immediate source of acquisition or transfer</div>
          <div class="col-md-9">{!! nl2br(e($io->acquisition)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== ISAD(G) 3.3 Content and structure area ===== --}}
  @if($io->scope_and_content || $io->appraisal || $io->accruals || $io->arrangement)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Content and structure area</h2>

      @if($io->scope_and_content)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Scope and content</div>
          <div class="col-md-9">{!! nl2br(e($io->scope_and_content)) !!}</div>
        </div>
      @endif

      @if($io->appraisal)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Appraisal, destruction and scheduling information</div>
          <div class="col-md-9">{!! nl2br(e($io->appraisal)) !!}</div>
        </div>
      @endif

      @if($io->accruals)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Accruals</div>
          <div class="col-md-9">{!! nl2br(e($io->accruals)) !!}</div>
        </div>
      @endif

      @if($io->arrangement)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">System of arrangement</div>
          <div class="col-md-9">{!! nl2br(e($io->arrangement)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== ISAD(G) 3.4 Conditions of access and use area ===== --}}
  @if($io->access_conditions || $io->reproduction_conditions || $languages->isNotEmpty() || $io->physical_characteristics || $io->finding_aids)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Conditions of access and use area</h2>

      @if($io->access_conditions)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Conditions governing access</div>
          <div class="col-md-9">{!! nl2br(e($io->access_conditions)) !!}</div>
        </div>
      @endif

      @if($io->reproduction_conditions)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Conditions governing reproduction</div>
          <div class="col-md-9">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
        </div>
      @endif

      @if($languages->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Language of material</div>
          <div class="col-md-9">
            @foreach($languages as $lang)
              <span class="badge bg-light text-dark me-1">{{ $lang->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($io->physical_characteristics)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Physical characteristics and technical requirements</div>
          <div class="col-md-9">{!! nl2br(e($io->physical_characteristics)) !!}</div>
        </div>
      @endif

      @if($io->finding_aids)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Finding aids</div>
          <div class="col-md-9">{!! nl2br(e($io->finding_aids)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== ISAD(G) 3.5 Allied materials area ===== --}}
  @if($io->location_of_originals || $io->location_of_copies || $io->related_units_of_description)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Allied materials area</h2>

      @if($io->location_of_originals)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Existence and location of originals</div>
          <div class="col-md-9">{!! nl2br(e($io->location_of_originals)) !!}</div>
        </div>
      @endif

      @if($io->location_of_copies)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Existence and location of copies</div>
          <div class="col-md-9">{!! nl2br(e($io->location_of_copies)) !!}</div>
        </div>
      @endif

      @if($io->related_units_of_description)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Related units of description</div>
          <div class="col-md-9">{!! nl2br(e($io->related_units_of_description)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== ISAD(G) 3.6 Notes area ===== --}}
  @if($notes->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Notes area</h2>

      @foreach($notes as $note)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">
            @if($note->type_id && isset($noteTypeNames[$note->type_id]))
              {{ $noteTypeNames[$note->type_id] }}
            @else
              Note
            @endif
          </div>
          <div class="col-md-9">{!! nl2br(e($note->content)) !!}</div>
        </div>
      @endforeach
    </section>
  @endif

  {{-- ===== Access points ===== --}}
  @if($subjects->isNotEmpty() || $places->isNotEmpty() || $nameAccessPoints->isNotEmpty() || $genres->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Access points</h2>

      @if($subjects->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Subject access points</div>
          <div class="col-md-9">
            @foreach($subjects as $subject)
              <span class="badge bg-info text-dark me-1 mb-1">{{ $subject->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($places->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Place access points</div>
          <div class="col-md-9">
            @foreach($places as $place)
              <span class="badge bg-success text-white me-1 mb-1">{{ $place->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($nameAccessPoints->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Name access points</div>
          <div class="col-md-9">
            @foreach($nameAccessPoints as $nap)
              <span class="badge bg-warning text-dark me-1 mb-1">{{ $nap->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($genres->isNotEmpty())
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Genre access points</div>
          <div class="col-md-9">
            @foreach($genres as $genre)
              <span class="badge bg-secondary me-1 mb-1">{{ $genre->name }}</span>
            @endforeach
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== ISAD(G) 3.7 Description control area ===== --}}
  @if($io->institution_responsible_identifier || $io->rules || $io->sources || $io->revision_history)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Description control area</h2>

      @if($io->institution_responsible_identifier)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Institution identifier</div>
          <div class="col-md-9">{{ $io->institution_responsible_identifier }}</div>
        </div>
      @endif

      @if($io->rules)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Rules or conventions</div>
          <div class="col-md-9">{!! nl2br(e($io->rules)) !!}</div>
        </div>
      @endif

      @if($io->sources)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Sources</div>
          <div class="col-md-9">{!! nl2br(e($io->sources)) !!}</div>
        </div>
      @endif

      @if($io->revision_history)
        <div class="row mb-2">
          <div class="col-md-3 fw-bold">Dates of creation, revision and deletion</div>
          <div class="col-md-9">{!! nl2br(e($io->revision_history)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Digital objects ===== --}}
  @if($digitalObjects->count() > 1)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Digital objects</h2>
      <div class="row g-3">
        @foreach($digitalObjects as $dobj)
          <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100">
              <img src="/uploads/r/{{ $dobj->path ?? '' }}/{{ $dobj->name ?? '' }}"
                   class="card-img-top"
                   alt="{{ $io->title }}"
                   onerror="this.parentElement.innerHTML='<div class=\'card-body text-center text-muted\'><i class=\'fas fa-file fa-3x\'></i></div>'">
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif
@endsection
