@extends('theme::layouts.1col')

@section('title', __('Research Datasets'))
@section('body-class', 'rdm datasets')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-database me-2"></i>{{ __('Research Datasets') }}</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('rdm.datasets.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-gauge-high me-1"></i>{{ __('Dashboard') }}</a>
    <a href="{{ route('rdm.datasets.compliance') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-clipboard-check me-1"></i>{{ __('Compliance scoreboard') }}</a>
    <a href="{{ route('rdm.datasets.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('New dataset') }}</a>
  </div>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
  <div class="card-body p-0">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr>
        <th>{{ __('Title') }}</th><th>{{ __('Project') }}</th>
        <th>{{ __('Status') }}</th><th class="text-end">{{ __('Files') }}</th><th>{{ __('Created') }}</th>
      </tr></thead>
      <tbody>
        @forelse ($datasets as $d)
          <tr>
            <td><a href="{{ route('rdm.datasets.show', $d->id) }}">{{ $d->title }}</a></td>
            <td class="small text-muted">{{ $d->project_title ?? '—' }}</td>
            <td><span class="badge bg-secondary">{{ $d->status }}</span></td>
            <td class="text-end">{{ $d->file_count }}</td>
            <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $d->created_at, 16, '') }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No datasets yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
