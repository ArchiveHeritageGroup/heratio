@extends('theme::layouts.1col')
@section('title', 'DOI Minted')
@section('body-class', 'success')
@section('content')
  <div class="card"><div class="card-body text-center py-5">
    <i class="fas fa-fingerprint fa-4x text-success mb-3"></i>
    <h3>{{ __('DOI Minted') }}</h3><p class="text-muted">DOI has been minted successfully.</p>
    <a href="{{ url()->previous() }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}</a>
  </div></div>
@endsection
