{{-- Provenance Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Provenance Report')
@section('body-class', 'museum-reports provenance')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('museum.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-history me-2"></i>{{ __('Provenance Report') }}</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($records) }}</strong> records with provenance</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Provenance') }}</th><th>{{ __('Legal Status') }}</th><th>{{ __('Rights Holder') }}</th></tr></thead>
    <tbody>
      @forelse($records as $r)
      <tr><td><strong>{{ e($r->title ?? '-') }}</strong></td><td>{{ Str::limit($r->provenance_text ?? '-', 80) }}</td><td>{{ e($r->legal_status ?? '-') }}</td><td>{{ e($r->rights_holder ?? '-') }}</td></tr>
      @empty
      <tr><td colspan="4" class="text-muted text-center py-4">No provenance records found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
