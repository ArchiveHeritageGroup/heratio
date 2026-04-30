@extends('theme::layouts.1col')

@section('title', 'Notifications')

@section('content')
<h1>{{ __('Notifications') }}</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Notifications') }}</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Notifications.</p>
  </div>
</div>
@endsection
