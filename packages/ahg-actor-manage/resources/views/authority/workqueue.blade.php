@extends('theme::layouts.1col')

@section('title', 'Authority Workqueue')
@section('body-class', 'authority workqueue')

@section('content')

@php
  $items = $workqueue['data'] ?? [];
  $total = $workqueue['total'] ?? 0;
  $lastPage = $workqueue['last_page'] ?? 1;
  $curPage = $workqueue['current_page'] ?? 1;
  $levelColors = ['stub' => 'danger', 'minimal' => 'warning', 'partial' => 'info', 'full' => 'success'];
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Workqueue</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-tasks me-2"></i>Authority Workqueue</h1>

{{-- Filters --}}
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="{{ route('actor.workqueue') }}" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label">{{ __('Level') }}</label>
        <select name="level" class="form-select form-select-sm">
          <option value="">{{ __('All') }}</option>
          @foreach ($levels as $lvl)
            <option value="{{ $lvl }}" {{ ($filters['level'] ?? '') === $lvl ? 'selected' : '' }}>
              {{ ucfirst($lvl) }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Assigned to') }}</label>
        <select name="assigned_to" class="form-select form-select-sm">
          <option value="">{{ __('All') }}</option>
          @foreach ($users as $u)
            <option value="{{ $u->id }}" {{ ($filters['assigned_to'] ?? '') == $u->id ? 'selected' : '' }}>
              {{ $u->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Max score') }}</label>
        <input type="number" name="max_score" class="form-control form-control-sm"
               value="{{ $filters['max_score'] ?? '' }}" min="0" max="100">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm atom-btn-white">
          <i class="fas fa-filter me-1"></i>Filter
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Results --}}
<div class="card">
  <div class="card-header d-flex justify-content-between" style="background: var(--ahg-primary); color: #fff;">
    <span>{{ number_format($total) }} record(s)</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-striped mb-0">
      <thead>
        <tr>
          <th>{{ __('Name') }}</th>
          <th>{{ __('Level') }}</th>
          <th class="text-center">{{ __('Score') }}</th>
          <th class="text-center">{{ __('IDs') }}</th>
          <th class="text-center">{{ __('Rels') }}</th>
          <th>{{ __('Assigned to') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($items))
          <tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>
        @else
          @foreach ($items as $item)
            @php $item = (object) $item; @endphp
            <tr>
              <td>
                <a href="{{ route('actor.identifiers', ['actorId' => $item->actor_id]) }}">
                  {{ $item->name ?? 'Actor #' . $item->actor_id }}
                </a>
              </td>
              <td>
                <span class="badge bg-{{ $levelColors[$item->completeness_level] ?? 'secondary' }}">
                  {{ ucfirst($item->completeness_level) }}
                </span>
              </td>
              <td class="text-center">{{ $item->completeness_score }}%</td>
              <td class="text-center">
                {!! $item->has_external_ids ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' !!}
              </td>
              <td class="text-center">
                {!! $item->has_relations ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' !!}
              </td>
              <td>
                @if ($item->assigned_to)
                  <small class="text-muted">#{{ $item->assigned_to }}</small>
                @else
                  <small class="text-muted">Unassigned</small>
                @endif
              </td>
              <td>
                @if ($item->slug)
                  <a href="{{ route('actor.show', $item->slug) }}" class="btn btn-sm atom-btn-white" title="{{ __('View') }}">
                    <i class="fas fa-eye"></i>
                  </a>
                @endif
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>
  @if ($lastPage > 1)
    <div class="card-footer">
      <nav>
        <ul class="pagination pagination-sm mb-0 justify-content-center">
          @for ($p = 1; $p <= $lastPage; $p++)
            <li class="page-item{{ $p == $curPage ? ' active' : '' }}">
              <a class="page-link" href="{{ route('actor.workqueue', array_merge($filters, ['page' => $p])) }}">{{ $p }}</a>
            </li>
          @endfor
        </ul>
      </nav>
    </div>
  @endif
</div>

@endsection
