{{-- Assets Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Assets Report')
@section('body-class', 'dam-reports assets')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('DAM Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('dam.reports.assets') }}"><i class="fas fa-file me-2"></i>Assets</a></li>
    <li><a href="{{ route('dam.reports.metadata') }}"><i class="fas fa-info-circle me-2"></i>Metadata</a></li>
    <li><a href="{{ route('dam.reports.iptc') }}"><i class="fas fa-camera me-2"></i>IPTC Data</a></li>
    <li><a href="{{ route('dam.reports.storage') }}"><i class="fas fa-hdd me-2"></i>Storage</a></li>
  </ul>
  <hr>
  <a href="{{ route('dam.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-file me-2"></i>Assets Report</h1>@endsection
@section('content')
@php if (!function_exists('damFormatBytes2')) { function damFormatBytes2($b,$p=2){$u=['B','KB','MB','GB','TB'];$b=max($b,0);$w=floor(($b?log($b):0)/log(1024));$w=min($w,count($u)-1);return round($b/pow(1024,$w),$p).' '.$u[$w];} } @endphp
<div class="alert alert-info"><strong>{{ count($rows) }}</strong> assets found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Filename') }}</th><th>{{ __('Record') }}</th><th>{{ __('Type') }}</th><th>{{ __('Size') }}</th></tr></thead>
    <tbody>
      @forelse($rows as $r)
      <tr>
        <td><strong>{{ e($r->name ?? '-') }}</strong></td>
        <td>{{ e($r->record_title ?? $r->record_identifier ?? '-') }}</td>
        <td><code>{{ $r->mime_type ?? '-' }}</code></td>
        <td class="text-end">{{ damFormatBytes2($r->byte_size ?? 0) }}</td>
      </tr>
      @empty
      <tr><td colspan="4" class="text-muted text-center py-4">No assets found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
