@extends('theme::layouts.1col')

@section('title', 'Featured')

@section('content')
<h1>Featured</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">Featured</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for Featured.</p>
  </div>
</div>
@endsection
