{{-- Storage Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Storage Report')
@section('body-class', 'dam-reports storage')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Storage Summary') }}</h4>
  @php
  if (!function_exists('damFmtBytes')) { function damFmtBytes($b,$p=2){$u=['B','KB','MB','GB','TB'];$b=max($b,0);$w=floor(($b?log($b):0)/log(1024));$w=min($w,count($u)-1);return round($b/pow(1024,$w),$p).' '.$u[$w];} }
  @endphp
  <div class="alert alert-primary">
    <strong>{{ damFmtBytes($storage['total'] ?? 0) }}</strong><br>
    <small>{{ __('Total Storage Used') }}</small>
  </div>
  @if(($storage['orphaned'] ?? 0) > 0)
  <div class="alert alert-warning">
    <strong>{{ $storage['orphaned'] }}</strong> orphaned files
  </div>
  @endif
  <hr>
  <a href="{{ route('dam.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-hdd me-2"></i>{{ __('Storage Report') }}</h1>@endsection
@section('content')
<div class="card">
  <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-hdd me-2"></i>{{ __('Storage by File Type') }}</h5></div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead class="table-light"><tr><th>{{ __('MIME Type') }}</th><th class="text-end">{{ __('Files') }}</th><th class="text-end">{{ __('Size') }}</th></tr></thead>
      <tbody>
        @forelse($storage['byType'] ?? [] as $t)
        <tr>
          <td><code>{{ $t->mime_type ?? 'unknown' }}</code></td>
          <td class="text-end">{{ number_format($t->count ?? 0) }}</td>
          <td class="text-end">{{ damFmtBytes($t->size ?? 0) }}</td>
        </tr>
        @empty
        <tr><td colspan="3" class="text-muted text-center py-4">No storage data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
