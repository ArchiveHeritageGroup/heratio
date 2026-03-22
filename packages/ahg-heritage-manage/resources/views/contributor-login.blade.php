@extends('theme::layouts.1col')
@section('title', 'Contributor Login')
@section('body-class', 'heritage')

@section('content')
<div class="row">  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-sign-in-alt me-2"></i>Contributor Login</h1>
      <a href="{{ route('heritage.landing') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-home me-1"></i>Heritage Home</a>
    </div>
    <p class="text-muted">Sign in to your contributor account.</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-sign-in-alt me-2"></i>Contributor Login</div>
      <div class="card-body">
        @if(!empty($items))
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              @foreach($columns ?? ['ID','Name','Status','Date'] as $col)
                <th>{{ $col }}</th>
              @endforeach
            </tr></thead>
            <tbody>
              @foreach($items as $item)
              <tr>@foreach((array)$item as $val)<td>{{ Str::limit($val, 80) ?: '-' }}</td>@endforeach</tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <p class="text-muted text-center py-4">No data available.</p>
        @endif
      </div>
    </div>

    @if(isset($items) && method_exists($items, 'links'))
      <div class="mt-3">{{ $items->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection