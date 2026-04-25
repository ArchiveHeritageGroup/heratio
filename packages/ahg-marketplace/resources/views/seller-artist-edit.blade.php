{{--
  Broker artist create/edit form. $artist is null when creating.
--}}
@extends('theme::layouts.1col')

@section('title', $artist ? __('Edit Artist') : __('Add Artist'))
@section('body-class', 'marketplace seller-artist-edit')

@section('content')

@php
  $isEdit = (bool) $artist;
  $a = $artist ?? (object) [];
  $action = $isEdit
    ? route('ahgmarketplace.seller-artist-edit.post')
    : route('ahgmarketplace.seller-artist-create.post');
@endphp

<h1 class="mb-4">
  <i class="fas fa-{{ $isEdit ? 'user-edit' : 'user-plus' }} me-2 text-primary"></i>
  {{ $isEdit ? __('Edit artist') : __('Add a new artist') }}
</h1>

<form method="POST" action="{{ $action }}">
  @csrf
  @if($isEdit)
    <input type="hidden" name="id" value="{{ $a->id }}">
  @endif

  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="fas fa-id-card me-1"></i> {{ __('Identity') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">{{ __('Display name') }} *</label>
          <input type="text" name="display_name" class="form-control" required
                 value="{{ old('display_name', $a->display_name ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Nationality') }}</label>
          <input type="text" name="nationality" class="form-control"
                 value="{{ old('nationality', $a->nationality ?? '') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Birth year') }}</label>
          <input type="number" name="birth_year" class="form-control" min="1000" max="2200"
                 value="{{ old('birth_year', $a->birth_year ?? '') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Death year') }}</label>
          <input type="number" name="death_year" class="form-control" min="1000" max="2200"
                 value="{{ old('death_year', $a->death_year ?? '') }}">
        </div>
        <div class="col-md-12">
          <label class="form-label">{{ __('Bio') }}</label>
          <textarea name="bio" class="form-control" rows="4">{{ old('bio', $a->bio ?? '') }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="fas fa-address-book me-1"></i> {{ __('Contact (private — not shown publicly)') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">{{ __('Email') }}</label>
          <input type="email" name="contact_email" class="form-control"
                 value="{{ old('contact_email', $a->contact_email ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Phone') }}</label>
          <input type="text" name="contact_phone" class="form-control"
                 value="{{ old('contact_phone', $a->contact_phone ?? '') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Website') }}</label>
          <input type="url" name="website" class="form-control"
                 value="{{ old('website', $a->website ?? '') }}">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="fas fa-coins me-1"></i> {{ __('Default pricing rules') }}</div>
    <div class="card-body">
      <p class="small text-muted">
        {{ __('These defaults pre-fill your listing form when you assign this artist. You can override on each listing.') }}
      </p>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">{{ __('Markup type') }}</label>
          <select name="default_markup_type" class="form-select">
            @php $mt = old('default_markup_type', $a->default_markup_type ?? 'percentage'); @endphp
            <option value="percentage" {{ $mt === 'percentage' ? 'selected' : '' }}>{{ __('Percentage (%)') }}</option>
            <option value="fixed"      {{ $mt === 'fixed'      ? 'selected' : '' }}>{{ __('Fixed amount') }}</option>
            <option value="none"       {{ $mt === 'none'       ? 'selected' : '' }}>{{ __('None (sell at base)') }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Markup value') }}</label>
          <input type="number" step="0.01" min="0" name="default_markup_value" class="form-control"
                 value="{{ old('default_markup_value', $a->default_markup_value ?? '30.00') }}">
          <small class="text-muted">{{ __('Percentage (e.g. 30 = 30%) or fixed amount in your listing currency.') }}</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Commission split (artist %)') }}</label>
          <input type="number" step="0.01" min="0" max="100" name="default_commission_split" class="form-control"
                 value="{{ old('default_commission_split', $a->default_commission_split ?? '') }}"
                 placeholder="e.g. 70">
          <small class="text-muted">{{ __('Optional — what % of net revenue the artist receives.') }}</small>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-bold"><i class="fas fa-sticky-note me-1"></i> {{ __('Internal notes') }}</div>
    <div class="card-body">
      <textarea name="notes" class="form-control" rows="3"
                placeholder="{{ __('Private notes (representation agreement, payment terms, etc.)') }}">{{ old('notes', $a->notes ?? '') }}</textarea>
    </div>
  </div>

  @if($isEdit)
    <div class="mb-3">
      <label class="form-label">{{ __('Status') }}</label>
      <select name="status" class="form-select" style="max-width: 200px;">
        @php $st = old('status', $a->status ?? 'active'); @endphp
        <option value="active"   {{ $st === 'active'   ? 'selected' : '' }}>{{ __('Active') }}</option>
        <option value="inactive" {{ $st === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
      </select>
    </div>
  @endif

  <div class="d-flex justify-content-between">
    <a href="{{ route('ahgmarketplace.seller-artists') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Cancel') }}
    </a>
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i> {{ $isEdit ? __('Save changes') : __('Add artist') }}
    </button>
  </div>
</form>

@endsection
