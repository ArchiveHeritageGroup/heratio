{{-- #123 enable_barcodes: scan landing page. --}}
@extends('theme::layouts.1col')

@section('title', __('Barcode lookup'))

@section('content')

<h1><i class="fas fa-barcode"></i> {{ __('Barcode lookup') }}</h1>

<div class="row">
  <div class="col-md-8">
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('Scan or type a barcode') }}</h5></div>
      <div class="card-body">
        <form method="post" action="{{ route('ahgspectrum.barcode-scan') }}">
          @csrf
          <div class="input-group mb-3">
            <input type="text" name="barcode" class="form-control form-control-lg"
                   placeholder="{{ __('Scan barcode...') }}"
                   value="{{ $barcode ?? '' }}" autofocus>
            <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>{{ __('Look up') }}</button>
          </div>
        </form>

        @if(($result ?? null) === 'not-found')
          <div class="alert alert-warning">
            {{ __('No object is assigned to this barcode:') }} <code>{{ $barcode }}</code>
          </div>
        @elseif(($result ?? null) === 'object-without-slug')
          <div class="alert alert-warning">
            {{ __('Found object id') }} <code>{{ $object_id }}</code>,
            {{ __('but it has no public slug; cannot redirect.') }}
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">{{ __('About') }}</h5></div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Operators scan a barcode (or type one) to jump straight to the matching archival record. Barcodes are managed centrally in the spectrum_object_barcode table; assign them via the API or seed scripts.') }}</p>
        <p class="small mb-0"><a href="{{ route('settings.ahg.spectrum') }}">{{ __('Spectrum settings') }}</a></p>
      </div>
    </div>
  </div>
</div>

@endsection
