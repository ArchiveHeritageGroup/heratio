{{-- heratio#1514 - storage location show (port of AtoM storageLocation/view) --}}
@extends('theme::layouts.1col')

@section('title', $location->name)
@section('body-class', 'show storagelocation')

@section('content')
  <div class="d-flex flex-wrap align-items-center mb-2">
    <h1 class="me-3 mb-0">{{ $location->name }}</h1>
    <a href="{{ route('storagelocation.edit', ['slug' => $location->slug]) }}" class="btn btn-outline-primary btn-sm me-2">
      <i class="fas fa-pencil-alt me-1"></i>{{ __('Edit') }}
    </a>
    <a href="{{ route('storagelocation.confirmDelete', ['slug' => $location->slug]) }}" class="btn btn-outline-danger btn-sm me-2">
      <i class="fas fa-trash me-1"></i>{{ __('Delete') }}
    </a>
    <a href="{{ route('storagelocation.browse') }}" class="btn btn-link btn-sm">&laquo; {{ __('Back to storage locations') }}</a>
  </div>

  <div class="mb-3">@include('ahg-storage-manage::storage-location._path', ['path' => $path])</div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-md-7">
      <div class="card h-100">
        <div class="card-header">{{ __('Details') }}</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4">{{ __('Type') }}</dt>
            <dd class="col-sm-8">{{ $types[$location->location_type] ?? $location->location_type }}</dd>

            <dt class="col-sm-4">{{ __('Level') }}</dt>
            <dd class="col-sm-8">{{ (int) $location->level }}</dd>

            <dt class="col-sm-4">{{ __('Slug') }}</dt>
            <dd class="col-sm-8"><code>{{ $location->slug }}</code></dd>

            <dt class="col-sm-4">{{ __('Description') }}</dt>
            <dd class="col-sm-8" style="white-space: pre-wrap;">{{ $location->description ?: '-' }}</dd>

            <dt class="col-sm-4">{{ __('Capacity') }}</dt>
            <dd class="col-sm-8">
              @if($location->capacity_value !== null)
                {{ rtrim(rtrim(number_format((float) $location->capacity_value, 2), '0'), '.') }}
                {{ $units[$location->capacity_unit] ?? $location->capacity_unit }}
              @else
                -
              @endif
            </dd>

            <dt class="col-sm-4">{{ __('Notes') }}</dt>
            <dd class="col-sm-8" style="white-space: pre-wrap;">{{ $location->notes ?: '-' }}</dd>

            <dt class="col-sm-4">{{ __('Created') }}</dt>
            <dd class="col-sm-8">{{ $location->created_at ?? '-' }}</dd>

            <dt class="col-sm-4">{{ __('Updated') }}</dt>
            <dd class="col-sm-8">{{ $location->updated_at ?? '-' }}</dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      <div class="card h-100">
        <div class="card-header">{{ __('Contents') }}</div>
        <div class="card-body">
          @if(count($subtree) > 0)
            @include('ahg-storage-manage::storage-location._tree', ['tree' => $subtree])
          @else
            <p class="text-muted mb-0">{{ __('Nothing is stored under this location yet.') }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- heratio#1528 - capacity declared, and what is held, counted down the tree.
       Per unit and never added across units, and the figure declared here is
       never added to the one declared beneath: a room and its shelves may both
       describe the same space. No percentage full: nothing records how much of a
       shelf a box takes up. --}}
  @php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
  @endphp
  <div class="card mb-3">
    <div class="card-header">{{ __('Capacity and contents') }}</div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-4">{{ __('Objects held here') }}</dt>
        <dd class="col-sm-8">{{ $rollup['objects_here'] }}</dd>

        @if(count($descendants) > 0)
          <dt class="col-sm-4">{{ __('Objects in locations beneath') }}</dt>
          <dd class="col-sm-8">{{ $rollup['objects_beneath'] }}</dd>

          <dt class="col-sm-4">{{ __('Objects in all') }}</dt>
          <dd class="col-sm-8"><strong>{{ $rollup['objects_total'] }}</strong></dd>
        @endif

        @if($rollup['own'])
          <dt class="col-sm-4">{{ __('Capacity declared here') }}</dt>
          <dd class="col-sm-8">{{ $fmt($rollup['own']['value']) }} {{ $units[$rollup['own']['unit']] ?? str_replace('_', ' ', $rollup['own']['unit']) }}</dd>
        @endif

        @if(count($rollup['beneath']) > 0)
          <dt class="col-sm-4">{{ __('Capacity declared beneath') }}</dt>
          <dd class="col-sm-8">
            @foreach($rollup['beneath'] as $unit => $total)
              <div>{{ $fmt($total) }} {{ (string) $unit === '' ? __('(no unit given)') : ($units[$unit] ?? str_replace('_', ' ', $unit)) }}</div>
            @endforeach
            <small class="text-muted">{{ __('Summed over :count location(s) that declare a capacity. Not added to the figure declared here: a room and its shelves may both describe the same space.', ['count' => $rollup['declared_beneath']]) }}</small>
          </dd>
        @endif
      </dl>
    </div>
  </div>

  @if(count($children) > 0)
    <div class="card mb-3">
      <div class="card-header">{{ __('Child locations') }} <span class="badge bg-secondary">{{ count($children) }}</span></div>
      <div class="card-body">
        @include('ahg-storage-manage::storage-location._list', ['rows' => $children])
      </div>
    </div>
  @endif

  @if(count($descendants) > count($children))
    <div class="card mb-3">
      <div class="card-header">{{ __('All descendants') }} <span class="badge bg-secondary">{{ count($descendants) }}</span></div>
      <div class="card-body">
        @include('ahg-storage-manage::storage-location._list', ['rows' => $descendants, 'indent' => true, 'baseLevel' => (int) $location->level + 1])
      </div>
    </div>
  @endif

  {{-- Objects held here, and moving them on. Started from the location rather
       than the object, because emptying a shelf is the task people actually have
       (heratio#1514). --}}
  <div class="card mb-3">
    <div class="card-header">{{ __('Objects here') }} <span class="badge bg-secondary">{{ count($objects) }}</span></div>
    <div class="card-body">
      @if(count($objects) === 0)
        <p class="text-muted mb-0">{{ __('No physical objects are in this location.') }}</p>
      @else
        <form method="POST" action="{{ route('storagelocation.move-objects', $location->slug) }}">
          @csrf
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="width:2rem">
                  <input type="checkbox" class="form-check-input" id="checkAll" aria-label="{{ __('Select all') }}">
                </th>
                <th>{{ __('Object') }}</th>
                <th>{{ __('Placed') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($objects as $object)
                <tr>
                  <td>
                    <input type="checkbox" class="form-check-input row-check" name="object_ids[]" value="{{ $object['physical_object_id'] }}"
                           aria-label="{{ $object['name'] ?? __('Object') }}">
                  </td>
                  <td>{{ $object['name'] ?? '#'.$object['physical_object_id'] }}</td>
                  <td class="text-muted">{{ $object['updated_at'] ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>

          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label" for="to_location_id">{{ __('Move selected to') }}</label>
              <select class="form-select @error('to_location_id') is-invalid @enderror" id="to_location_id" name="to_location_id">
                <option value="">{{ __('Choose a location') }}</option>
                @foreach($locations as $option)
                  @continue((int) $option->id === (int) $location->id)
                  <option value="{{ $option->id }}">{{ str_repeat('- ', (int) $option->level).$option->name }}</option>
                @endforeach
              </select>
              @error('to_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label class="form-label" for="bulk_note">{{ __('Note') }}</label>
              <input type="text" class="form-control" id="bulk_note" name="note" maxlength="2000"
                     placeholder="{{ __('Why they moved (optional)') }}">
            </div>
            <div class="col-md-3">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="bulk_remove" name="remove_from_storage" value="1">
                <label class="form-check-label" for="bulk_remove">{{ __('Remove from storage') }}</label>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-dolly me-1"></i>{{ __('Move selected') }}
              </button>
            </div>
          </div>
        </form>
      @endif
    </div>
  </div>

  {{-- heratio#1528 - what sits in the locations beneath this one, with the
       location each object is actually in. A carton or a pallet is a location,
       so this is how "what is on this pallet" reads. --}}
  @if(count($beneath) > 0)
    <div class="card mb-3">
      <div class="card-header">{{ __('Objects in locations beneath this one') }} <span class="badge bg-secondary">{{ count($beneath) }}</span></div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>{{ __('Object') }}</th>
              <th>{{ __('In') }}</th>
              <th>{{ __('Type') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($beneath as $object)
              <tr>
                <td>{{ $object['name'] ?? '#'.$object['physical_object_id'] }}</td>
                <td><a href="{{ route('storagelocation.show', $object['location_slug']) }}">{{ $object['location_name'] }}</a></td>
                <td>{{ $types[$object['location_type']] ?? $object['location_type'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- heratio#1528 - give objects that have no place yet this one. One batch of
       first placements in the movement log. Editors and administrators. --}}
  @if($canPlace && ($unplaced['total'] > 0 || $unplacedSearch !== ''))
    <div class="card mb-3">
      <div class="card-header">{{ __('Place objects here') }} <span class="badge bg-secondary">{{ __(':count without a place', ['count' => $unplaced['total']]) }}</span></div>
      <div class="card-body">
        <form method="GET" action="{{ route('storagelocation.show', $location->slug) }}" class="row g-2 mb-3">
          <div class="col-md-9">
            <label class="visually-hidden" for="unplaced-q">{{ __('Find an object by name') }}</label>
            <input class="form-control" type="text" id="unplaced-q" name="q" value="{{ $unplacedSearch }}"
                   placeholder="{{ __('Find an object by name') }}">
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-outline-secondary" type="submit">{{ __('Find') }}</button>
          </div>
        </form>

        @if(count($unplaced['rows']) === 0)
          <p class="mb-0 text-muted">{{ __('No object without a place matches that.') }}</p>
        @else
          <form method="POST" action="{{ route('storagelocation.place-objects', $location->slug) }}">
            @csrf
            @if($unplaced['total'] > count($unplaced['rows']))
              <p class="text-muted">{{ __('Showing the first :shown of :total. Search to narrow the list.', ['shown' => count($unplaced['rows']), 'total' => $unplaced['total']]) }}</p>
            @endif
            @error('object_ids')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="row mb-3">
              @foreach($unplaced['rows'] as $object)
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="object_ids[]"
                           value="{{ $object['physical_object_id'] }}" id="place-{{ $object['physical_object_id'] }}">
                    <label class="form-check-label" for="place-{{ $object['physical_object_id'] }}">
                      {{ $object['name'] ?? '#'.$object['physical_object_id'] }}
                    </label>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="row g-2 align-items-end">
              <div class="col-md-9">
                <label class="form-label" for="place-note">{{ __('Note') }}</label>
                <input class="form-control" type="text" id="place-note" name="note" maxlength="2000"
                       placeholder="{{ __('Why it is here, for the record (optional)') }}">
              </div>
              <div class="col-md-3 d-grid">
                <button class="btn btn-primary" type="submit"><i class="fas fa-box me-1"></i>{{ __('Place here') }}</button>
              </div>
            </div>
          </form>
        @endif
      </div>
    </div>
  @endif

  {{-- The record of what came and went. Append-only: a wrong move is corrected
       by another move, never by editing this list. --}}
  <div class="card mb-3">
    <div class="card-header">{{ __('Movement history') }} <span class="badge bg-secondary">{{ count($movements) }}</span></div>
    <div class="card-body">
      @if(count($movements) === 0)
        <p class="text-muted mb-0">{{ __('Nothing has moved into or out of this location yet.') }}</p>
      @else
        <table class="table table-sm">
          <thead>
            <tr>
              <th>{{ __('When') }}</th>
              <th>{{ __('What') }}</th>
              <th>{{ __('From') }}</th>
              <th>{{ __('To') }}</th>
              <th>{{ __('By') }}</th>
              <th>{{ __('Note') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($movements as $move)
              <tr>
                <td class="text-nowrap">{{ $move['moved_at'] }}</td>
                <td>
                  {{ $move['subject_name'] ?? '#'.$move['subject_id'] }}
                  @if($move['subject_type'] === 'storage_location')
                    <span class="badge bg-light text-dark">{{ __('location') }}</span>
                  @endif
                </td>
                {{-- A null side reads differently per subject: for an object it is
                     storage itself, for a location it is the top of the tree. --}}
                <td>{{ $move['from_location_name'] ?? ($move['subject_type'] === 'storage_location' ? __('Root') : __('Not in storage')) }}</td>
                <td>{{ $move['to_location_name'] ?? ($move['subject_type'] === 'storage_location' ? __('Root') : __('Removed from storage')) }}</td>
                <td>{{ $move['username'] ?? '-' }}</td>
                <td>{{ $move['note'] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>

  <a href="{{ route('storagelocation.create', ['parent_id' => $location->id]) }}" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i>{{ __('Add child location') }}
  </a>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkAll = document.getElementById('checkAll');
    var rowChecks = document.querySelectorAll('.row-check');

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowChecks.forEach(function (cb) { cb.checked = checkAll.checked; });
        });
    }

    // Ticking "remove from storage" makes the destination meaningless, so the
    // select is disabled rather than left to look like it still applies.
    var remove = document.getElementById('bulk_remove');
    var destination = document.getElementById('to_location_id');
    if (remove && destination) {
        remove.addEventListener('change', function () {
            destination.disabled = remove.checked;
            if (remove.checked) { destination.value = ''; }
        });
    }
});
</script>
@endpush
