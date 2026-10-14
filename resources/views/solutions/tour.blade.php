{{-- H06: the guided demo tour. The floating card on each step is partials/demo-tour. --}}
@extends('theme::layouts.1col')

@section('title', __('Guided tour').' - Heratio')

@section('title-block')
  <h1>{{ __('Guided tour') }}</h1>
  <p class="lead mb-3">{{ __('Try Heratio the way an archivist would, in about fifteen minutes.') }}</p>
@endsection

@section('content')
  <div class="row g-4">
    <div class="col-lg-7">
      <p>{{ __('The tour takes you through the demo one page at a time. On each page a small card says what to look at and what to try, with buttons to go back, move on or stop.') }}</p>
      <ol>
        @foreach($steps as $step)
          <li class="mb-1"><strong>{{ $step['title'] }}</strong></li>
        @endforeach
      </ol>
      <button type="button" class="btn btn-primary" data-demo-tour-start>{{ __('Start the tour') }}</button>
    </div>
    <aside class="col-lg-5">
      <div class="card">
        <div class="card-body small">
          <h2 class="h6">{{ __('Before you start') }}</h2>
          <ul class="mb-0">
            <li class="mb-1">{{ __('Everything here is sample data. Change, add or delete whatever you like.') }}</li>
            <li class="mb-1">{{ __('The demo login in the sign-in menu has full administrator rights, so you can try every feature.') }}</li>
            <li class="mb-1">{{ __('The whole site is reset every night at 02:20 South African time, so nothing you do here lasts, and nobody else sees it after the reset.') }}</li>
            <li>{{ __('Please do not enter real personal information.') }}</li>
          </ul>
        </div>
      </div>
    </aside>
  </div>
@endsection
