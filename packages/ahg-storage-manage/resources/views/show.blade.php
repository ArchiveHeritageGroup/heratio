@extends('theme::layouts.1col')

@section('title', $storage->name ?? config('app.ui_label_physicalobject', 'Physical storage'))
@section('body-class', 'view physicalobject')

@section('content')
  @include('ahg-ric::_view-switch')

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-storage', ['storage' => $storage])
  @else

  <div class="multiline-header d-flex align-items-center mb-3">
    <a href="{{ route('physicalobject.box-list', ['slug' => $storage->slug]) }}" class="text-reset">
      <i class="fas fa-3x fa-print me-3" aria-hidden="true"></i>
      <span class="visually-hidden">Print</span>
    </a>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">{{ $storage->name ?? '[Untitled]' }}</h1>
      <span class="small" id="heading-label">View {{ config('app.ui_label_physicalobject', 'Physical storage') }}</span>
    </div>
  </div>

  @if(!empty($translations))
    @include('ahg-core::_translation-links')
  @endif

  {{-- ===== Physical storage (AtoM-style) ===== --}}
  <section class="section border-bottom" id="physicalStorage">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">@auth<a href="{{ route('physicalobject.edit', $storage->slug) }}#edit-collapse" class="text-primary text-decoration-none">{{ config('app.ui_label_physicalobject', 'Physical storage') }}</a>@else {{ config('app.ui_label_physicalobject', 'Physical storage') }} @endauth</div></h2>
    <div>
      @if($typeName)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Type</h3>
          <div class="col-9 p-2">{{ $typeName }}</div>
        </div>
      @endif

      @if($storage->location ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Location</h3>
          <div class="col-9 p-2">{{ $storage->location }}</div>
        </div>
      @endif

      @if($storage->description ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description</h3>
          <div class="col-9 p-2">{!! nl2br(e($storage->description)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  <div class="row mt-3">
    <div class="col-md-8">

      @if(!empty($extendedData))
        {{-- Extended Location --}}
        @php
          $locationParts = array_filter([
            $extendedData['building'] ?? null,
            !empty($extendedData['floor']) ? 'Floor ' . $extendedData['floor'] : null,
            !empty($extendedData['room']) ? 'Room ' . $extendedData['room'] : null,
          ]);
          $shelfParts = array_filter([
            !empty($extendedData['aisle']) ? 'Aisle ' . $extendedData['aisle'] : null,
            !empty($extendedData['bay']) ? 'Bay ' . $extendedData['bay'] : null,
            !empty($extendedData['rack']) ? 'Rack ' . $extendedData['rack'] : null,
            !empty($extendedData['shelf']) ? 'Shelf ' . $extendedData['shelf'] : null,
            !empty($extendedData['position']) ? 'Pos ' . $extendedData['position'] : null,
          ]);
        @endphp

        @if(!empty($locationParts) || !empty($shelfParts) || !empty($extendedData['barcode']) || !empty($extendedData['reference_code']))
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Location Details</h5>
          </div>
          <div class="card-body">
            @if(!empty($locationParts))
              <p class="mb-2">
                <i class="fas fa-building me-2 text-muted"></i>
                <strong>{!! implode(' &gt; ', array_map('e', $locationParts)) !!}</strong>
              </p>
            @endif

            @if(!empty($shelfParts))
              <p class="mb-2">
                <i class="fas fa-th me-2 text-primary"></i>
                <strong>{!! implode(' &gt; ', array_map('e', $shelfParts)) !!}</strong>
              </p>
            @endif

            <dl class="row mb-0 mt-3">
              @foreach(['building' => 'Building', 'floor' => 'Floor', 'room' => 'Room', 'aisle' => 'Aisle', 'bay' => 'Bay', 'rack' => 'Rack', 'shelf' => 'Shelf', 'position' => 'Position'] as $field => $label)
                @if(!empty($extendedData[$field]))
                  <dt class="col-sm-4">{{ $label }}</dt>
                  <dd class="col-sm-8">{{ $extendedData[$field] }}</dd>
                @endif
              @endforeach

              @if(!empty($extendedData['barcode']))
                <dt class="col-sm-4">Barcode</dt>
                <dd class="col-sm-8"><code>{{ $extendedData['barcode'] }}</code></dd>
              @endif

              @if(!empty($extendedData['reference_code']))
                <dt class="col-sm-4">Reference Code</dt>
                <dd class="col-sm-8">{{ $extendedData['reference_code'] }}</dd>
              @endif
            </dl>
          </div>
        </div>
        @endif

        {{-- Dimensions --}}
        @if(!empty($extendedData['width']) || !empty($extendedData['height']) || !empty($extendedData['depth']))
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-ruler-combined me-2"></i>Dimensions</h5>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              @foreach(['width' => 'Width', 'height' => 'Height', 'depth' => 'Depth'] as $field => $label)
                @if(!empty($extendedData[$field]))
                  <dt class="col-sm-4">{{ $label }}</dt>
                  <dd class="col-sm-8">{{ $extendedData[$field] }} cm</dd>
                @endif
              @endforeach
            </dl>
          </div>
        </div>
        @endif

        {{-- Capacity --}}
        @if(!empty($extendedData['total_capacity']) || !empty($extendedData['total_linear_metres']))
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Capacity</h5>
          </div>
          <div class="card-body">
            @if(!empty($extendedData['total_capacity']))
              @php
                $used = (int)($extendedData['used_capacity'] ?? 0);
                $total = (int)$extendedData['total_capacity'];
                $available = (int)($extendedData['available_capacity'] ?? ($total - $used));
                $percent = $total > 0 ? round(($used / $total) * 100) : 0;
                $barClass = $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-success');
              @endphp
              <h6>Unit Capacity</h6>
              <div class="row mb-3">
                <div class="col-md-4 text-center">
                  <h3 class="mb-0">{{ $total }}</h3>
                  <small class="text-muted">Total</small>
                </div>
                <div class="col-md-4 text-center">
                  <h3 class="mb-0 text-primary">{{ $used }}</h3>
                  <small class="text-muted">Used</small>
                </div>
                <div class="col-md-4 text-center">
                  <h3 class="mb-0 text-success">{{ $available }}</h3>
                  <small class="text-muted">Available</small>
                </div>
              </div>
              <div class="progress mb-2" style="height:25px">
                <div class="progress-bar {{ $barClass }}" role="progressbar" style="width:{{ $percent }}%">
                  {{ $percent }}% used
                </div>
              </div>
              <p class="text-muted mb-0">Unit: {{ $extendedData['capacity_unit'] ?? 'items' }}</p>
            @endif

            @if(!empty($extendedData['total_linear_metres']))
              <hr>
              @php
                $usedLm = (float)($extendedData['used_linear_metres'] ?? 0);
                $totalLm = (float)$extendedData['total_linear_metres'];
                $availableLm = (float)($extendedData['available_linear_metres'] ?? ($totalLm - $usedLm));
                $percentLm = $totalLm > 0 ? round(($usedLm / $totalLm) * 100) : 0;
                $barClassLm = $percentLm >= 90 ? 'bg-danger' : ($percentLm >= 70 ? 'bg-warning' : 'bg-success');
              @endphp
              <h6>Linear Metres</h6>
              <div class="row mb-3">
                <div class="col-md-4 text-center">
                  <h3 class="mb-0">{{ number_format($totalLm, 2) }}</h3>
                  <small class="text-muted">Total</small>
                </div>
                <div class="col-md-4 text-center">
                  <h3 class="mb-0 text-primary">{{ number_format($usedLm, 2) }}</h3>
                  <small class="text-muted">Used</small>
                </div>
                <div class="col-md-4 text-center">
                  <h3 class="mb-0 text-success">{{ number_format($availableLm, 2) }}</h3>
                  <small class="text-muted">Available</small>
                </div>
              </div>
              <div class="progress" style="height:25px">
                <div class="progress-bar {{ $barClassLm }}" role="progressbar" style="width:{{ $percentLm }}%">
                  {{ $percentLm }}% used
                </div>
              </div>
            @endif
          </div>
        </div>
        @endif

        {{-- Environmental & Security --}}
        @if(!empty($extendedData['climate_controlled']) || !empty($extendedData['security_level']) || !empty($extendedData['temperature_min']))
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Environmental &amp; Security</h5>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              @if(!empty($extendedData['climate_controlled']))
                <dt class="col-sm-4">Climate Controlled</dt>
                <dd class="col-sm-8"><span class="badge bg-info">Yes</span></dd>
              @endif

              @if(!empty($extendedData['temperature_min']) || !empty($extendedData['temperature_max']))
                <dt class="col-sm-4">Temperature Range</dt>
                <dd class="col-sm-8">{{ $extendedData['temperature_min'] ?? '?' }}°C - {{ $extendedData['temperature_max'] ?? '?' }}°C</dd>
              @endif

              @if(!empty($extendedData['humidity_min']) || !empty($extendedData['humidity_max']))
                <dt class="col-sm-4">Humidity Range</dt>
                <dd class="col-sm-8">{{ $extendedData['humidity_min'] ?? '?' }}% - {{ $extendedData['humidity_max'] ?? '?' }}%</dd>
              @endif

              @if(!empty($extendedData['security_level']))
                <dt class="col-sm-4">Security Level</dt>
                <dd class="col-sm-8"><span class="badge bg-danger">{{ ucfirst($extendedData['security_level']) }}</span></dd>
              @endif

              @if(!empty($extendedData['access_restrictions']))
                <dt class="col-sm-4">Access Restrictions</dt>
                <dd class="col-sm-8">{!! nl2br(e($extendedData['access_restrictions'])) !!}</dd>
              @endif
            </dl>
          </div>
        </div>
        @endif

        {{-- Notes --}}
        @if(!empty($extendedData['notes']))
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
          </div>
          <div class="card-body">
            {!! nl2br(e($extendedData['notes'])) !!}
          </div>
        </div>
        @endif
      @endif

    </div>

    <div class="col-md-4">

      {{-- Status --}}
      @if(!empty($extendedData['status']))
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-toggle-on me-2"></i>Status</h5>
        </div>
        <div class="card-body text-center">
          @php
            $statusBadge = match($extendedData['status']) {
              'active' => 'bg-success',
              'full' => 'bg-danger',
              'maintenance' => 'bg-warning',
              'decommissioned' => 'bg-secondary',
              default => 'bg-primary',
            };
          @endphp
          <span class="badge {{ $statusBadge }} fs-5 p-2">{{ ucfirst($extendedData['status']) }}</span>
        </div>
      </div>
      @endif

      {{-- Actions --}}
      @auth
      @php $isAdmin = auth()->user()->is_admin; @endphp
      <div class="card mb-4">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h5>
        </div>
        <div class="card-body">
          <a href="{{ route('physicalobject.edit', $storage->slug) }}" class="btn atom-btn-outline-success w-100 mb-2">
            <i class="fas fa-edit me-1"></i>Edit
          </a>
          <a href="{{ route('physicalobject.browse') }}" class="btn atom-btn-outline-light w-100 mb-2">
            <i class="fas fa-list me-1"></i>Browse storage locations
          </a>
          @if($isAdmin)
          <a href="{{ route('physicalobject.confirmDelete', $storage->slug) }}" class="btn atom-btn-outline-danger w-100">
            <i class="fas fa-trash me-1"></i>Delete
          </a>
          @endif
        </div>
      </div>
      @endauth

    </div>
  </div>

  {{-- Admin area --}}
  @auth
    @if(auth()->user()->is_admin && $storage->source_culture)
      <section class="section border-bottom mb-3" id="adminArea">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">
            Administration area
          </div>
        </h2>
        <div>
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source language</h3>
            <div class="col-9 p-2">
              @php
                $displayLang = function_exists('locale_get_display_language')
                  ? locale_get_display_language($storage->source_culture, app()->getLocale())
                  : $storage->source_culture;
              @endphp
              {{ $displayLang }}
            </div>
          </div>
        </div>
      </section>
    @endif
  @endauth

  {{-- ===== Related resources / accessions (collapsible, at bottom) ===== --}}
  @php
    $relatedTotal = (isset($descriptions) ? $descriptions->count() : 0)
      + (isset($accessions) ? $accessions->count() : 0);
  @endphp
  @if($relatedTotal > 0)
    <div class="accordion mb-3" id="relatedResourcesAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="relatedResourcesHeading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse"
                  data-bs-target="#relatedResourcesCollapse" aria-expanded="true"
                  aria-controls="relatedResourcesCollapse">
            <i class="fas fa-link me-2"></i>Related resources
            <span class="badge bg-light text-dark ms-2">{{ $relatedTotal }}</span>
          </button>
        </h2>
        <div id="relatedResourcesCollapse" class="accordion-collapse collapse show"
             aria-labelledby="relatedResourcesHeading">
          <div class="accordion-body">
            @if(isset($descriptions) && $descriptions->isNotEmpty())
              <h3 class="h6 text-muted">Archival descriptions</h3>
              <ul class="list-unstyled mb-3">
                @foreach($descriptions as $desc)
                  <li class="mb-1">
                    <i class="fas fa-file-alt text-info me-1"></i>
                    <a href="{{ url('/' . $desc->slug) }}">{{ $desc->title ?: '[Untitled]' }}</a>
                  </li>
                @endforeach
              </ul>
            @endif

            @if(isset($accessions) && $accessions->isNotEmpty())
              <h3 class="h6 text-muted">Accessions</h3>
              <ul class="list-unstyled mb-0">
                @foreach($accessions as $acc)
                  <li class="mb-1">
                    <i class="fas fa-inbox text-warning me-1"></i>
                    <a href="{{ route('accession.show', $acc->slug) }}">{{ $acc->title ?: $acc->identifier ?: '[Untitled]' }}</a>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- Bottom action bar --}}
  @auth
  <ul class="actions mb-3 nav gap-2">
    {{-- Edit: any authenticated user --}}
    <li><a href="{{ route('physicalobject.edit', $storage->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
    {{-- Delete: admin only --}}
    @if(auth()->user()->is_admin)
    <li><a href="{{ route('physicalobject.confirmDelete', $storage->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
    @endif
    <li><a href="{{ route('physicalobject.browse') }}" class="btn atom-btn-outline-light"><i class="fas fa-list me-1"></i>Browse</a></li>
  </ul>
  @endauth

  @endif {{-- end heratio/ric view mode --}}

  {{-- RiC Context Sidebar --}}
  @include('ahg-ric::_context-sidebar', ['resourceId' => $storage->id])

  {{-- RiC Explorer Panel --}}
  @include('ahg-ric::_ric-panel', ['resourceId' => $storage->id])
@endsection
