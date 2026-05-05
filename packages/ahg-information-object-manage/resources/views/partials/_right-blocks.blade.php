{{--
    Shared right-column blocks for GLAM/DAM show pages.

    Required:
      $record  — the IO entity (id, slug, title, updated_at)
      $slug    — $record->slug (or override)
      $type    — clipboard type, default 'informationObject'

    Optional (degrade gracefully if absent):
      $collectionRootId, $digitalObjects, $hasChildren,
      $findingAid, $subjects, $creators, $nameAccessPoints,
      $genres, $places, $physicalObjects, $physicalObjectTypeNames

    Skip flags (pass true from views that already include
    ahg-core::partials._record-sidebar-extras):
      $skipExport, $skipActiveLoans

    Used by: ahg-information-object-manage, ahg-dam, ahg-museum,
             ahg-library, ahg-gallery show views.
--}}
@php
  $type             = $type             ?? 'informationObject';
  $slug             = $slug             ?? $record->slug;
  $collectionRootId = $collectionRootId ?? $record->id;
  $skipExport       = $skipExport       ?? false;
  $skipActiveLoans  = $skipActiveLoans  ?? false;
@endphp

<nav>
  {{-- Clipboard --}}
  <div class="mb-3">
    @include('ahg-core::clipboard._button', ['slug' => $slug, 'type' => $type, 'wide' => true])
  </div>

  {{-- Explore --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-cogs me-1"></i> {{ __('Explore') }}
    </div>
    <div class="list-group list-group-flush">
      @if(\Illuminate\Support\Facades\Route::has('informationobject.reports'))
        <a href="{{ route('informationobject.reports', $slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-print me-1"></i> {{ __('Reports') }}
        </a>
      @endif
      @if(!empty($hasChildren) && \Illuminate\Support\Facades\Route::has('informationobject.inventory'))
        <a href="{{ route('informationobject.inventory', $slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list-alt me-1"></i> {{ __('Inventory') }}
        </a>
      @endif
      <a href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 0]) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-list me-1"></i> {{ __('Browse as list') }}
      </a>
      @if(isset($digitalObjects) && !empty($digitalObjects['master']))
        <a href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 0, 'view' => 'card', 'onlyMedia' => 1]) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-image me-1"></i> {{ __('Browse digital objects') }}
        </a>
      @endif
    </div>
  </div>

  {{-- Import --}}
  @auth
    @if(\Illuminate\Support\Facades\Route::has('informationobject.import.xml'))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-upload me-1"></i> {{ __('Import') }}
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.import.xml', $slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-code me-1"></i> XML
          </a>
          @if(\Illuminate\Support\Facades\Route::has('informationobject.import.csv'))
            <a href="{{ route('informationobject.import.csv', $slug) }}" class="list-group-item list-group-item-action small">
              <i class="fas fa-file-csv me-1"></i> CSV
            </a>
          @endif
        </div>
      </div>
    @endif
  @endauth

  {{-- Export — moved here directly under Import per user request 2026-05-03 --}}
  @unless($skipExport)
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-file-export me-1"></i> {{ __('Export') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('informationobject.export.dc', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-code me-1"></i> {{ __('Dublin Core 1.1 XML') }}
      </a>
      <a href="{{ route('informationobject.export.ead', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-code me-1"></i> {{ __('EAD 2002 XML') }}
      </a>
      <a href="{{ route('informationobject.export.ead3', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-code me-1"></i> {{ __('EAD3 1.1 XML') }}
      </a>
      <a href="{{ route('informationobject.export.ead4', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-code me-1"></i> {{ __('EAD 4 XML') }}
      </a>
      <a href="{{ route('informationobject.export.mods', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-code me-1"></i> {{ __('MODS 3.5 XML') }}
      </a>
      <a href="{{ route('informationobject.export.rico', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-code me-1"></i> {{ __('RiC-O JSON-LD') }}
      </a>
      @auth
        @if(\Illuminate\Support\Facades\Route::has('informationobject.export.csv'))
          <a href="{{ route('informationobject.export.csv', $slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-csv me-1"></i> {{ __('Export CSV') }}
          </a>
        @endif
      @endauth
    </div>
  </div>
  @endunless

  {{-- Marketplace (auth + marketplace_enabled gate via shared partial) --}}
  @includeIf('marketplace::partials._add-to-marketplace', ['ioId' => $record->id])

  {{-- Active Loans --}}
  @if(!$skipActiveLoans && \Illuminate\Support\Facades\Schema::hasTable('ahg_loan'))
    @php
      $activeLoans = \Illuminate\Support\Facades\DB::table('ahg_loan')
          ->join('ahg_loan_object', 'ahg_loan.id', '=', 'ahg_loan_object.loan_id')
          ->where('ahg_loan_object.information_object_id', $record->id)
          ->whereNotIn('ahg_loan.status', ['returned', 'closed', 'cancelled'])
          ->select('ahg_loan.id', 'ahg_loan.loan_number', 'ahg_loan.loan_type', 'ahg_loan.status', 'ahg_loan.partner_institution', 'ahg_loan.end_date')
          ->get();
    @endphp
    @if($activeLoans->isNotEmpty())
      <div class="card mb-3 border-warning">
        <div class="card-header bg-warning text-dark fw-bold">
          <i class="fas fa-exchange-alt me-1"></i> Active Loans ({{ $activeLoans->count() }})
        </div>
        <div class="list-group list-group-flush">
          @foreach($activeLoans as $al)
            @php $isOverdue = $al->end_date && $al->end_date < now()->toDateString(); @endphp
            <a href="{{ route('loan.show', $al->id) }}" class="list-group-item list-group-item-action {{ $isOverdue ? 'list-group-item-danger' : '' }}">
              <div class="d-flex justify-content-between">
                <strong>{{ $al->loan_number }}</strong>
                <span class="badge bg-{{ $al->loan_type === 'out' ? 'info' : 'warning' }}">{{ $al->loan_type === 'out' ? 'Out' : 'In' }}</span>
              </div>
              <small>{{ $al->partner_institution }}</small>
              @if($isOverdue)<span class="badge bg-danger ms-1"><i class="fas fa-exclamation-triangle"></i> {{ __('Overdue') }}</span>@endif
            </a>
          @endforeach
        </div>
      </div>
    @endif
  @endif

  {{-- Export block relocated above (under Import). --}}

  {{-- Finding aid --}}
  @auth
    @if(\Illuminate\Support\Facades\Route::has('informationobject.findingaid.generate'))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-book me-1"></i> {{ __('Finding aid') }}
        </div>
        <div class="list-group list-group-flush">
          @if(!empty($findingAid))
            <a href="{{ route('informationobject.findingaid.download', $slug) }}" class="list-group-item list-group-item-action small">
              <i class="fas fa-download me-1"></i> {{ __('Download') }}
            </a>
            <a href="{{ route('informationobject.findingaid.generate', $slug) }}" class="list-group-item list-group-item-action small">
              <i class="fas fa-sync-alt me-1"></i> {{ __('Regenerate') }}
            </a>
            <form action="{{ route('informationobject.findingaid.delete', $slug) }}" method="POST" class="d-inline">
              @csrf
              <button type="submit" class="list-group-item list-group-item-action small text-danger border-0 text-start w-100" onclick="return confirm('{{ __('Are you sure you want to delete this finding aid?') }}')">
                <i class="fas fa-trash me-1"></i> {{ __('Delete') }}
              </button>
            </form>
          @else
            <a href="{{ route('informationobject.findingaid.generate', $slug) }}" class="list-group-item list-group-item-action small">
              <i class="fas fa-file-alt me-1"></i> {{ __('Generate') }}
            </a>
            @if(\Illuminate\Support\Facades\Route::has('informationobject.findingaid.upload.form') && \AhgCore\Services\AclService::check($record ?? null, 'update'))
              <a href="{{ route('informationobject.findingaid.upload.form', $slug) }}" class="list-group-item list-group-item-action small">
                <i class="fas fa-upload me-1"></i> {{ __('Upload') }}
              </a>
            @endif
          @endif
        </div>
      </div>
    @endif
  @endauth

  {{-- Tasks --}}
  @auth
    @if(\Illuminate\Support\Facades\Route::has('informationobject.calculateDates') && \AhgCore\Services\AclService::check($record ?? null, 'update'))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-tasks me-1"></i> {{ __('Tasks') }}
        </div>
        <div class="list-group list-group-flush">
          <form action="{{ route('informationobject.calculateDates', $slug) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="list-group-item list-group-item-action small border-0 text-start w-100" title="Click 'Calculate dates' to recalculate the start and end dates of a parent-level description. A job runs in the background, accounting for the earliest and most recent dates across all the child descriptions. The results display in the Start and End fields of the edit page.">
              <i class="fas fa-calendar me-1"></i> {{ __('Calculate dates') }}
            </button>
          </form>
          <span class="list-group-item small text-muted">
            <i class="fas fa-clock me-1"></i> {{ __('Last run:') }} {{ ($record->updated_at ?? null) ? \Carbon\Carbon::parse($record->updated_at)->diffForHumans() : __('Never') }}
          </span>
        </div>
      </div>
    @endif
  @endauth

  {{-- Related subjects --}}
  @if(!empty($subjects) && $subjects->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-tag me-1"></i> {{ __('Related subjects') }}
      </div>
      <ul class="list-group list-group-flush">
        @foreach($subjects as $subject)
          <li class="list-group-item small">
            <a href="{{ route('informationobject.browse', ['subject' => $subject->name]) }}" class="text-decoration-none">
              {{ $subject->name }}
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Related people and organizations --}}
  @if((!empty($creators) && $creators->isNotEmpty()) || (!empty($nameAccessPoints) && $nameAccessPoints->isNotEmpty()))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-users me-1"></i> {{ __('Related people and organizations') }}
      </div>
      <ul class="list-group list-group-flush">
        @if(!empty($creators) && $creators->isNotEmpty())
          @foreach($creators as $creator)
            <li class="list-group-item small">
              <a href="{{ route('actor.show', $creator->slug) }}" class="text-decoration-none">{{ $creator->name }}</a>
              <span class="text-muted">(Creation)</span>
            </li>
          @endforeach
        @endif
        @if(!empty($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
          @foreach($nameAccessPoints as $nap)
            <li class="list-group-item small">
              @if(isset($nap->slug))
                <a href="{{ route('actor.show', $nap->slug) }}" class="text-decoration-none">{{ $nap->name }}</a>
              @else
                {{ $nap->name }}
              @endif
            </li>
          @endforeach
        @endif
      </ul>
    </div>
  @endif

  {{-- Related genres --}}
  @if(!empty($genres) && $genres->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-masks-theater me-1"></i> {{ __('Related genres') }}
      </div>
      <ul class="list-group list-group-flush">
        @foreach($genres as $genre)
          <li class="list-group-item small d-flex justify-content-between align-items-center gap-2">
            @if(!empty($genre->slug))
              <a href="{{ route('term.show', $genre->slug) }}" class="text-decoration-none flex-grow-1" title="{{ __('Open genre record') }}">
                {{ $genre->name }}
              </a>
            @else
              <span class="flex-grow-1">{{ $genre->name }}</span>
            @endif
            <a href="{{ route('informationobject.browse', ['genre' => $genre->name]) }}"
               class="text-muted small text-decoration-none"
               title="{{ __('Browse records tagged with this genre') }}">
              <i class="fas fa-search"></i>
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Related places --}}
  @if(!empty($places) && $places->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-map-marker-alt me-1"></i> {{ __('Related places') }}
      </div>
      <ul class="list-group list-group-flush">
        @foreach($places as $place)
          <li class="list-group-item small d-flex justify-content-between align-items-center gap-2">
            @if(!empty($place->slug))
              <a href="{{ route('term.show', $place->slug) }}" class="text-decoration-none flex-grow-1" title="{{ __('Open place record') }}">
                {{ $place->name }}
              </a>
            @else
              <span class="flex-grow-1">{{ $place->name }}</span>
            @endif
            <a href="{{ route('informationobject.browse', ['place' => $place->name]) }}"
               class="text-muted small text-decoration-none"
               title="{{ __('Browse records tagged with this place') }}">
              <i class="fas fa-search"></i>
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Physical storage (visibility check) --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('physical_storage'))
    @if(!empty($physicalObjects) && (is_countable($physicalObjects) ? count($physicalObjects) > 0 : !empty($physicalObjects)))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-box me-1"></i> {{ config('app.ui_label_physicalobject', 'Physical storage') }}
        </div>
        <ul class="list-group list-group-flush">
          @foreach($physicalObjects as $pobj)
            <li class="list-group-item small">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  @if(!empty($physicalObjectTypeNames[$pobj->type_id ?? null]))
                    <span class="badge bg-secondary me-1">{{ $physicalObjectTypeNames[$pobj->type_id] }}</span>
                  @endif
                  @if(isset($pobj->slug))
                    <a href="{{ route('physicalobject.show', $pobj->slug) }}" class="fw-bold text-decoration-none">{{ $pobj->name ?? '[Unknown]' }}</a>
                  @else
                    <span class="fw-bold">{{ $pobj->name ?? '[Unknown]' }}</span>
                  @endif
                </div>
              </div>
              @if(!empty($pobj->location))
                <div class="mt-1 text-muted">
                  <i class="fas fa-map-marker-alt me-1"></i> {{ $pobj->location }}
                </div>
              @endif
              @if(!empty($pobj->description))
                <div class="mt-1 text-muted small">
                  <i class="fas fa-info-circle me-1"></i> {{ $pobj->description }}
                </div>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  @endif

  {{-- RiC Actions --}}
  @if(class_exists(\AhgRic\Controllers\RicEntityController::class) && \AhgCore\Services\MenuService::isPluginEnabled('ahgRicExplorerPlugin'))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-project-diagram me-1"></i> {{ __('RiC') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('ric.explorer') }}?id={{ $record->id }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-project-diagram me-1"></i> {{ __('Graph Explorer') }}
        </a>
        <a href="{{ route('ric.export-jsonld') }}?id={{ $record->id }}" class="list-group-item list-group-item-action small" target="_blank">
          <i class="fas fa-code me-1"></i> {{ __('JSON-LD Export') }}
        </a>
        <a href="{{ route('ric.explorer') }}?id={{ $record->id }}&view=timeline" class="list-group-item list-group-item-action small">
          <i class="fas fa-clock me-1"></i> {{ __('Timeline') }}
        </a>
      </div>
    </div>
  @endif
</nav>
