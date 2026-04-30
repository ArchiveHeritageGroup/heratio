@extends('theme::layouts.2col')
@section('title', 'Services Monitor')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-heartbeat me-2"></i>{{ __('Services Monitor') }}</h1>
@endsection

@section('content')
    <div class="row g-3">
      @foreach($serviceChecks as $name => $check)
        <div class="col-md-6">
          <div class="card {{ $check['status'] === 'error' ? 'border-danger' : ($check['status'] === 'warning' ? 'border-warning' : 'border-success') }}">
            <div class="card-body d-flex align-items-center">
              <div class="me-3">
                @if($check['status'] === 'ok' || $check['status'] === 'green')
                  <i class="fas fa-check-circle fa-2x text-success"></i>
                @elseif($check['status'] === 'warning' || $check['status'] === 'yellow')
                  <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                @else
                  <i class="fas fa-times-circle fa-2x text-danger"></i>
                @endif
              </div>
              <div>
                <h6 class="mb-0">{{ $name }}</h6>
                <small class="text-muted">{{ $check['message'] }}</small>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
@endsection
