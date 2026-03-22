@extends('theme::layouts.1col')
@section('title', 'GRAP 103 Compliance Dashboard')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-balance-scale me-2"></i>GRAP 103 Compliance Dashboard</h1>
    <p class="text-muted">South African heritage asset compliance monitoring.</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Stats Cards --}}
    <div class="row mb-4">
      @foreach($stats ?? [] as $key => $value)
      <div class="col-md-3 mb-3">
        <div class="card border-primary h-100">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($value) }}</h3>
            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</small>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-balance-scale me-2"></i>GRAP 103 Compliance Dashboard</div>
      <div class="card-body">
        @if(!empty($items))
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr style="background:var(--ahg-primary);color:#fff">
              @foreach($columns ?? ['ID','Asset','Standard','Status','Score','Date'] as $col)
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
        <p class="text-muted text-center py-4">No records found.</p>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection