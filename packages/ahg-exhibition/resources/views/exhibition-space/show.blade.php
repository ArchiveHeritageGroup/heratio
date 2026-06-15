{{-- heratio#146 — Exhibition space show + placement timeline --}}
@extends('theme::layouts.1col')

@section('title', $space->name)
@section('body-class', 'show exhibition-space')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-3 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-palette me-2"></i>{{ $space->name }}
      <span class="badge bg-secondary ms-2">{{ ucwords(str_replace('_', ' ', $space->space_type)) }}</span>
    </h1>
    @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'show'])
    @auth
      <a href="{{ route('exhibition-space.edit', ['slug' => $space->slug]) }}" class="btn btn-outline-primary"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</a>
      @if(Route::has('exhibition-space.sync-ric'))
        <form method="POST" action="{{ route('exhibition-space.sync-ric', ['slug' => $space->slug]) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-outline-primary" title="{{ __('Publish this space into the RiC knowledge graph as an Activity, linked to its objects') }}"><i class="fas fa-diagram-project me-1"></i>{{ __('Publish to RiC graph') }}</button>
        </form>
      @endif
      <a href="{{ route('exhibition-space.confirmDelete', ['slug' => $space->slug]) }}" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</a>
    @endauth
  </div>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  {{-- heratio#1186 - AI-generated exhibition introduction (generative exhibitions) --}}
  @if(!empty($space->intro_text))
    <div class="card mb-3">
      <div class="card-header"><strong>{{ __('Exhibition introduction') }}</strong></div>
      <div class="card-body"><p class="mb-0">{{ $space->intro_text }}</p></div>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Location') }}</strong></div>
        <div class="card-body">
          <p class="mb-1"><small class="text-muted">{{ __('Building') }}</small><br>{{ $space->building ?: '—' }}</p>
          <p class="mb-1"><small class="text-muted">{{ __('Floor') }}</small><br>{{ $space->floor ?: '—' }}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Capacity') }}</strong></div>
        <div class="card-body">
          @if($space->capacity_value !== null)
            <p class="mb-1">{{ (float) $space->capacity_value }} {{ __($capacityUnits[$space->capacity_unit] ?? $space->capacity_unit) }}</p>
          @else
            <p class="text-muted">{{ __('No capacity set') }}</p>
          @endif
          @if($space->lighting_lux_target !== null)
            <p class="mb-0 small text-muted">{{ __('Lighting target: :n lux', ['n' => (float) $space->lighting_lux_target]) }}</p>
          @endif
        </div>
      </div>
      @if(!empty($space->notes))
        <div class="card mb-3"><div class="card-header"><strong>{{ __('Notes') }}</strong></div><div class="card-body"><p class="mb-0">{{ $space->notes }}</p></div></div>
      @endif
      {{-- heratio#1186 - building rooms + their AI-generated blurbs (generative exhibitions) --}}
      @php
        $buildingRooms = !empty($space->building_id)
          ? \Illuminate\Support\Facades\DB::table('ahg_exhibition_space')
              ->where('building_id', $space->building_id)
              ->orderBy('floor_level')->orderBy('building_seq')->orderBy('id')
              ->get(['name', 'room_blurb'])
          : collect();
      @endphp
      @if($buildingRooms->count() > 1)
        <div class="card mb-3">
          <div class="card-header"><strong>{{ __('Rooms') }}</strong></div>
          <ul class="list-group list-group-flush">
            @foreach($buildingRooms as $r)
              <li class="list-group-item">
                <strong>{{ $r->name }}</strong>
                @if(!empty($r->room_blurb))<br><small class="text-muted">{{ $r->room_blurb }}</small>@endif
              </li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>{{ __('Placements') }}</strong>
          <small class="text-muted">{{ count($placements) }} {{ __('total') }}</small>
        </div>
        <div class="card-body p-0">
          @if(count($placements) === 0)
            <div class="p-3 text-muted small">{{ __('No placements yet.') }}</div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Information object') }}</th>
                    <th>{{ __('Units') }}</th>
                    <th>{{ __('Starts') }}</th>
                    <th>{{ __('Ends') }}</th>
                    <th>{{ __('Notes') }}</th>
                    @auth <th></th> @endauth
                  </tr>
                </thead>
                <tbody>
                  @foreach($placements as $p)
                    <tr>
                      <td>
                        @if(!empty($p->is_remote))
                          {{-- #1277 federated twin: a borrowed peer object (no local record) --}}
                          @if(!empty($p->record_url))
                            <a href="{{ $p->record_url }}" target="_blank" rel="noopener">{{ $p->information_object_title }}</a>
                          @else
                            {{ $p->information_object_title }}
                          @endif
                          <span class="badge bg-primary ms-1"><i class="fas fa-people-arrows me-1"></i>{{ $p->remote_peer ? __('Courtesy of').' '.$p->remote_peer : __('Borrowed') }}</span>
                        @elseif(!empty($p->information_object_title))
                          {{ $p->information_object_title }} <small class="text-muted">#{{ $p->information_object_id }}</small>
                        @else
                          <span class="text-muted">{{ __('Object') }} #{{ $p->information_object_id }}</span>
                        @endif
                      </td>
                      <td>{{ (float) $p->size_units_used }}</td>
                      <td>{{ $p->starts_at ?: '—' }}</td>
                      <td>{{ $p->ends_at ?: '—' }}</td>
                      <td><small class="text-muted">{{ $p->notes }}</small></td>
                      @auth
                      <td>
                        <form method="POST" action="{{ route('exhibition-space.placement.remove', $p->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove this placement?') }}');">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                        </form>
                      </td>
                      @endauth
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

      @auth
      <div class="card mt-3">
        <div class="card-header"><strong>{{ __('Add a placement') }}</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('exhibition-space.place', ['slug' => $space->slug]) }}">
            @csrf
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label for="information_object_id" class="form-label small">{{ __('Information object') }} <span class="text-danger">*</span></label>
                <select name="information_object_id" id="information_object_id" class="form-control form-control-sm" required>
                  <option value="">{{ __('Type to search...') }}</option>
                </select>
              </div>
              <div class="col-md-2">
                <label for="size_units_used" class="form-label small">{{ __('Units used') }}</label>
                <input type="number" name="size_units_used" id="size_units_used" class="form-control form-control-sm" min="0" step="0.01" value="0">
              </div>
              <div class="col-md-2">
                <label for="starts_at" class="form-label small">{{ __('Starts') }}</label>
                <input type="date" name="starts_at" id="starts_at" class="form-control form-control-sm">
              </div>
              <div class="col-md-2">
                <label for="ends_at" class="form-label small">{{ __('Ends') }}</label>
                <input type="date" name="ends_at" id="ends_at" class="form-control form-control-sm">
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>{{ __('Place') }}</button>
              </div>
            </div>
            <div class="mt-2">
              <input type="text" name="notes" class="form-control form-control-sm" placeholder="{{ __('Optional notes') }}">
            </div>
          </form>
        </div>
      </div>
      @endauth
    </div>
  </div>

  {{-- TomSelect lookup for the placement "Information object" field (heratio#146).
       Searches information_object via the shared informationobject/autocomplete
       endpoint and submits the selected object id. --}}
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('information_object_id');
    if (!el || typeof TomSelect === 'undefined') return;
    new TomSelect(el, {
      valueField: 'id', labelField: 'name', searchField: ['name'],
      placeholder: '{{ __('Type to search information objects...') }}',
      maxItems: 1, maxOptions: 15,
      load: function (query, callback) {
        if (query.length < 2) return callback();
        fetch('{{ url('informationobject/autocomplete') }}?query=' + encodeURIComponent(query) + '&limit=15')
          .then(function (r) { return r.json(); })
          .then(function (data) { callback(data); })
          .catch(function () { callback(); });
      },
      render: {
        option: function (d, escape) {
          return '<div>' + escape(d.name) + ' <small class="text-muted">#' + escape(d.id) + (d.slug ? ' ' + escape(d.slug) : '') + '</small></div>';
        },
        item: function (d, escape) {
          return '<div>' + escape(d.name) + ' <small class="text-muted">#' + escape(d.id) + '</small></div>';
        }
      }
    });
  });
  </script>
@endsection
