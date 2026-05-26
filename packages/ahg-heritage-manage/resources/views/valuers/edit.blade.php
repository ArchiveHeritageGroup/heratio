@extends('theme::layouts.1col')
@section('title', 'Edit Valuer')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="bi bi-pencil-square me-2"></i>{{ __('Edit Valuer') }}: {{ $item->name }}</h1>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction }}">
      @include('ahg-heritage-manage::valuers._form')
    </form>
  </div>
</div>
@endsection
