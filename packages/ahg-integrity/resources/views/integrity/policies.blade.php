@extends('theme::layouts.1col')
@section('title', 'Integrity - Policies')
@section('body-class', 'admin integrity policies')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Policies') }}</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Policies') }}</h5></div>
  <div class="card-body p-0">
    @if(isset($items) && count($items) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('ID') }}</th><th>{{ __('Name') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>@foreach($items as $item)<tr><td>{{ $item->id ?? '' }}</td><td>{{ $item->name ?? $item->title ?? '' }}</td><td>{{ $item->created_at ?? $item->started_at ?? '' }}</td><td>{{ ucfirst($item->status ?? '') }}</td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No records found.</div>@endif
  </div>
</div>
<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
