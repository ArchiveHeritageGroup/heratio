@extends('theme::layouts.1col')

@section('title', 'Semantic Search')

@section('content')
<h1>Semantic Search</h1>

<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>#</th><th>Name</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="3" class="text-muted text-center">No records found.</td></tr>
    </tbody>
  </table>
</div>
@endsection
