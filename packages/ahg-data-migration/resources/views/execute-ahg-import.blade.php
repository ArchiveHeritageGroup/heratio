@extends('theme::layouts.1col')
@section('title', 'Execute Import')
@section('body-class', 'success')
@section('content')
  <div class="card"><div class="card-body text-center py-5">
    <i class="fas fa-play-circle fa-4x text-success mb-3"></i>
    <h3>Execute Import</h3><p class="text-muted">Import is being processed.</p>
    <a href="{{ url()->previous() }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i> Back</a>
  </div></div>
@endsection
