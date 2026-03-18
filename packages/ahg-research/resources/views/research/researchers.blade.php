{{-- Admin: Manage Researchers - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'researchers'])
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <h1 class="mb-4"><i class="fas fa-user-check me-2"></i>Manage Researchers</h1>

  {{-- Status Filter Tabs --}}
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link {{ ($filter ?? 'all') === 'all' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'all']) }}">
        All <span class="badge bg-secondary">{{ $counts['all'] ?? 0 }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($filter ?? '') === 'pending' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'pending']) }}">
        Pending <span class="badge bg-warning text-dark">{{ $counts['pending'] ?? 0 }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($filter ?? '') === 'approved' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'approved']) }}">
        Approved <span class="badge bg-success">{{ $counts['approved'] ?? 0 }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($filter ?? '') === 'suspended' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'suspended']) }}">
        Suspended <span class="badge bg-danger">{{ $counts['suspended'] ?? 0 }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($filter ?? '') === 'expired' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'expired']) }}">
        Expired <span class="badge bg-secondary">{{ $counts['expired'] ?? 0 }}</span>
      </a>
    </li>
  </ul>

  {{-- Search --}}
  <form action="{{ route('research.researchers') }}" method="GET" class="mb-3">
    <input type="hidden" name="filter" value="{{ $filter ?? 'all' }}">
    <div class="input-group">
      <input type="text" name="q" class="form-control" placeholder="Search researchers by name, email, or institution..." value="{{ e($query ?? '') }}">
      <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>

  {{-- Researchers Table --}}
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Institution</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($researchers ?? [] as $r)
          <tr>
            <td>{{ e($r->title ?? '') }} {{ e($r->first_name) }} {{ e($r->last_name) }}</td>
            <td>{{ e($r->email) }}</td>
            <td>{{ e($r->institution ?? '-') }}</td>
            <td>
              @php
                $sc = ['approved' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'expired' => 'secondary', 'rejected' => 'danger'];
              @endphp
              <span class="badge bg-{{ $sc[$r->status] ?? 'secondary' }}">{{ ucfirst(e($r->status ?? 'unknown')) }}</span>
            </td>
            <td>{{ $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('Y-m-d') : '-' }}</td>
            <td>
              <a href="{{ route('research.viewResearcher', $r->id) }}" class="btn btn-sm btn-outline-primary" title="View">
                <i class="fas fa-eye"></i>
              </a>
              @if(($r->status ?? '') === 'pending')
                <form action="{{ route('research.researchers.approve', $r->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-success" title="Approve">
                    <i class="fas fa-check"></i>
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-muted text-center">No researchers found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($researchers ?? collect(), 'links'))
    <div class="d-flex justify-content-center">
      {{ $researchers->appends(request()->query())->links() }}
    </div>
  @endif
@endsection
