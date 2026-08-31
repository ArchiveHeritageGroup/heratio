@extends('theme::layouts.1col')
@section('title', $container->name ?: __('Container'))
@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
      <h1 class="h4 mb-1"><i class="fas fa-box me-2"></i>{{ $container->name ?: __('(unnamed container)') }}</h1>
      <div class="text-muted small">
        @if($container->type_name){{ $container->type_name }}@endif
        @if($container->location) &middot; {{ $container->location }}@endif
      </div>
      @if($container->description)<p class="mt-2 mb-0">{{ $container->description }}</p>@endif
    </div>
    @if($qr)
      {{-- The same code that is on the box, so a curator can check that the
           label in their hand belongs to the container on the screen. --}}
      <div class="text-center">
        <img src="{{ $qr }}" alt="{{ __('QR code for this container') }}" style="width:140px;height:auto">
      </div>
    @endif
  </div>

  <h2 class="h6 mt-4">{{ __('Contents') }} <span class="text-muted">({{ $holdings->count() }})</span></h2>
  @if($holdings->isEmpty())
    <div class="alert alert-info small">{{ __('Nothing is recorded as stored in this container, or you do not have access to what is.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Title') }}</th></tr></thead>
        <tbody>
        @foreach($holdings as $h)
          <tr>
            <td class="text-nowrap">{{ $h->identifier ?: '-' }}</td>
            <td>@if($h->slug)<a href="{{ url('/'.$h->slug) }}">{{ $h->title ?: __('(untitled)') }}</a>@else{{ $h->title ?: __('(untitled)') }}@endif</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <a href="{{ route('ahglabel.containers') }}" class="btn atom-btn-white btn-sm mt-2">
    <i class="fas fa-arrow-left me-1"></i>{{ __('All containers') }}
  </a>
</div>
@endsection
