@extends('theme::layouts.1col')

@section('title', 'Disposal Queue')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">{{ __('Disposal Queue') }}</h1>
        <a href="{{ route('records.disposal.history') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-history"></i> View History
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md">
            <div class="card text-center border-secondary">
                <div class="card-body py-2">
                    <div class="h4 mb-0">{{ $stats['by_status']['pending'] ?? 0 }}</div>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-info">
                <div class="card-body py-2">
                    <div class="h4 mb-0">{{ $stats['by_status']['recommended'] ?? 0 }}</div>
                    <small class="text-muted">Recommended</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-primary">
                <div class="card-body py-2">
                    <div class="h4 mb-0">{{ $stats['by_status']['approved'] ?? 0 }}</div>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-success">
                <div class="card-body py-2">
                    <div class="h4 mb-0">{{ $stats['by_status']['cleared'] ?? 0 }}</div>
                    <small class="text-muted">Cleared</small>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-dark">
                <div class="card-body py-2">
                    <div class="h4 mb-0">{{ $stats['by_status']['executed'] ?? 0 }}</div>
                    <small class="text-muted">Executed</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('records.disposal.queue') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="filter-status">{{ __('Status') }}</label>
                    <select name="status" id="filter-status" class="form-select form-select-sm">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['pending', 'recommended', 'approved', 'cleared', 'executed', 'rejected', 'cancelled', 'retained'] as $s)
                            <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="filter-action-type">{{ __('Action Type') }}</label>
                    <select name="action_type" id="filter-action-type" class="form-select form-select-sm">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['destroy' => 'Destroy', 'transfer_archives' => 'Transfer to Archives', 'transfer_external' => 'Transfer External', 'retain_permanent' => 'Retain Permanently', 'review' => 'Review'] as $val => $label)
                            <option value="{{ $val }}" {{ ($filters['action_type'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filter-date-from">{{ __('Date From') }}</label>
                    <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filter-date-to">{{ __('Date To') }}</label>
                    <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Queue Table --}}
    <div class="card">
        <div class="card-body p-0">
            @if (count($items) > 0)
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('IO Title') }}</th>
                            <th>{{ __('Action Type') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Initiated By') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->io_title ?? 'Untitled (IO #' . $item->information_object_id . ')' }}</td>
                                <td>
                                    @php
                                        $typeLabels = [
                                            'destroy' => 'Destroy',
                                            'transfer_archives' => 'Transfer to Archives',
                                            'transfer_external' => 'Transfer External',
                                            'retain_permanent' => 'Retain Permanently',
                                            'review' => 'Review',
                                        ];
                                        $typeBadges = [
                                            'destroy' => 'danger',
                                            'transfer_archives' => 'info',
                                            'transfer_external' => 'info',
                                            'retain_permanent' => 'success',
                                            'review' => 'warning',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $typeBadges[$item->action_type] ?? 'secondary' }}">{{ $typeLabels[$item->action_type] ?? $item->action_type }}</span>
                                </td>
                                <td>
                                    @php
                                        $statusBadges = [
                                            'pending' => 'secondary',
                                            'recommended' => 'info',
                                            'approved' => 'primary',
                                            'cleared' => 'success',
                                            'executed' => 'dark',
                                            'rejected' => 'danger',
                                            'cancelled' => 'warning',
                                            'retained' => 'success',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusBadges[$item->status] ?? 'secondary' }}">{{ ucfirst($item->status) }}</span>
                                </td>
                                <td>{{ $item->initiated_by_name ?? 'User #' . $item->initiated_by }}</td>
                                <td>{{ $item->initiated_at ? \Carbon\Carbon::parse($item->initiated_at)->format('Y-m-d H:i') : '' }}</td>
                                <td>
                                    <a href="{{ route('records.disposal.show', $item->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-4 text-center text-muted">No disposal actions found.</div>
            @endif
        </div>
    </div>

    {{-- Pagination --}}
    @if ($total > $perPage)
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                @for ($i = 1; $i <= ceil($total / $perPage); $i++)
                    <li class="page-item {{ $i == $page ? 'active' : '' }}">
                        <a class="page-link" href="{{ route('records.disposal.queue', array_merge($filters, ['page' => $i])) }}">{{ $i }}</a>
                    </li>
                @endfor
            </ul>
        </nav>
    @endif
</div>
@endsection
