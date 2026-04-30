@extends('theme::layouts.1col')
@section('title', 'Integrity - Dead Letter')
@section('body-class', 'admin integrity dead-letter')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Dead Letter') }}</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Dead Letter Queue') }}</h5></div>
  <div class="card-body p-0">
    @if(isset($deadLetters) && count($deadLetters) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('ID') }}</th><th>{{ __('Object') }}</th><th>{{ __('Failure Type') }}</th><th>{{ __('Message') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>@foreach($deadLetters as $dl)<tr><td>{{ $dl->id }}</td><td>#{{ $dl->digital_object_id ?? '' }}</td><td>{{ $dl->failure_type ?? '' }}</td><td>{{ Str::limit($dl->message ?? '', 60) }}</td><td>{{ $dl->created_at ?? '' }}</td><td><span class="badge bg-{{ ($dl->status ?? 'open') === 'open' ? 'danger' : 'success' }}">{{ ucfirst($dl->status ?? 'open') }}</span></td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No dead letter entries found.</div>@endif
  </div>
</div>
<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
