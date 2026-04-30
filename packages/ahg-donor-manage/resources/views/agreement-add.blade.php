@extends('theme::layouts.1col')
@section('title', 'Add Agreement')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-file-signature me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('Add Agreement') }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>{{ __('Add Agreement') }}</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    @include('ahg-donor-manage::_agreement-form')
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> {{ __('Cancel') }}</a></div>
  </form></div></div>
@endsection
