@extends('theme::layouts.1col')
@section('title', 'Integrity - Legal Holds')
@section('body-class', 'admin integrity holds')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Legal Holds') }}</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row mb-3">
  <div class="col-md-4">
    <div class="card text-center">
      <div class="card-body">
        <h5 class="card-title text-danger">{{ $counts['active'] ?? 0 }}</h5>
        <p class="card-text mb-0">Active Holds</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-center">
      <div class="card-body">
        <h5 class="card-title text-secondary">{{ $counts['released'] ?? 0 }}</h5>
        <p class="card-text mb-0">Released</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-center">
      <div class="card-body">
        <h5 class="card-title">{{ $counts['total'] ?? 0 }}</h5>
        <p class="card-text mb-0">Total</p>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="get" action="{{ route('integrity.holds') }}" class="d-flex align-items-center gap-2">
    <select name="repository_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
      <option value="">{{ __('All repositories') }}</option>
      @foreach($repositories as $repo)
        <option value="{{ $repo->id }}" {{ ($repositoryId ?? '') == $repo->id ? 'selected' : '' }}>{{ $repo->name }}</option>
      @endforeach
    </select>
  </form>
  <a href="{{ route('integrity.holds.create') }}" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Place Hold</a>
</div>

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Active Legal Holds') }}</h5></div>
  <div class="card-body p-0">
    @if(count($holds) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <th>{{ __('ID') }}</th><th>{{ __('IO') }}</th><th>{{ __('Title') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Placed By') }}</th><th>{{ __('Placed At') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th>
      </tr></thead>
      <tbody>
        @foreach($holds as $h)
        @php $hold = is_array($h) ? (object) $h : $h; @endphp
        <tr>
          <td>{{ $hold->id }}</td>
          <td><a href="{{ url('/informationobject/show/' . $hold->information_object_id) }}">#{{ $hold->information_object_id }}</a></td>
          <td>{{ $hold->io_title ?? '-' }}</td>
          <td>{{ \Illuminate\Support\Str::limit($hold->reason, 80) }}</td>
          <td>{{ $hold->placed_by }}</td>
          <td>{{ $hold->placed_at }}</td>
          <td><span class="badge bg-{{ $hold->status === 'active' ? 'danger' : 'secondary' }}">{{ ucfirst($hold->status) }}</span></td>
          <td class="text-nowrap">
            <a href="{{ route('integrity.holds.history', ['ioId' => $hold->information_object_id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('History') }}"><i class="fas fa-history"></i></a>
            @if($hold->status === 'active')
            <button type="button" class="btn btn-sm btn-outline-warning" title="{{ __('Release') }}" data-bs-toggle="modal" data-bs-target="#releaseModal{{ $hold->id }}"><i class="fas fa-unlock"></i></button>
            <!-- Release Modal -->
            <div class="modal fade" id="releaseModal{{ $hold->id }}" tabindex="-1">
              <div class="modal-dialog">
                <form method="post" action="{{ route('integrity.holds.release', ['id' => $hold->id]) }}">
                  @csrf
                  <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Release Hold #{{ $hold->id }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <label class="form-label">{{ __('Release Reason') }}</label>
                        <textarea name="release_reason" class="form-control" rows="3" required></textarea>
                      </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-warning">{{ __('Release Hold') }}</button></div>
                  </div>
                </form>
              </div>
            </div>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="text-center py-4 text-muted">No active legal holds found.</div>
    @endif
  </div>
</div>

@if($total > $perPage)
<nav class="mt-3">
  <ul class="pagination justify-content-center">
    @for($p = 1; $p <= ceil($total / $perPage); $p++)
    <li class="page-item {{ $p == $page ? 'active' : '' }}"><a class="page-link" href="{{ route('integrity.holds', ['page' => $p, 'repository_id' => $repositoryId]) }}">{{ $p }}</a></li>
    @endfor
  </ul>
</nav>
@endif

<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
