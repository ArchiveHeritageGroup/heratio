@extends('theme::layouts.1col')
@section('title', 'Holdings Report Export')
@section('body-class', 'success')
@section('content')
  <div class="card"><div class="card-body text-center py-5"><i class="fas fa-file-download fa-4x text-success mb-3"></i><h3>Holdings Report Export</h3><p class="text-muted">Holdings report is being generated.</p>
    <a href="{{ url()->previous() }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i> Back</a>
  </div></div>
@endsection
