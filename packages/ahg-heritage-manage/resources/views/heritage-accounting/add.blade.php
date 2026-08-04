@extends('theme::layouts.1col')
@section('title', __('Add Heritage Asset'))
@section('body-class', 'admin heritage')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0"><i class="fas fa-plus me-2"></i>{{ __('Add Heritage Asset') }}</h1>
  @if(Route::has('heritage.accounting.browse'))
    <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>{{ __('All assets') }}</a>
  @endif
</div>

<form method="post" action="{{ route('heritage.accounting.store') }}">
  @csrf

  @include('ahg-heritage-manage::heritage-accounting._form', [
    'asset'     => null,
    'standards' => $standards ?? collect(),
    'classes'   => $classes ?? collect(),
    'io'        => $io ?? null,
  ])

  <div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save asset') }}</button>
    @if(isset($io) && $io && ($io->slug ?? null))
      <a href="{{ url('/' . $io->slug) }}" class="btn btn-outline-secondary btn-lg">{{ __('Cancel') }}</a>
    @else
      <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-secondary btn-lg">{{ __('Cancel') }}</a>
    @endif
  </div>
</form>
@endsection
