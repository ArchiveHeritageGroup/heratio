@extends('theme::layouts.1col')

@section('title', 'Privacy & Data Protection')

@section('content')
<div class="d-flex align-items-center mb-3">
  <a href="{{ url()->previous() && url()->previous() !== url()->current() ? url()->previous() : url('/admin') }}"
     class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}">
    <i class="fas fa-arrow-left"></i>
  </a>
  <h1 class="h3 mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('Privacy & Data Protection') }}</h1>
</div>

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
