@extends('theme::layouts.1col')

@section('title', 'Interlibrary Loans')

@section('content')
<div class="container py-4">

  {{-- Header ─────────────────────────────────────────────────────────── --}}
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="mb-0">
      <i class="fas fa-globe me-2"></i>{{ __('Interlibrary Loans') }}
    </h1>
    <a href="{{ route('library.ill-create') }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>{{ __('New ILL Request') }}
    </a>
  </div>

  {{-- Settings link (Tipasa / OCLC config) ─────────────────────────── --}}
  @if(auth()->check() && auth()->user()->isAdministrator())
  <div class="mb-3 text-end">
    <a href="{{ route('library.ill-settings') }}" class="text-muted small">
      <i class="fas fa-cog me-1"></i>{{ __('ILL Settings') }}
    </a>
  </div>
  @endif

  {{-- Status / type filter tabs ────────────────────────────────────── --}}
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link @if(!$status_filter) active @endif"
         href="{{ route('library.ill') }}">{{ __('All') }}</a>
    </li>
    @foreach($status_counts as $status => $count)
    @if($count > 0)
    <li class="nav-item">
      <a class="nav-link @if($status_filter == $status) active @endif"
         href="{{ route('library.ill', ['status' => $status]) }}">
        {{ ucfirst($status) }}
        <span class="badge bg-secondary ms-1">{{ $count }}</span>
      </a>
    </li>
    @endif
    @endforeach
    <li class="nav-item">
      <a class="nav-link @if($type_filter == 'borrow') active @endif"
         href="{{ route('library.ill', ['type' => 'borrow']) }}">
        {{ __('Borrow') }}
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link @if($type_filter == 'lend') active @endif"
         href="{{ route('library.ill', ['type' => 'lend']) }}">
        {{ __('Lend') }}
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-warning @if($overdue_filter) active @endif"
         href="{{ route('library.ill', ['overdue_only' => 1]) }}">
        <i class="fas fa-exclamation-triangle me-1"></i>{{ __('Overdue') }}
        @if($overdue_count > 0)<span class="badge bg-warning text-dark ms-1">{{ $overdue_count }}</span>@endif
      </a>
    </li>
  </ul>

  {{-- Search ───────────────────────────────────────────────────────── --}}
  <form method="get" action="{{ route('library.ill') }}" class="row g-2 mb-4">
    @if($status_filter)
      <input type="hidden" name="status" value="{{ $status_filter }}">
    @endif
    @if($type_filter)
      <input type="hidden" name="type" value="{{ $type_filter }}">
    @endif
    @if($overdue_filter)
      <input type="hidden" name="overdue_only" value="1">
    @endif
    <div class="col-md-6">
      <input type="text" name="q" value="{{ e($search_query ?? '') }}"
             placeholder="{{ __('Search by ILL #, title, author, ISBN, library…') }}"
             class="form-control">
    </div>
    <div class="col-md-2">
      <select name="type" class="form-select">
        <option value="">{{ __('All types') }}</option>
        <option value="borrow" @if(($type_filter ?? '') === 'borrow') selected @endif>{{ __('Borrow') }}</option>
        <option value="lend"   @if(($type_filter ?? '') === 'lend')   selected @endif>{{ __('Lend') }}</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">{{ __('All statuses') }}</option>
        @foreach($all_statuses as $s)
          <option value="{{ $s }}" @if($status_filter === $s) selected @endif>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button type="submit" class="btn btn-secondary">
        <i class="fas fa-search me-1"></i>{{ __('Filter') }}
      </button>
    </div>
  </form>

  {{-- Request table ─────────────────────────────────────────────────── --}}
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('ILL #') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Author') }}</th>
            <th>{{ __('Library') }}</th>
            <th>{{ __('Patron') }}</th>
            <th>{{ __('Requested') }}</th>
            <th>{{ __('Due') }}</th>
            <th>{{ __('Status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        @forelse($requests as $r)
          <tr class="{{ $r->status === 'overdue' ? 'table-warning' : '' }}">
            <td>
              <a href="{{ route('library.ill-view', $r->id) }}">
                {{ e($r->ill_number) }}
              </a>
              @if($r->opac_suppress)
                <span class="badge bg-dark ms-1" title="Suppressed from OPAC">S</span>
              @endif
            </td>
            <td>
              <span class="badge {{ $r->type === 'borrow' ? 'bg-primary' : 'bg-success' }}">
                {{ ucfirst($r->type) }}
              </span>
            </td>
            <td>{{ e($r->title) }}</td>
            <td>{{ e($r->author) }}</td>
            <td>{{ e($r->library_name) }}</td>
            <td>
              @if($r->patron_id)
                <a href="{{ route('library.patron-view', $r->patron_id) }}">
                  {{ e($r->patron_id) }}
                </a>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>{{ $r->request_date }}</td>
            <td>
              @if($r->due_date)
                <span class="{{ $r->status === 'overdue' ? 'text-danger' : '' }}">
                  {{ $r->due_date }}
                </span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              {!! $statusBadge($r->status) !!}
            </td>
            <td class="text-end">
              <a href="{{ route('library.ill-view', $r->id) }}"
                 class="btn btn-sm btn-outline-primary">
                <i class="fas fa-eye"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="text-muted text-center py-4">
              No ILL requests found.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Quick stats footer ─────────────────────────────────────────────── --}}
  <div class="row mt-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="text-muted small">{{ __('Borrow requests') }}</div>
          <div class="fs-4 fw-bold">{{ $borrow_count }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="text-muted small">{{ __('Lend requests') }}</div>
          <div class="fs-4 fw-bold">{{ $lend_count }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="text-muted small">{{ __('Pending action') }}</div>
          <div class="fs-4 fw-bold">{{ $pending_count }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center {{ $overdue_count > 0 ? 'border-danger' : '' }}">
        <div class="card-body py-2">
          <div class="text-muted small">{{ __('Overdue') }}</div>
          <div class="fs-4 fw-bold text-danger">{{ $overdue_count }}</div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

{{-- Blade helper: status badge ───────────────────────────────────────── --}}
@php
if (!function_exists('statusBadge')) {
  function statusBadge(string $status): string {
    $classes = [
      'pending'    => 'bg-secondary',
      'requested'  => 'bg-info',
      'shipped'    => 'bg-primary',
      'lost'       => 'bg-danger',
      'received'   => 'bg-success',
      'returned'   => 'bg-success',
      'cancelled'  => 'bg-dark',
      'overdue'    => 'bg-warning text-dark',
      'unfulfilled'=> 'bg-dark',
    ];
    $label = ucfirst(str_replace('_', ' ', $status));
    $class = $classes[$status] ?? 'bg-secondary';
    return "<span class=\"badge {$class}\">{$label}</span>";
  }
}
@endphp