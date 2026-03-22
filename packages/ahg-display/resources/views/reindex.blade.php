@extends('theme::layouts.1col')
@section('title', 'Reindex Search')
@section('body-class', 'success')
@section('content')
  <div class="card"><div class="card-body text-center py-5">
    <i class="fas fa-sync fa-4x text-success mb-3"></i>
    <h3>Reindex Search</h3><p class="text-muted">Search index rebuild has been queued.</p>
    <a href="{{ url()->previous() }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i> Back</a>
  </div></div>
@endsection
