@extends('theme::layouts.1col')
@section('title', 'Add Valuer')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="bi bi-person-plus me-2"></i>{{ __('Add Valuer') }}</h1>
    <p class="text-muted">{{ __('Register a new qualified valuer.') }}</p>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction }}">
      @include('ahg-heritage-manage::valuers._form')
    </form>
  </div>
</div>
@endsection
