@extends('theme::layouts.master')

@section('title', 'Bulk Set Object Types')
@section('body-class', 'admin display bulk-set-type')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('glam.index') }}">Display Configuration</a></li>
  <li class="breadcrumb-item active" aria-current="page">Bulk Set Types</li>
@endsection

@section('layout-content')
@php
  if (!function_exists('getTypeIcon')) {
    function getTypeIcon($type) {
      return match($type) {
        'archive' => 'fa-archive',
        'museum'  => 'fa-landmark',
        'gallery' => 'fa-palette',
        'library' => 'fa-book',
        'dam'     => 'fa-images',
        default   => 'fa-globe',
      };
    }
  }
  if (!function_exists('getTypeColor')) {
    function getTypeColor($type) {
      return match($type) {
        'archive' => 'success',
        'museum'  => 'warning',
        'gallery' => 'info',
        'library' => 'primary',
        'dam'     => 'danger',
        default   => 'secondary',
      };
    }
  }
@endphp

<div id="main-column" role="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-tags me-3 text-primary" aria-hidden="true"></i>
      <div>
        <h1 class="mb-0">Bulk Set Object Types</h1>
        <span class="small text-muted">Assign a collection type to all objects in a collection</span>
      </div>
    </div>
    <a href="{{ route('glam.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row">
    {{-- Main form --}}
    <div class="col-md-8">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Set Object Type</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('glam.bulk.set.type') }}">
            @csrf

            {{-- Collection select --}}
            <div class="mb-4">
              <label for="collection_id" class="form-label"><strong>Select Collection</strong></label>
              <select name="collection_id" id="collection_id" class="form-select" required>
                <option value="">-- Choose a collection --</option>
                @if(!empty($collections))
                  @foreach($collections as $collection)
                    <option value="{{ $collection->id }}" {{ old('collection_id') == $collection->id ? 'selected' : '' }}>
                      {{ $collection->title ?? $collection->name ?? 'Collection #' . $collection->id }}
                    </option>
                  @endforeach
                @endif
              </select>
              @error('collection_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            {{-- Type radio buttons --}}
            <div class="mb-4">
              <label class="form-label"><strong>Object Type</strong></label>
              @if(!empty($collectionTypes) && count($collectionTypes))
                <div class="row g-3">
                  @foreach($collectionTypes as $type)
                    @php
                      $typeCode = $type->code ?? $type->name ?? '';
                      $icon = getTypeIcon($typeCode);
                      $color = getTypeColor($typeCode);
                    @endphp
                    <div class="col-6 col-lg-4">
                      <div class="form-check card h-100 p-3 {{ old('type') === $typeCode ? 'border-' . $color . ' bg-light' : '' }}">
                        <input class="form-check-input" type="radio" name="type" id="type_{{ $typeCode }}"
                               value="{{ $typeCode }}" {{ old('type') === $typeCode ? 'checked' : '' }} required>
                        <label class="form-check-label d-block text-center mt-2" for="type_{{ $typeCode }}">
                          <i class="fas {{ $icon }} fa-2x text-{{ $color }} d-block mb-2"></i>
                          <strong>{{ $type->name ?? ucfirst($typeCode) }}</strong>
                          @if(!empty($type->description))
                            <small class="d-block text-muted mt-1">{{ $type->description }}</small>
                          @endif
                        </label>
                      </div>
                    </div>
                  @endforeach
                </div>
              @else
                <div class="alert alert-warning mb-0">
                  <i class="fas fa-exclamation-triangle me-2"></i>No collection types defined.
                </div>
              @endif
              @error('type')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            {{-- Submit --}}
            <div class="d-flex gap-2">
              <button type="submit" class="btn atom-btn-outline-success">
                <i class="fas fa-check me-1"></i> Apply Type
              </button>
              <a href="{{ route('glam.index') }}" class="btn atom-btn-white">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Info sidebar --}}
    <div class="col-md-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Object Types</h5>
        </div>
        <div class="card-body">
          <p>Object types determine how items are displayed and categorised within the system.</p>
          <p>Each type has its own display profile, field mappings, and level hierarchy.</p>
          <hr>
          <h6>Available types:</h6>
          <ul class="list-unstyled mb-0">
            @if(!empty($collectionTypes))
              @foreach($collectionTypes as $type)
                @php
                  $typeCode = $type->code ?? $type->name ?? '';
                @endphp
                <li class="mb-2">
                  <i class="fas {{ getTypeIcon($typeCode) }} text-{{ getTypeColor($typeCode) }} me-2"></i>
                  <strong>{{ $type->name ?? ucfirst($typeCode) }}</strong>
                  @if(!empty($type->description))
                    <br><small class="text-muted ms-4">{{ $type->description }}</small>
                  @endif
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
