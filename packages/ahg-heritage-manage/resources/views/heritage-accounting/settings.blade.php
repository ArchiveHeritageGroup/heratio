@extends('theme::layouts.1col')
@section('title', 'Accounting Settings')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-cog me-2"></i>Accounting Settings</h1>
      @if(Route::has('heritage.accounting.add'))
      <a href="{{ route('heritage.accounting.add') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-plus me-1"></i>Add Asset</a>
      @endif
    </div>
    <p class="text-muted">Configure heritage asset accounting settings.</p>

    {{-- Stats --}}
    @if(!empty($stats))
    <div class="row mb-4">
      @foreach($stats as $key => $value)
      <div class="col-md-3 mb-3">
        <div class="card bg-{{ ['primary','success','warning','info'][$loop->index % 4] }} text-white h-100">
          <div class="card-body"><h6 class="text-white-50">{{ ucwords(str_replace('_', ' ', $key)) }}</h6><h2 class="mb-0">{{ is_numeric($value) ? number_format($value, (strpos($key,'value')!==false?2:0)) : $value }}</h2></div>
        </div>
      </div>
      @endforeach
    </div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cog me-2"></i>Accounting Settings</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              @foreach($columns ?? ['ID','Name','Class','Status','Value','Date'] as $col)
                <th>{{ $col }}</th>
              @endforeach
            </tr></thead>
            <tbody>
              @forelse($items ?? [] as $item)
              <tr>@foreach((array)$item as $val)<td>{{ Str::limit($val, 80) ?: '-' }}</td>@endforeach</tr>
              @empty
              <tr><td colspan="{{ count($columns ?? ['ID','Name','Class','Status','Value','Date']) }}" class="text-center text-muted py-3">No records found</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if(isset($items) && method_exists($items, 'links'))
      <div class="mt-3">{{ $items->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection