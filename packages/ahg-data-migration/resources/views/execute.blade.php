@extends('theme::layouts.1col')
@section('title', 'Execute Migration')
@section('body-class', 'success')
@section('content')
  <div class="card"><div class="card-body text-center py-5">
    <i class="fas fa-play-circle fa-4x text-success mb-3"></i>
    <h3>{{ __('Execute Migration') }}</h3><p class="text-muted">Migration job has been queued.</p>
    <a href="{{ url()->previous() }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}</a>
  </div></div>
@endsection
