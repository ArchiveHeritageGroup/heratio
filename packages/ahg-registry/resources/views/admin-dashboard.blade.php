@extends('ahg-registry::layouts.registry')

@section('title', 'Registry Admin')

@section('content')
<h1>{{ __('Registry Admin') }}</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Registry Admin') }}</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Registry Admin.</p>
  </div>
</div>
@endsection
