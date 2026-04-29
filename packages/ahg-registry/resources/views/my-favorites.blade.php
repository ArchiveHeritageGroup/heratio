{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/myFavoritesSuccess.php --}}
@extends('ahg-registry::layouts.registry')

@section('title', __('My Favorites'))
@section('body-class', 'registry my-favorites')

@section('content')

@include('ahg-registry::_breadcrumb', ['items' => [
  ['label' => __('Home'), 'url' => url('/')],
  ['label' => __('Registry'), 'url' => route('registry.index')],
  ['label' => __('My Favorites')],
]])

<h1 class="h3 mb-4"><i class="fas fa-star text-warning me-2"></i>{{ __('My Favorites') }}</h1>

@php
    $institutions = $institutions ?? collect();
    $vendors = $vendors ?? collect();
    $software = $software ?? collect();
    $groups = $groups ?? collect();
    $hasAny = $institutions->isNotEmpty() || $vendors->isNotEmpty() || $software->isNotEmpty() || $groups->isNotEmpty();
@endphp

@if (! $hasAny)
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i>
    {{ __('You have not favorited anything yet. Browse the registry and click the star icon to add items to your favorites.') }}
  </div>
@endif

@if ($institutions->isNotEmpty())
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="fas fa-university me-2 text-primary"></i>{{ __('Institutions') }} <span class="badge bg-primary">{{ $institutions->count() }}</span></div>
  <div class="card-body p-0">
    <div class="list-group list-group-flush">
      @foreach ($institutions as $inst)
      @php
        $href = \Illuminate\Support\Facades\Route::has('registry.institutionView')
          ? route('registry.institutionView', ['id' => (int) $inst->id])
          : url('/registry/institution/' . $inst->id);
      @endphp
      <a href="{{ $href }}" class="list-group-item list-group-item-action">
        <div class="d-flex align-items-center">
          @if (! empty($inst->logo_path))
            <img src="{{ $inst->logo_path }}" alt="" class="rounded me-3" style="width: 40px; height: 40px; object-fit: contain;">
          @else
            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fas fa-university text-muted"></i></div>
          @endif
          <div class="flex-grow-1">
            <strong>{{ $inst->name }}</strong>
            @if (! empty($inst->is_verified))<i class="fas fa-check-circle text-primary ms-1"></i>@endif
            <div class="small text-muted">
              {{ ucfirst(str_replace('_', ' ', $inst->institution_type ?? '')) }}
              @if (! empty($inst->country))&middot; {{ $inst->country }}@endif
            </div>
          </div>
          <i class="fas fa-star text-warning"></i>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</div>
@endif

@if ($vendors->isNotEmpty())
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="fas fa-building me-2 text-success"></i>{{ __('Vendors') }} <span class="badge bg-success">{{ $vendors->count() }}</span></div>
  <div class="card-body p-0">
    <div class="list-group list-group-flush">
      @foreach ($vendors as $v)
      @php
        $href = \Illuminate\Support\Facades\Route::has('registry.vendorView')
          ? route('registry.vendorView', ['id' => (int) $v->id])
          : url('/registry/vendor/' . $v->id);
        $rawVt = $v->vendor_type ?? '[]';
        $vtArr = is_string($rawVt) ? (json_decode($rawVt, true) ?: []) : (is_array($rawVt) ? $rawVt : []);
      @endphp
      <a href="{{ $href }}" class="list-group-item list-group-item-action">
        <div class="d-flex align-items-center">
          @if (! empty($v->logo_path))
            <img src="{{ $v->logo_path }}" alt="" class="rounded me-3" style="width: 40px; height: 40px; object-fit: contain;">
          @else
            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fas fa-building text-muted"></i></div>
          @endif
          <div class="flex-grow-1">
            <strong>{{ $v->name }}</strong>
            @if (! empty($v->is_verified))<i class="fas fa-check-circle text-primary ms-1"></i>@endif
            <div class="small text-muted">{{ implode(', ', array_map(fn ($t) => ucfirst(str_replace('_', ' ', $t)), $vtArr)) }}</div>
          </div>
          <i class="fas fa-star text-warning"></i>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</div>
@endif

@if ($software->isNotEmpty())
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="fas fa-cube me-2 text-info"></i>{{ __('Software') }} <span class="badge bg-info">{{ $software->count() }}</span></div>
  <div class="card-body p-0">
    <div class="list-group list-group-flush">
      @foreach ($software as $sw)
      @php
        $href = \Illuminate\Support\Facades\Route::has('registry.softwareView')
          ? route('registry.softwareView', ['id' => (int) $sw->id])
          : url('/registry/software/' . $sw->id);
        $rawCat = $sw->category ?? '';
        $catList = '' !== (string) $rawCat
          ? (is_array($d = json_decode((string) $rawCat, true)) ? $d : [(string) $rawCat])
          : [];
      @endphp
      <a href="{{ $href }}" class="list-group-item list-group-item-action">
        <div class="d-flex align-items-center">
          @if (! empty($sw->logo_path))
            <img src="{{ $sw->logo_path }}" alt="" class="rounded me-3" style="width: 40px; height: 40px; object-fit: contain;">
          @else
            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fas fa-cube text-muted"></i></div>
          @endif
          <div class="flex-grow-1">
            <strong>{{ $sw->name }}</strong>
            <div class="small text-muted">{{ implode(', ', array_map('strtoupper', $catList)) }}</div>
          </div>
          <i class="fas fa-star text-warning"></i>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</div>
@endif

@if ($groups->isNotEmpty())
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="fas fa-users me-2 text-warning"></i>{{ __('User Groups') }} <span class="badge bg-warning text-dark">{{ $groups->count() }}</span></div>
  <div class="card-body p-0">
    <div class="list-group list-group-flush">
      @foreach ($groups as $g)
      @php
        $href = \Illuminate\Support\Facades\Route::has('registry.groupView')
          ? route('registry.groupView', ['id' => (int) $g->id])
          : url('/registry/group/' . $g->id);
      @endphp
      <a href="{{ $href }}" class="list-group-item list-group-item-action">
        <div class="d-flex align-items-center">
          <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fas fa-users text-muted"></i></div>
          <div class="flex-grow-1">
            <strong>{{ $g->name }}</strong>
            <div class="small text-muted">{{ (int) ($g->member_count ?? 0) }} {{ __('members') }}</div>
          </div>
          <i class="fas fa-star text-warning"></i>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</div>
@endif

@endsection
