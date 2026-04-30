@extends('theme::layouts.1col')

@section('title', 'Researcher View')

@section('content')
<h1>{{ __('Researcher View') }}</h1>

<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>#</th><th>{{ __('Name') }}</th><th>{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="3" class="text-muted text-center">No records found.</td></tr>
    </tbody>
  </table>
</div>
@endsection
