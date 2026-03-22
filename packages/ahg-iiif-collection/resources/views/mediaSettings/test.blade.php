@extends('theme::layouts.1col')
@section('title', 'Media Processing Test')
@section('body-class', 'admin media-settings test')
@section('title-block')<h1 class="mb-0"><i class="fas fa-vial me-2"></i>Media Processing Test</h1>@endsection
@section('content')
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Test Media Processing</h5></div>
<div class="card-body">
  <form method="post" action="{{ route('iiif.media-settings.test') }}">@csrf
    <div class="mb-3"><label for="object_id" class="form-label">Object ID <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" name="object_id" id="object_id" class="form-control" placeholder="Enter object ID to test"></div>
    <button type="submit" class="btn atom-btn-white"><i class="fas fa-play me-1"></i>Run Test</button>
  </form>
  @if(isset($result))
  <div class="mt-4 alert alert-{{ ($result['status'] ?? '') === 'success' ? 'success' : 'danger' }}"><h6>Result: {{ ucfirst($result['status'] ?? '') }}</h6><pre>{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre></div>
  @endif
</div></div>
@endsection
