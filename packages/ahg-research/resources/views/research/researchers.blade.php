{{-- Admin: Manage Researchers - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'researchers'])
@endsection

@section('title')
  <h1><i class="fas fa-users text-primary me-2"></i>Researchers</h1>
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <h1 class="h2 mb-4"><i class="fas fa-users text-primary me-2"></i>Manage Researchers</h1>

  {{-- Status Filter Pills --}}
  @php $cs = $filter ?? 'all'; @endphp
  <ul class="nav nav-pills mb-4">
    <li class="nav-item">
      <a class="nav-link {{ $cs === 'all' ? 'active' : '' }}" href="{{ route('research.researchers') }}">
        All <span class="badge bg-{{ $cs === 'all' ? 'white text-primary' : 'secondary' }} ms-1">{{ (int) ($counts['all'] ?? 0) }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $cs === 'pending' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'pending']) }}">
        Pending <span class="badge bg-{{ $cs === 'pending' ? 'white text-primary' : 'warning text-dark' }} ms-1">{{ (int) ($counts['pending'] ?? 0) }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $cs === 'approved' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'approved']) }}">
        Approved <span class="badge bg-{{ $cs === 'approved' ? 'white text-primary' : 'success' }} ms-1">{{ (int) ($counts['approved'] ?? 0) }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $cs === 'suspended' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'suspended']) }}">
        Suspended <span class="badge bg-{{ $cs === 'suspended' ? 'white text-primary' : 'danger' }} ms-1">{{ (int) ($counts['suspended'] ?? 0) }}</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $cs === 'expired' ? 'active' : '' }}" href="{{ route('research.researchers', ['filter' => 'expired']) }}">
        Expired <span class="badge bg-{{ $cs === 'expired' ? 'white text-primary' : 'secondary' }} ms-1">{{ (int) ($counts['expired'] ?? 0) }}</span>
      </a>
    </li>
  </ul>

  {{-- Card with header containing status dropdown + search + count --}}
  <div class="card">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-md-6">
          <form method="get" action="{{ route('research.researchers') }}" class="d-flex gap-2">
            <select name="filter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
              <option value="all">All Status</option>
              <option value="pending" {{ $cs === 'pending' ? 'selected' : '' }}>Pending</option>
              <option value="approved" {{ $cs === 'approved' ? 'selected' : '' }}>Approved</option>
              <option value="suspended" {{ $cs === 'suspended' ? 'selected' : '' }}>Suspended</option>
              <option value="rejected" {{ $cs === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="{{ e($query ?? '') }}" style="width: 200px;">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
          </form>
        </div>
        <div class="col-md-6 text-end">
          <span class="text-muted">{{ is_countable($researchers) ? count($researchers) : (is_object($researchers) && method_exists($researchers, 'total') ? $researchers->total() : 0) }} researchers</span>
        </div>
      </div>
    </div>

    {{-- Researchers Table --}}
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Institution</th>
            <th>Status</th>
            <th>Registered</th>
            <th width="100"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($researchers ?? [] as $r)
            <tr>
              <td>
                <strong>{{ e($r->title ? $r->title . ' ' : '') }}{{ e($r->first_name . ' ' . $r->last_name) }}</strong>
              </td>
              <td><a href="mailto:{{ e($r->email) }}">{{ e($r->email) }}</a></td>
              <td>{{ e($r->institution ?? '-') }}</td>
              <td>
                @php
                  $sc = ['approved' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'expired' => 'secondary', 'rejected' => 'danger'];
                @endphp
                <span class="badge bg-{{ $sc[$r->status] ?? 'secondary' }}">{{ ucfirst(e($r->status ?? 'unknown')) }}</span>
              </td>
              <td><small>{{ $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('Y-m-d') : '-' }}</small></td>
              <td>
                <a href="{{ route('research.viewResearcher', $r->id) }}" class="btn btn-sm btn-outline-primary">
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
              <td colspan="6" class="text-center text-muted py-4">No researchers found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if(is_object($researchers) && method_exists($researchers, 'links'))
    <div class="d-flex justify-content-center mt-3">
      {{ $researchers->appends(request()->query())->links() }}
    </div>
  @endif
@endsection
