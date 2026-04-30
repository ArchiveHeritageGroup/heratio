@extends('theme::layouts.1col')

@section('title', 'Split Authority Record')
@section('body-class', 'authority split')

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ $actor->slug ? route('actor.show', $actor->slug) : '#' }}">{{ e($actor->name ?? '') }}</a>
    </li>
    <li class="breadcrumb-item active">Split</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-divide me-2"></i>Split Authority Record</h1>

<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-divide me-1"></i>Split: {{ e($actor->name ?? '') }}
  </div>
  <div class="card-body">
    <p class="text-muted">Select fields and relations to move to a new authority record.</p>

    <form id="split-form">
      <div class="mb-3">
        <label class="form-label">{{ __('New actor name') }}</label>
        <input type="text" name="new_name" id="split-new-name" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Notes') }}</label>
        <textarea name="notes" id="split-notes" class="form-control" rows="3"></textarea>
      </div>

      <button type="submit" class="btn btn-warning">
        <i class="fas fa-divide me-1"></i>Create Split Request
      </button>
      <a href="{{ route('actor.dashboard') }}" class="btn atom-btn-white ms-2">
        Cancel
      </a>
    </form>
  </div>
</div>

<script>
var csrfToken = '{{ csrf_token() }}';

document.getElementById('split-form').addEventListener('submit', function(e) {
  e.preventDefault();

  var data = new FormData();
  data.append('_token', csrfToken);
  data.append('source_id', {{ (int) $actor->id }});
  data.append('notes', document.getElementById('split-notes').value);

  fetch('{{ route("actor.api.split.execute") }}', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.success) {
        window.location.href = '{{ route("actor.dashboard") }}';
      }
    });
});
</script>

@endsection
