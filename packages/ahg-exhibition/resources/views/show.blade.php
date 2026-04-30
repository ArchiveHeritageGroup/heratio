@extends('theme::layouts.1col')

@section('title', 'Show')

@section('content')
<h1>{{ __('Show') }}</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Show') }}</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Show.</p>
  </div>
</div>
@endsection
