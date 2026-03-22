@extends('theme::layouts.1col')

@section('title', 'Security Audit Log')

@section('content')
<div class="container py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL</a></li>
      <li class="breadcrumb-item active" aria-current="page">Audit Log</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-clipboard-list me-2"></i> Security Audit Log</h2>
    <div>
      <div class="btn-group" role="group">
        <a href="{{ route('acl.audit-log', ['limit' => 50]) }}" class="btn atom-btn-white {{ $limit == 50 ? 'active' : '' }}">50</a>
        <a href="{{ route('acl.audit-log', ['limit' => 100]) }}" class="btn atom-btn-white {{ $limit == 100 ? 'active' : '' }}">100</a>
        <a href="{{ route('acl.audit-log', ['limit' => 250]) }}" class="btn atom-btn-white {{ $limit == 250 ? 'active' : '' }}">250</a>
      </div>
      <a href="{{ route('acl.groups') }}" class="btn atom-btn-white ms-2">
        <i class="fas fa-arrow-left me-1"></i> Back to ACL
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Entries ({{ $entries->count() }})</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>User</th>
              <th>Action</th>
              <th>Category</th>
              <th>Object Type</th>
              <th>Object ID</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            @forelse($entries as $entry)
              <tr>
                <td>
                  <small>{{ $entry->created_at ?? '—' }}</small>
                </td>
                <td>{{ $entry->display_name ?? $entry->user_name ?? '—' }}</td>
                <td><code>{{ $entry->action }}</code></td>
                <td>
                  @php
                    $catClass = match(strtolower($entry->action_category ?? 'access')) {
                      'access' => 'bg-info text-dark',
                      'security' => 'bg-danger',
                      'admin' => 'bg-warning text-dark',
                      'auth' => 'bg-primary',
                      default => 'bg-secondary',
                    };
                  @endphp
                  <span class="badge {{ $catClass }}">{{ $entry->action_category ?? 'access' }}</span>
                </td>
                <td>{{ $entry->object_type ?? '—' }}</td>
                <td>{{ $entry->object_id ?? '—' }}</td>
                <td><code>{{ $entry->ip_address ?? '—' }}</code></td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-4">No audit log entries found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
