@extends('theme::layouts.1col')
@section('title', 'Hero Slides')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-images me-2"></i>Hero Slides</h1>
    </div>
    <p class="text-muted">Manage hero slideshow images for the landing page.</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-images me-2"></i>Hero Slides</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                @foreach($columns ?? ['ID','Name','Status','Date'] as $col)
                  <th>{{ $col }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @forelse($items ?? [] as $item)
              <tr>
                @foreach((array)$item as $val)
                  <td>{{ Str::limit($val, 80) ?: '-' }}</td>
                @endforeach
              </tr>
              @empty
              <tr><td colspan="{{ count($columns ?? ['ID','Name','Status','Date']) }}" class="text-center text-muted py-3">No records found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection