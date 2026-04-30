@extends('theme::layouts.1col')

@section('title', 'Search History')

@section('content')
<h1>{{ __('Search History') }}</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Search History') }}</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Search History.</p>
  </div>
</div>
@endsection
