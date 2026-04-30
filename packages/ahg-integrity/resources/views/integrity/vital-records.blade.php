@extends('theme::layouts.1col')
@section('title', 'Integrity - Vital Records')
@section('body-class', 'admin integrity vital-records')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-star me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Vital Records') }}</h1><span class="small text-muted">{{ __('Critical records requiring periodic review') }}</span></div>
  </div>
@endsection
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row mb-4">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Flag as Vital Record') }}</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('integrity.vital-records.flag') }}">
          @csrf
          <div class="mb-3">
            <label for="information_object_id" class="form-label">{{ __('Information Object ID') }}</label>
            <input type="number" class="form-control" id="information_object_id" name="information_object_id" required min="1" placeholder="{{ __('Enter IO ID') }}">
          </div>
          <div class="mb-3">
            <label for="reason" class="form-label">{{ __('Reason') }}</label>
            <textarea class="form-control" id="reason" name="reason" rows="2" required maxlength="2000" placeholder="{{ __('Why is this a vital record?') }}"></textarea>
          </div>
          <div class="mb-3">
            <label for="review_cycle_days" class="form-label">{{ __('Review Cycle (days)') }}</label>
            <input type="number" class="form-control" id="review_cycle_days" name="review_cycle_days" required min="1" max="3650" value="365">
          </div>
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-star me-1"></i>{{ __('Flag as Vital') }}</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card mb-3">
      <div class="card-body">
        <div class="row text-center">
          <div class="col-md-4">
            <h3 class="mb-0">{{ $total }}</h3>
            <small class="text-muted">{{ __('Active Vital Records') }}</small>
          </div>
          <div class="col-md-4">
            <h3 class="mb-0 {{ $overdueCount > 0 ? 'text-danger' : '' }}">{{ $overdueCount }}</h3>
            <small class="text-muted">{{ __('Overdue Reviews') }}</small>
          </div>
          <div class="col-md-4">
            <a href="{{ route('integrity.vital-records.overdue') }}" class="btn btn-outline-danger btn-sm mt-2"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('View Overdue') }}</a>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('integrity.vital-records') }}" class="row g-2 align-items-end">
          <div class="col-md-8">
            <label for="repository_id" class="form-label">{{ __('Filter by Repository') }}</label>
            <select class="form-select" id="repository_id" name="repository_id">
              <option value="">-- All Repositories --</option>
              @foreach($repositories as $repo)
              <option value="{{ $repo->id }}" {{ $repositoryId == $repo->id ? 'selected' : '' }}>{{ $repo->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Vital Records <span class="badge bg-light text-dark ms-2">{{ $total }}</span></h5>
  </div>
  <div class="card-body p-0">
    @if(count($records) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <th>{{ __('ID') }}</th><th>{{ __('IO ID') }}</th><th>{{ __('IO Title') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Review Cycle') }}</th><th>{{ __('Next Review') }}</th><th>{{ __('Last Reviewed') }}</th><th>{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($records as $rec)
        @php
          $isOverdue = \Carbon\Carbon::parse($rec->next_review_date)->isPast();
        @endphp
        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
          <td>{{ $rec->id }}</td>
          <td><a href="{{ url('/informationobject/show/' . $rec->information_object_id) }}">#{{ $rec->information_object_id }}</a></td>
          <td>{{ $rec->io_title ?? '-' }}</td>
          <td>{{ \Illuminate\Support\Str::limit($rec->reason, 50) }}</td>
          <td>{{ $rec->review_cycle_days }} days</td>
          <td>
            @if($isOverdue)
              <span class="badge bg-danger">{{ $rec->next_review_date }}</span>
            @else
              {{ $rec->next_review_date }}
            @endif
          </td>
          <td>{{ $rec->last_reviewed_at ?? 'Never' }}</td>
          <td>
            <form method="POST" action="{{ route('integrity.vital-records.review', $rec->id) }}" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-sm btn-success" title="{{ __('Mark as reviewed') }}"><i class="fas fa-check"></i></button>
            </form>
            <form method="POST" action="{{ route('integrity.vital-records.unflag', $rec->information_object_id) }}" class="d-inline ms-1">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove vital flag') }}" onclick="return confirm('Remove vital record flag?')"><i class="fas fa-times"></i></button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @if($total > $perPage)
    <nav class="d-flex justify-content-center py-3">
      <ul class="pagination mb-0">
        @for($i = 1; $i <= ceil($total / $perPage); $i++)
        <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?page={{ $i }}{{ $repositoryId ? '&repository_id=' . $repositoryId : '' }}">{{ $i }}</a></li>
        @endfor
      </ul>
    </nav>
    @endif
    @else
    <div class="text-center py-4 text-muted">No vital records found.</div>
    @endif
  </div>
</div>

<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a></div>
@endsection
