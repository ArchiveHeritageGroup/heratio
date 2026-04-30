{{-- 3D Digital Objects Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', '3D Files Report')
@section('body-class', 'admin three-d-reports digital-objects')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('iiif.three-d-reports.index') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a></div>@endsection
@section('title-block')<h1><i class="fas fa-file me-2"></i>3D Files Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> 3D files found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('File') }}</th><th>{{ __('Object') }}</th><th>{{ __('MIME Type') }}</th><th>{{ __('Size') }}</th></tr></thead>
    <tbody>
      @forelse($items as $d)
      <tr>
        <td><strong>{{ e($d->name ?? '-') }}</strong></td>
        <td>{{ e($d->object_title ?? '-') }}</td>
        <td><code>{{ $d->mime_type ?? '-' }}</code></td>
        <td class="text-end">@php $b=$d->byte_size??0;$u=['B','KB','MB','GB'];$p=floor(($b?log($b):0)/log(1024));echo round($b/pow(1024,min($p,3)),1).' '.$u[min($p,3)]; @endphp</td>
      </tr>
      @empty
      <tr><td colspan="4" class="text-muted text-center py-4">No 3D files found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
