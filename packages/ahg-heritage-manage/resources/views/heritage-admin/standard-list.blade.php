@extends('theme::layouts.1col')
@section('title', 'Accounting Standards')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-book me-2"></i>{{ __('Accounting Standards') }}</h1>
    </div>
    <p class="text-muted">Manage heritage accounting standards.</p>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-book me-2"></i>{{ __('Accounting Standards') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              @foreach($columns ?? ['ID','Name','Code','Status','Actions'] as $col)
                <th>{{ $col }}</th>
              @endforeach
            </tr></thead>
            <tbody>
              @forelse($items ?? [] as $item)
              <tr>@foreach((array)$item as $val)<td>{{ Str::limit($val, 80) ?: '-' }}</td>@endforeach</tr>
              @empty
              <tr><td colspan="{{ count($columns ?? ['ID','Name','Code','Status','Actions']) }}" class="text-center text-muted py-3">No records found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection