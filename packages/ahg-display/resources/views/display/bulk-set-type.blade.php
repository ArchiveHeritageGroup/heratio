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
          <h5 class="mb-0">Select Collection and Type</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('glam.bulk.set.type') }}">
            @csrf

            {{-- Collection select --}}
            <div class="mb-4">
              <label class="form-label"><strong>Top-Level Collection *</strong></label>
              <select name="collection_id" id="collection_id" class="form-select" required>
                <option value="">Select collection...</option>
                @if(!empty($collections))
                  @foreach($collections as $collection)
                    <option value="{{ $collection->id }}" {{ old('collection_id') == $collection->id ? 'selected' : '' }}>
                      {{ !empty($collection->identifier) ? $collection->identifier . ' - ' : '' }}{{ $collection->title ?? $collection->name ?? 'Untitled' }}
                    </option>
                  @endforeach
                @endif
              </select>
              <small class="text-muted">This will set the type for this collection and ALL descendants</small>
              @error('collection_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            {{-- Type radio buttons --}}
            <div class="mb-4">
              <label class="form-label"><strong>Object Type *</strong></label>
              @if(!empty($collectionTypes) && count($collectionTypes))
                <div class="row">
                  @foreach($collectionTypes as $type)
                    @php
                      $typeCode = $type->code ?? $type->name ?? '';
                      $icon = getTypeIcon($typeCode);
                    @endphp
                    <div class="col-md-4 mb-2">
                      <div class="form-check">
                        <input type="radio" name="type" value="{{ $typeCode }}"
                               class="form-check-input" id="type_{{ $typeCode }}"
                               {{ old('type') === $typeCode ? 'checked' : '' }} required>
                        <label class="form-check-label" for="type_{{ $typeCode }}">
                          <i class="fas {{ $icon }} me-1"></i>
                          {{ $type->name ?? ucfirst($typeCode) }}
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

            <hr>

            {{-- Submit --}}
            <div class="d-flex justify-content-between">
              <a href="{{ route('glam.index') }}" class="btn atom-btn-white">Cancel</a>
              <button type="submit" class="btn atom-btn-outline-success" onclick="return confirm('This will update ALL objects in this collection. Continue?')">
                <i class="fas fa-save me-1"></i> Apply to Collection
              </button>
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
          <p>Object types determine how records are displayed:</p>
          <ul class="small">
            <li><strong>Archive:</strong> ISAD(G) hierarchical view</li>
            <li><strong>Museum:</strong> Spectrum object records</li>
            <li><strong>Gallery:</strong> Artwork/artist focus</li>
            <li><strong>Book Collection:</strong> Bibliographic view</li>
            <li><strong>Photo Archive:</strong> Visual grid/lightbox</li>
            <li><strong>Audiovisual:</strong> Media player focus</li>
          </ul>
          <p class="text-muted small mb-0">
            Types are inherited by children. Setting a type on a fonds will apply to all series, files, and items within.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
