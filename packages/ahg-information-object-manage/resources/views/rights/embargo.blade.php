@extends('theme::layouts.1col')
@section('title', 'Add Embargo — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-lock',
    'featureTitle' => 'Add Embargo',
    'featureDescription' => 'Restrict access to this description until a specified date',
  ])

  <div class="card">
    <div class="card-body">
      <form>
        <div class="mb-3">
          <label class="form-label" for="embargo-date">Embargo until</label>
          <input type="date" class="form-control" id="embargo-date" style="max-width:300px;">
        </div>
        <div class="mb-3">
          <label class="form-label" for="embargo-reason">Reason</label>
          <textarea class="form-control" id="embargo-reason" rows="3" placeholder="Reason for embargo..."></textarea>
        </div>
        <button type="button" class="btn atom-btn-outline-success" onclick="alert('Embargo save — migration in progress'); return false;">
          <i class="fas fa-lock me-1"></i> Apply embargo
        </button>
      </form>
    </div>
  </div>
@endsection
