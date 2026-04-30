@extends('theme::layouts.1col')
@section('title', 'Records Management - Retention Schedules')
@section('body-class', 'admin records schedules')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-calendar-check me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Retention Schedules') }}</h1><span class="small text-muted">Records management — retention schedule administration</span></div>
  </div>
@endsection
@section('content')
<div class="row mb-3">
  <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fw-bold fs-4">{{ $stats['total_schedules'] ?? 0 }}</div><small class="text-muted">Total Schedules</small></div></div></div>
  <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fw-bold fs-4 text-success">{{ $stats['active'] ?? 0 }}</div><small class="text-muted">Active</small></div></div></div>
  <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fw-bold fs-4 text-secondary">{{ $stats['draft'] ?? 0 }}</div><small class="text-muted">Draft</small></div></div></div>
  <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fw-bold fs-4">{{ $stats['total_classes'] ?? 0 }}</div><small class="text-muted">Disposal Classes</small></div></div></div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">{{ __('Retention Schedules') }}</h5>
    <a href="{{ route('records.schedules.create') }}" class="btn btn-sm btn-light"><i class="fas fa-plus me-1"></i>Create Schedule</a>
  </div>
  <div class="card-body pb-2">
    <form method="get" action="{{ route('records.schedules.index') }}" class="row g-2 mb-3">
      <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
          <option value="">{{ __('All statuses') }}</option>
          <option value="draft" {{ ($filters['status'] ?? '') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
          <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
          <option value="superseded" {{ ($filters['status'] ?? '') === 'superseded' ? 'selected' : '' }}>{{ __('Superseded') }}</option>
          <option value="expired" {{ ($filters['status'] ?? '') === 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
        </select>
      </div>
      <div class="col-md-3">
        <select name="jurisdiction" class="form-select form-select-sm">
          <option value="">{{ __('All jurisdictions') }}</option>
          @foreach($jurisdictions as $j)
            <option value="{{ $j }}" {{ ($filters['jurisdiction'] ?? '') === $j ? 'selected' : '' }}>{{ $j }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('Search ref or title...') }}" value="{{ $filters['search'] ?? '' }}">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-sm atom-btn-white w-100"><i class="fas fa-filter me-1"></i>Filter</button>
      </div>
    </form>
  </div>
  <div class="card-body p-0">
    @if(count($items) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <th>{{ __('Ref') }}</th><th>{{ __('Title') }}</th><th>{{ __('Jurisdiction') }}</th><th>{{ __('Effective Date') }}</th><th>{{ __('Review Date') }}</th><th>{{ __('Classes') }}</th><th>{{ __('Status') }}</th>
      </tr></thead>
      <tbody>
        @foreach($items as $item)
        <tr>
          <td><a href="{{ route('records.schedules.show', $item->id) }}">{{ $item->schedule_ref }}</a></td>
          <td><a href="{{ route('records.schedules.show', $item->id) }}">{{ $item->title }}</a></td>
          <td>{{ $item->jurisdiction ?? '-' }}</td>
          <td>{{ $item->effective_date ?? '-' }}</td>
          <td>{{ $item->review_date ?? '-' }}</td>
          <td>{{ $item->class_count ?? 0 }}</td>
          <td>
            @if($item->status === 'draft')
              <span class="badge bg-secondary">Draft</span>
            @elseif($item->status === 'active')
              <span class="badge bg-success">Active</span>
            @elseif($item->status === 'superseded')
              <span class="badge bg-warning text-dark">Superseded</span>
            @elseif($item->status === 'expired')
              <span class="badge bg-danger">Expired</span>
            @else
              <span class="badge bg-secondary">{{ ucfirst($item->status) }}</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
      <div class="text-center py-4 text-muted">No retention schedules found.</div>
    @endif
  </div>
</div>

@if($total > $perPage)
<nav class="mt-3">
  <ul class="pagination justify-content-center">
    @for($i = 1; $i <= ceil($total / $perPage); $i++)
      <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="{{ route('records.schedules.index', array_merge($filters, ['page' => $i])) }}">{{ $i }}</a></li>
    @endfor
  </ul>
</nav>
@endif
@endsection
