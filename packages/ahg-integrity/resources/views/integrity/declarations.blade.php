@extends('theme::layouts.1col')
@section('title', 'Integrity - Record Declarations')
@section('body-class', 'admin integrity declarations')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-signature me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Record Declarations') }}</h1><span class="small text-muted">Formal record declaration workflow</span></div>
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
  <div class="col-md-4">
    <div class="card">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Declare a Record') }}</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('integrity.declare-record') }}">
          @csrf
          <div class="mb-3">
            <label for="information_object_id" class="form-label">{{ __('Information Object ID') }}</label>
            <input type="number" class="form-control" id="information_object_id" name="information_object_id" required min="1" placeholder="{{ __('Enter IO ID') }}">
          </div>
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-file-signature me-1"></i>Submit Declaration</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <h5 class="mb-0">{{ __('Summary') }}</h5>
      </div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-md-4">
            <h3 class="mb-0">{{ count($pending) }}</h3>
            <small class="text-muted">Pending Approval</small>
          </div>
          <div class="col-md-4">
            <h3 class="mb-0">{{ collect($declarations)->where('status', 'declared')->count() }}</h3>
            <small class="text-muted">Declared</small>
          </div>
          <div class="col-md-4">
            <h3 class="mb-0">{{ $total }}</h3>
            <small class="text-muted">Total</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<ul class="nav nav-tabs mb-3" id="declTabs" role="tablist">
  <li class="nav-item"><a class="nav-link active" id="pending-tab" data-bs-toggle="tab" href="#pending" role="tab">Pending <span class="badge bg-warning text-dark">{{ count($pending) }}</span></a></li>
  <li class="nav-item"><a class="nav-link" id="all-tab" data-bs-toggle="tab" href="#all" role="tab">All Declarations <span class="badge bg-secondary">{{ $total }}</span></a></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="pending" role="tabpanel">
    <div class="card">
      <div class="card-body p-0">
        @if(count($pending) > 0)
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
              <th>{{ __('ID') }}</th><th>{{ __('IO ID') }}</th><th>{{ __('IO Title') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pending as $p)
            <tr>
              <td>{{ $p->id }}</td>
              <td><a href="{{ url('/informationobject/show/' . $p->information_object_id) }}">#{{ $p->information_object_id }}</a></td>
              <td>{{ $p->io_title ?? '-' }}</td>
              <td><span class="badge bg-warning text-dark">{{ ucfirst(str_replace('_', ' ', $p->status)) }}</span></td>
              <td>{{ $p->created_at }}</td>
              <td>
                <form method="POST" action="{{ route('integrity.declarations.approve', $p->information_object_id) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this declaration?')"><i class="fas fa-check me-1"></i>Approve</button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="text-center py-4 text-muted">No pending declarations.</div>
        @endif
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="all" role="tabpanel">
    <div class="card">
      <div class="card-body p-0">
        @if(count($declarations) > 0)
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
              <th>{{ __('ID') }}</th><th>{{ __('IO ID') }}</th><th>{{ __('IO Title') }}</th><th>{{ __('Status') }}</th><th>{{ __('Declared By') }}</th><th>{{ __('Declared At') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($declarations as $d)
            <tr>
              <td>{{ $d->id }}</td>
              <td><a href="{{ url('/informationobject/show/' . $d->information_object_id) }}">#{{ $d->information_object_id }}</a></td>
              <td>{{ $d->io_title ?? '-' }}</td>
              <td>
                @if($d->status === 'declared')
                  <span class="badge bg-success">Declared</span>
                @elseif($d->status === 'pending_approval')
                  <span class="badge bg-warning text-dark">Pending</span>
                @else
                  <span class="badge bg-secondary">{{ ucfirst($d->status) }}</span>
                @endif
              </td>
              <td>{{ $d->declared_by_name ?? '-' }}</td>
              <td>{{ $d->declared_at ?? '-' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>

        @if($total > $perPage)
        <nav class="d-flex justify-content-center py-3">
          <ul class="pagination mb-0">
            @for($i = 1; $i <= ceil($total / $perPage); $i++)
            <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?page={{ $i }}">{{ $i }}</a></li>
            @endfor
          </ul>
        </nav>
        @endif
        @else
        <div class="text-center py-4 text-muted">No declarations found.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
