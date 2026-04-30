@extends('theme::layouts.1col')

@section('title', 'Link Physical Storage — ' . ($io->title ?? ''))
@section('body-class', 'edit physicalobject')

@section('content')

  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0"><i class="fas fa-warehouse me-2"></i>{{ $io->title ?? '[Untitled]' }}</h1>
    <span class="small text-muted">Link Physical Storage</span>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Currently linked containers --}}
  @if($linked->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header bg-warning text-dark fw-bold">
        <i class="fas fa-link me-2"></i>Linked Containers ({{ $linked->count() }})
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>{{ __('Container') }}</th>
              <th>{{ __('Location') }}</th>
              <th>{{ __('Barcode') }}</th>
              <th>{{ __('Capacity') }}</th>
              <th>{{ __('Status') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($linked as $po)
              <tr>
                <td>
                  @if($po->po_slug)
                    <a href="{{ route('physicalobject.show', $po->po_slug) }}" class="fw-bold">{{ $po->po_name ?: '[Unnamed]' }}</a>
                  @else
                    <strong>{{ $po->po_name ?: '[Unnamed]' }}</strong>
                  @endif
                </td>
                <td>
                  @php
                    $loc = array_filter([
                      $po->building,
                      $po->floor ? 'Floor ' . $po->floor : null,
                      $po->room ? 'Room ' . $po->room : null,
                      $po->aisle ? 'Aisle ' . $po->aisle : null,
                      $po->bay ? 'Bay ' . $po->bay : null,
                      $po->rack ? 'Rack ' . $po->rack : null,
                      $po->shelf ? 'Shelf ' . $po->shelf : null,
                      $po->position ? 'Pos ' . $po->position : null,
                    ]);
                  @endphp
                  @if(!empty($loc))
                    <small>{{ implode(' > ', $loc) }}</small>
                  @elseif($po->po_location)
                    <small>{{ $po->po_location }}</small>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  @if($po->barcode)
                    <code>{{ $po->barcode }}</code>
                  @else
                    -
                  @endif
                </td>
                <td>
                  @if($po->total_capacity)
                    @php
                      $used = (int)($po->used_capacity ?? 0);
                      $total = (int)$po->total_capacity;
                      $pct = $total > 0 ? round(($used / $total) * 100) : 0;
                      $barCls = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                    @endphp
                    <small>{{ $used }}/{{ $total }} {{ $po->capacity_unit ?? 'items' }}</small>
                    <div class="progress" style="height:4px;"><div class="progress-bar {{ $barCls }}" style="width:{{ $pct }}%"></div></div>
                  @else
                    -
                  @endif
                </td>
                <td>
                  @if($po->po_status && $po->po_status !== 'active')
                    <span class="badge bg-{{ $po->po_status === 'full' ? 'danger' : 'warning' }}">{{ ucfirst($po->po_status) }}</span>
                  @else
                    <span class="badge bg-success">Active</span>
                  @endif
                  @if($po->climate_controlled)<span class="badge bg-info ms-1"><i class="fas fa-thermometer-half"></i></span>@endif
                  @if($po->security_level)<span class="badge bg-dark ms-1"><i class="fas fa-lock"></i> {{ $po->security_level }}</span>@endif
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    @if($po->po_slug)
                      <a href="{{ route('physicalobject.edit', $po->po_slug) }}" class="btn btn-outline-primary" title="{{ __('Edit container') }}"><i class="fas fa-edit"></i></a>
                    @endif
                    <form method="POST" action="{{ route('physicalobject.unlink', $po->relation_id) }}" class="d-inline" onsubmit="return confirm('Unlink this container?')">
                      @csrf
                      <button type="submit" class="btn btn-outline-danger" title="{{ __('Unlink') }}"><i class="fas fa-unlink"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @else
    <div class="alert alert-info mb-4">
      <i class="fas fa-info-circle me-2"></i>No physical storage containers linked to this record.
    </div>
  @endif

  {{-- Add container --}}
  <div class="accordion mb-4" id="addContainerAccordion">

    {{-- Link existing container --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#linkExisting" aria-expanded="true">
          <i class="fas fa-link me-2"></i>Link existing container
        </button>
      </h2>
      <div id="linkExisting" class="accordion-collapse collapse show" data-bs-parent="#addContainerAccordion">
        <div class="accordion-body">
          <p class="text-muted small">Search for an existing container by name. Duplicate links will be ignored.</p>
          <form method="POST" action="{{ route('physicalobject.link-to.store', $io->slug) }}">
            @csrf
            <input type="hidden" name="action" value="link_existing">
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="form-label">{{ __('Container') }}</label>
                <input type="text" id="container-search" class="form-control" placeholder="{{ __('Type to search containers...') }}" autocomplete="off">
                <input type="hidden" name="physical_object_id" id="container-id">
                <div id="container-results" class="list-group mt-1" style="position:absolute;z-index:1000;max-height:200px;overflow-y:auto;display:none;"></div>
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn atom-btn-outline-success w-100"><i class="fas fa-link me-1"></i>Link</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Create new container --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#createNew">
          <i class="fas fa-plus me-2"></i>Or, create a new container
        </button>
      </h2>
      <div id="createNew" class="accordion-collapse collapse" data-bs-parent="#addContainerAccordion">
        <div class="accordion-body">
          <form method="POST" action="{{ route('physicalobject.link-to.store', $io->slug) }}">
            @csrf
            <input type="hidden" name="action" value="create_new">

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Container name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g. Box 12, Shelf A3') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Type') }}</label>
                <select name="type_id" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach($containerTypes as $ct)
                    <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Barcode') }}</label>
                <input type="text" name="barcode" class="form-control" placeholder="{{ __('Scan or enter') }}">
              </div>
            </div>

            <h6 class="text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i>Location</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-3">
                <label class="form-label">{{ __('Building') }}</label>
                <input type="text" name="building" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">{{ __('Floor') }}</label>
                <input type="text" name="floor" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Room') }}</label>
                <input type="text" name="room" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">{{ __('Aisle') }}</label>
                <input type="text" name="aisle" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">{{ __('Bay') }}</label>
                <input type="text" name="bay" class="form-control">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-2">
                <label class="form-label">{{ __('Rack') }}</label>
                <input type="text" name="rack" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">{{ __('Shelf') }}</label>
                <input type="text" name="shelf" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">{{ __('Position') }}</label>
                <input type="text" name="position" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Location (text)') }}</label>
                <input type="text" name="location" class="form-control" placeholder="{{ __('Free text location') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Security level') }}</label>
                <select name="security_level" class="form-select">
                  <option value="">-- None --</option>
                  <option value="public">{{ __('Public') }}</option>
                  <option value="restricted">{{ __('Restricted') }}</option>
                  <option value="confidential">{{ __('Confidential') }}</option>
                  <option value="vault">{{ __('Vault') }}</option>
                </select>
              </div>
            </div>

            <h6 class="text-muted mb-2"><i class="fas fa-box me-1"></i>Capacity</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-3">
                <label class="form-label">{{ __('Total capacity') }}</label>
                <input type="number" name="total_capacity" class="form-control" min="0">
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Unit') }}</label>
                <select name="capacity_unit" class="form-select">
                  <option value="items">{{ __('Items') }}</option>
                  <option value="boxes">{{ __('Boxes') }}</option>
                  <option value="folders">{{ __('Folders') }}</option>
                  <option value="volumes">{{ __('Volumes') }}</option>
                </select>
              </div>
              <div class="col-md-3">
                <div class="form-check mt-4">
                  <input type="checkbox" name="climate_controlled" class="form-check-input" id="climate">
                  <label class="form-check-label" for="climate"><i class="fas fa-thermometer-half me-1"></i>Climate controlled</label>
                </div>
              </div>
            </div>

            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i>Create & Link</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <section class="actions mb-3 nav gap-2">
    <li><a href="/{{ $io->slug }}" class="btn atom-btn-outline-light">Back to record</a></li>
  </section>

  <script>
  (function() {
    var search = document.getElementById('container-search');
    var results = document.getElementById('container-results');
    var hiddenId = document.getElementById('container-id');
    var timer;

    search.addEventListener('input', function() {
      clearTimeout(timer);
      var q = this.value.trim();
      if (q.length < 2) { results.style.display = 'none'; return; }

      timer = setTimeout(function() {
        fetch('{{ route("physicalobject.autocomplete") }}?query=' + encodeURIComponent(q))
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (!data.length) { results.style.display = 'none'; return; }
            results.innerHTML = '';
            data.forEach(function(item) {
              var a = document.createElement('a');
              a.href = '#';
              a.className = 'list-group-item list-group-item-action small';
              a.textContent = item.name;
              a.dataset.id = item.id;
              a.addEventListener('click', function(e) {
                e.preventDefault();
                search.value = item.name;
                hiddenId.value = item.id;
                results.style.display = 'none';
              });
              results.appendChild(a);
            });
            results.style.display = 'block';
          });
      }, 300);
    });

    document.addEventListener('click', function(e) {
      if (!results.contains(e.target) && e.target !== search) results.style.display = 'none';
    });
  })();
  </script>

@endsection
