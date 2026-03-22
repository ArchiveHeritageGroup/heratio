@extends('theme::layouts.1col')

@section('title', 'My Bids')

@section('content')
<h1>My Bids</h1>

<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead>
      <tr style="background:var(--ahg-primary);color:#fff">
        <th>#</th><th>Name</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="3" class="text-muted text-center">No records found.</td></tr>
    </tbody>
  </table>
</div>
@endsection
