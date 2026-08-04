@extends('theme::layouts.1col')
@section('title', __('Edit Heritage Asset'))
@section('body-class', 'admin heritage')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0"><i class="fas fa-pencil-alt me-2"></i>{{ __('Edit Heritage Asset') }}</h1>
  <div class="d-flex gap-2">
    @if(isset($asset) && Route::has('heritage.accounting.view'))
      <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
    @endif
    @if(Route::has('heritage.accounting.browse'))
      <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>{{ __('All assets') }}</a>
    @endif
  </div>
</div>

<form method="post" action="{{ $formAction ?? route('heritage.accounting.update', $asset->id) }}">
  @csrf
  @method('PUT')

  @include('ahg-heritage-manage::heritage-accounting._form', [
    'asset'     => $asset,
    'standards' => $standards ?? collect(),
    'classes'   => $classes ?? collect(),
    'io'        => $io ?? null,
  ])

  <div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save changes') }}</button>
    <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary btn-lg">{{ __('Cancel') }}</a>
  </div>
</form>
@endsection
