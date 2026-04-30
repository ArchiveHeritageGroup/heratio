@extends('theme::layouts.1col')
@section('title', 'Integrity - Disposition')
@section('body-class', 'admin integrity disposition')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Disposition') }}</h1><span class="small text-muted">{{ __('Digital object integrity management') }}</span></div>
  </div>
@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Disposition Actions') }}</h5></div>
  <div class="card-body p-0">
    @if(isset($dispositions) && count($dispositions) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('Object') }}</th><th>{{ __('Action') }}</th><th>{{ __('Scheduled') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>@foreach($dispositions as $d)<tr><td>#{{ $d->object_id ?? '' }}</td><td>{{ ucfirst($d->action ?? '') }}</td><td>{{ $d->scheduled_date ?? '-' }}</td><td>{{ ucfirst($d->status ?? '') }}</td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No disposition records found.</div>@endif
  </div>
</div>
<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a></div>
@endsection
