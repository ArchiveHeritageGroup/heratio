{{-- heratio#1514 - storage location browse (port of AtoM storageLocation/browse) --}}
@extends('theme::layouts.1col')

@section('title', __('Storage locations'))
@section('body-class', 'browse storagelocation')

@section('content')
  <div class="d-flex align-items-center mb-3">
    <h1 class="me-3 mb-0">{{ __('Storage locations') }}</h1>
    <a href="{{ route('storagelocation.create') }}" class="btn btn-primary btn-sm">
      <i class="fas fa-plus me-1"></i>{{ __('Add location') }}
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row g-3">
    <div class="col-md-4 col-lg-3">
      <div class="card mb-3">
        <div class="card-header">{{ __('Hierarchy') }}</div>
        <div class="card-body">
          @include('ahg-storage-manage::storage-location._tree', ['tree' => $tree])
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">{{ __('Search') }}</div>
        <div class="card-body">
          <form method="get" action="{{ route('storagelocation.browse') }}" role="search">
            <label for="sl-search" class="form-label visually-hidden">{{ __('Search locations') }}</label>
            <input type="search" id="sl-search" name="search" value="{{ $search }}" class="form-control mb-2"
                   placeholder="{{ __('Search locations') }}">
            <label for="sl-type" class="form-label visually-hidden">{{ __('Location type') }}</label>
            <select id="sl-type" name="type" class="form-select mb-2">
              <option value="">{{ __('All types') }}</option>
              @foreach($types as $code => $label)
                <option value="{{ $code }}" @selected($type === $code)>{{ $label }}</option>
              @endforeach
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Search') }}</button>
            @if($search !== '' || $type !== '')
              <a href="{{ route('storagelocation.browse') }}" class="btn btn-outline-secondary btn-sm">{{ __('Clear') }}</a>
            @endif
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-8 col-lg-9">
      @if($locations->total() > 0)
        <div class="table-responsive mb-3">
          <table class="table table-bordered align-middle mb-0">
            <thead>
              <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Type') }}</th>
                <th class="text-end">{{ __('Level') }}</th>
                <th>{{ __('Capacity') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($locations as $location)
                <tr>
                  <td style="padding-left: {{ 0.5 + 1.25 * (int) $location->level }}rem;">
                    <a href="{{ route('storagelocation.show', ['slug' => $location->slug]) }}">{{ $location->name }}</a>
                  </td>
                  <td>{{ $types[$location->location_type] ?? $location->location_type }}</td>
                  <td class="text-end">{{ (int) $location->level }}</td>
                  <td class="small">
                    @if($location->capacity_value !== null)
                      {{ rtrim(rtrim(number_format((float) $location->capacity_value, 2), '0'), '.') }}
                      {{ $units[$location->capacity_unit] ?? $location->capacity_unit }}
                    @else
                      <span class="text-muted">{{ __('not set') }}</span>
                    @endif
                  </td>
                  <td class="text-end text-nowrap">
                    <a href="{{ route('storagelocation.edit', ['slug' => $location->slug]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
                    <a href="{{ route('storagelocation.confirmDelete', ['slug' => $location->slug]) }}" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{ $locations->withQueryString()->links() }}
      @else
        <p class="text-muted">
          @if($search !== '' || $type !== '')
            {{ __('No storage locations match your search.') }}
          @else
            {{ __('No storage locations yet.') }}
            <a href="{{ route('storagelocation.create') }}">{{ __('Add the first one.') }}</a>
          @endif
        </p>
      @endif
    </div>
  </div>
@endsection
