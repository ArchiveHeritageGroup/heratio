@extends('theme::layouts.1col')

@section('title', 'NAZ Audit Log')

@section('content')
<h1>NAZ Audit Log</h1>

<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">Filters</h5>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('ahgnaz.audit-log') }}" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label">Action type</label>
        <input type="text" name="action_type" class="form-control form-control-sm" value="{{ request('action_type') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">Entity type</label>
        <input type="text" name="entity_type" class="form-control form-control-sm" value="{{ request('entity_type') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">User ID</label>
        <input type="number" name="user_id" class="form-control form-control-sm" value="{{ request('user_id') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
      </div>
      <div class="col-md-2">
        <button class="btn btn-sm btn-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<p class="text-muted">{{ $total }} entries found.</p>

<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>Date</th>
      <th>Action</th>
      <th>Entity</th>
      <th>Entity ID</th>
      <th>User</th>
      <th>IP</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $row)
    <tr>
      <td>{{ $row->created_at }}</td>
      <td>{{ $row->action_type }}</td>
      <td>{{ $row->entity_type }}</td>
      <td>{{ $row->entity_id }}</td>
      <td>{{ $row->user_id }}</td>
      <td>{{ $row->ip_address }}</td>
      <td>{{ \Illuminate\Support\Str::limit($row->notes, 80) }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-muted">No audit log entries.</td></tr>
    @endforelse
  </tbody>
</table>

@if($total > $limit)
<nav>
  <ul class="pagination pagination-sm">
    @for($p = 1; $p <= ceil($total / $limit); $p++)
    <li class="page-item {{ $p == $page ? 'active' : '' }}">
      <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a>
    </li>
    @endfor
  </ul>
</nav>
@endif
@endsection
