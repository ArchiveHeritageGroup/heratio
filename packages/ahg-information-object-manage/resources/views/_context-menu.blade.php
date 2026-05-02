{{--
  Information Object Context Menu
  Migrated from AtoM informationobject/contextMenu + _actionIcons.
  Expects: $io (information object with ->slug, ->id, ->repository_id etc.)
  Optional: $hasChildren, $collectionRootId, $findingAid, $digitalObjects
--}}
@php
  $slug = $io->slug ?? ($resource->slug ?? ($slug ?? ''));
  $ioId = $io->id ?? ($resource->id ?? null);
  $collRoot = $collectionRootId ?? ($io->id ?? null);
@endphp

{{-- Clipboard --}}
<div class="mb-3">
  @include('ahg-core::clipboard._button', ['slug' => $slug, 'type' => 'informationObject', 'wide' => true])
</div>

{{-- ===== Explore ===== --}}
<div class="card mb-3">
  <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-cogs me-1"></i> {{ __('Explore') }}
  </div>
  <div class="list-group list-group-flush">
    <a href="{{ route('informationobject.reports', $slug) }}" class="list-group-item list-group-item-action small">
      <i class="fas fa-chart-bar me-1"></i> {{ __('Reports') }}
    </a>
    @if(!empty($hasChildren))
      <a href="{{ route('informationobject.inventory', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-list me-1"></i> {{ __('Inventory') }}
      </a>
    @endif
    <a href="{{ route('informationobject.browse', ['collection' => $collRoot, 'topLod' => 0]) }}" class="list-group-item list-group-item-action small">
      <i class="fas fa-list me-1"></i> {{ __('Browse as list') }}
    </a>
    @if(isset($digitalObjects) && !empty($digitalObjects['master']))
      <a href="{{ route('informationobject.browse', ['collection' => $collRoot, 'topLod' => 0, 'view' => 'card', 'onlyMedia' => 1]) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-image me-1"></i> {{ __('Browse digital objects') }}
      </a>
    @endif
  </div>
</div>

{{-- ===== Import (auth only) ===== --}}
@auth
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-file-import me-1"></i> {{ __('Import') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('informationobject.import.xml', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-file-import me-1"></i> {{ __('XML') }}
      </a>
      <a href="{{ route('informationobject.import.csv', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-file-import me-1"></i> {{ __('CSV') }}
      </a>
    </div>
  </div>
@endauth

{{-- ===== Export ===== --}}
<div class="card mb-3">
  <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-file-export me-1"></i> {{ __('Export') }}
  </div>
  <div class="list-group list-group-flush">
    <a href="{{ route('informationobject.export.dc', $slug) }}" class="list-group-item list-group-item-action small">
      <i class="fas fa-file-export me-1"></i> {{ __('Dublin Core 1.1 XML') }}
    </a>
    <a href="{{ route('informationobject.export.ead', $slug) }}" class="list-group-item list-group-item-action small">
      <i class="fas fa-file-export me-1"></i> {{ __('EAD 2002 XML') }}
    </a>
    <a href="{{ route('informationobject.export.mods', $slug) }}" class="list-group-item list-group-item-action small">
      <i class="fas fa-file-export me-1"></i> {{ __('MODS 3.5 XML') }}
    </a>
    @auth
      <a href="{{ route('informationobject.export.csv', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-file-csv me-1"></i> {{ __('Export CSV') }}
      </a>
    @endauth
  </div>
</div>

{{-- ===== Finding aid (auth only) ===== --}}
@auth
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-book me-1"></i> {{ __('Finding aid') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('informationobject.findingaid.generate', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-file-alt me-1"></i> {{ __('Generate finding aid') }}
      </a>
      @if(\AhgCore\Services\AclService::check($io ?? null, 'update'))
      <a href="{{ route('informationobject.findingaid.upload.form', $slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-upload me-1"></i> {{ __('Upload finding aid') }}
      </a>
      @endif
      @if(isset($findingAid) && $findingAid)
        <a href="{{ route('informationobject.findingaid.download', $slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-download me-1"></i> {{ __('Download finding aid') }}
        </a>
        <form action="{{ route('informationobject.findingaid.delete', $slug) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="list-group-item list-group-item-action small text-danger border-0 text-start w-100" onclick="return confirm('{{ __('Are you sure you want to delete this finding aid?') }}')">
            <i class="fas fa-trash me-1"></i> {{ __('Delete finding aid') }}
          </button>
        </form>
      @endif
    </div>
  </div>
@endauth

{{-- ===== Marketplace (auth only) ===== --}}
@auth
  @if($ioId)
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-store me-1"></i> {{ __('Marketplace') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('ahgmarketplace.seller-listing-create', ['io' => $ioId]) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-tag me-1"></i> {{ __('Add to marketplace') }}
        </a>
      </div>
    </div>
  @endif
@endauth


{{-- ===== Tasks (admin only) ===== --}}
@auth
  @if(\AhgCore\Services\AclService::check($io ?? null, 'update'))
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-tasks me-1"></i> {{ __('Tasks') }}
    </div>
    <div class="list-group list-group-flush">
      <form action="{{ route('informationobject.calculateDates', $slug) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="list-group-item list-group-item-action small border-0 text-start w-100" title="{{ __("Click 'Calculate dates' to recalculate the start and end dates of a parent-level description. A job runs in the background, accounting for the earliest and most recent dates across all the child descriptions.") }}">
          <i class="fas fa-calendar me-1"></i> {{ __('Calculate dates') }}
        </button>
      </form>
    </div>
  </div>
  @endif
@endauth
