{{-- IPTC Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'IPTC Data Report')
@section('body-class', 'dam-reports iptc')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('DAM Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('dam.reports.assets') }}"><i class="fas fa-file me-2"></i>{{ __('Assets') }}</a></li>
    <li><a href="{{ route('dam.reports.metadata') }}"><i class="fas fa-info-circle me-2"></i>{{ __('Metadata') }}</a></li>
    <li><a href="{{ route('dam.reports.iptc') }}"><i class="fas fa-camera me-2"></i>{{ __('IPTC Data') }}</a></li>
    <li><a href="{{ route('dam.reports.storage') }}"><i class="fas fa-hdd me-2"></i>{{ __('Storage') }}</a></li>
  </ul>
  <hr>
  <a href="{{ route('dam.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-camera me-2"></i>{{ __('IPTC Data Report') }}</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($rows) }}</strong> records with IPTC data</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('File') }}</th><th>{{ __('Property') }}</th><th>{{ __('Value') }}</th></tr></thead>
    <tbody>
      @forelse($rows as $r)
      <tr>
        <td><strong>{{ e($r->name ?? '-') }}</strong></td>
        <td><code>{{ $r->property_name ?? '-' }}</code></td>
        <td>{{ e($r->property_value ?? '-') }}</td>
      </tr>
      @empty
      <tr><td colspan="3" class="text-muted text-center py-4">No IPTC data found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
