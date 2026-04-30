@extends('theme::layouts.1col')

@section('title', 'Radius')

@section('content')
<h1>{{ __('Radius') }}</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Radius') }}</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Radius.</p>
  </div>
</div>
@endsection
