@extends('theme::layouts.1col')

@section('title', 'Consultation View')

@section('content')
<h1>{{ __('Consultation View') }}</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Consultation View') }}</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Consultation View.</p>
  </div>
</div>
@endsection
