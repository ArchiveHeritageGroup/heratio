@extends('theme::layouts.1col')

@section('title', __('My Tasks'))

@section('content')

@php
$proceduresRaw = $procedures ?? [];
$workflowConfigsRaw = $workflowConfigs ?? [];
$tasksRaw = $tasks ?? [];
$procedureTypesRaw = $procedureTypes ?? [];
$unreadCount = $unreadCount ?? 0;
$currentFilter = $currentFilter ?? '';
@endphp

<h1>
    <i class="fas fa-clipboard-list me-2"></i>{{ __('My Tasks') }}
    @if ($unreadCount > 0)
    <span class="badge bg-danger ms-2">{{ $unreadCount }} {{ __('new') }}</span>
    @endif
</h1>

<div class="row">
    <!-- Sidebar -->
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>{{ __('Filter') }}</h5>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Procedure Type') }}</label>
                        <select name="procedure_type" class="form-select">
                            <option value="">{{ __('All procedures') }}</option>
                            @foreach ($procedureTypesRaw as $type)
                            <option value="{{ $type }}" {{ $currentFilter === $type ? 'selected' : '' }}>
                                {{ $proceduresRaw[$type]['label'] ?? ucwords(str_replace('_', ' ', $type)) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>{{ __('Filter') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Summary') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Total Tasks') }}</span>
                    <span class="badge bg-primary">{{ count($tasksRaw) }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Links') }}</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="{{ route('spectrum.dashboard') }}" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i>{{ __('Workflow Dashboard') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-9">
        @if (empty($tasksRaw))
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">{{ __('No tasks assigned') }}</h4>
                <p class="text-muted">{{ __('You have no pending tasks at this time.') }}</p>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Assigned Tasks') }}</h5>
                <span class="badge bg-primary">{{ count($tasksRaw) }} {{ __('tasks') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Object') }}</th>
                            <th>{{ __('Procedure') }}</th>
                            <th>{{ __('State') }}</th>
                            <th>{{ __('Assigned') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasksRaw as $task)
                        @php
                            $procedureLabel = $proceduresRaw[$task->procedure_type]['label'] ?? ucwords(str_replace('_', ' ', $task->procedure_type));
                            $stateLabel = $task->current_state;
                            if (isset($workflowConfigsRaw[$task->procedure_type]['state_labels'][$task->current_state])) {
                                $stateLabel = $workflowConfigsRaw[$task->procedure_type]['state_labels'][$task->current_state];
                            } else {
                                $stateLabel = ucwords(str_replace('_', ' ', $task->current_state));
                            }

                            $stateBadge = 'secondary';
                            $state = $task->current_state;
                            if (strpos($state, 'completed') !== false || strpos($state, 'approved') !== false) {
                                $stateBadge = 'success';
                            } elseif (strpos($state, 'review') !== false || strpos($state, 'pending') !== false) {
                                $stateBadge = 'warning';
                            } elseif (strpos($state, 'progress') !== false || strpos($state, 'active') !== false) {
                                $stateBadge = 'primary';
                            } elseif (strpos($state, 'reject') !== false || strpos($state, 'cancel') !== false) {
                                $stateBadge = 'danger';
                            }
                        @endphp
                        <tr>
                            <td>
                                <a href="/{{ $task->slug }}" class="text-decoration-none">
                                    <strong>{{ $task->object_title ?: $task->identifier ?: 'Untitled' }}</strong>
                                </a>
                                @if ($task->identifier)
                                <br><small class="text-muted">{{ $task->identifier }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $procedureLabel }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $stateBadge }}">{{ $stateLabel }}</span>
                            </td>
                            <td>
                                @if ($task->assigned_at)
                                <small>
                                    {{ date('d M Y H:i', strtotime($task->assigned_at)) }}
                                    @if ($task->assigned_by_name)
                                    <br><span class="text-muted">{{ __('by') }} {{ $task->assigned_by_name }}</span>
                                    @endif
                                </small>
                                @endif
                            </td>
                            <td>
                                <a href="/spectrum/{{ $task->slug }}/workflow?procedure_type={{ urlencode($task->procedure_type) }}"
                                   class="btn btn-sm btn-outline-primary" title="{{ __('View Workflow') }}">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
