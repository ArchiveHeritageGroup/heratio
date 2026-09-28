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

  <a href="{{ route('storagelocation.create', ['parent_id' => $location->id]) }}" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i>{{ __('Add child location') }}
  </a>
@endsection
