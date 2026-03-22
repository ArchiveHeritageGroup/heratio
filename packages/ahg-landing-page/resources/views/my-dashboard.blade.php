@extends('theme::layouts.1col')

@section('title', 'My Dashboard')

@section('content')
<h1>My Dashboard</h1>

<div class="card">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">My Dashboard</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Content for My Dashboard.</p>
  </div>
</div>
@endsection
