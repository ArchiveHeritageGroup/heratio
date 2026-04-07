@extends('theme::layouts.1col')

@section('title', __('Spectrum Workflow'))

@section('content')

@php
use Illuminate\Support\Facades\DB;

// Get workflow config for current procedure
$procedureType = $procedureType ?? request('procedure_type', '');

$workflowConfig = DB::table('spectrum_workflow_config')
    ->where('procedure_type', $procedureType)
    ->where('is_active', 1)
    ->first();

$configData = $workflowConfig ? json_decode($workflowConfig->config_json, true) : null;
$steps = $configData['steps'] ?? [];
$states = $configData['states'] ?? [];
$transitions = $configData['transitions'] ?? [];

// Get current state for this object/procedure
$currentState = DB::table('spectrum_workflow_state')
    ->where('record_id', $resource->id ?? 0)
    ->where('procedure_type', $procedureType)
    ->first();

$currentStateName = $currentState->current_state ?? ($configData['initial_state'] ?? 'pending');

// Get workflow history
$history = DB::table('spectrum_workflow_history')
    ->where('record_id', $resource->id ?? 0)
    ->where('procedure_type', $procedureType)
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

// Get available transitions from current state
$availableTransitions = [];
foreach ($transitions as $transKey => $transDef) {
    if (isset($transDef['from']) && in_array($currentStateName, $transDef['from'])) {
        $availableTransitions[$transKey] = $transDef;
    }
}

// Get users for assignment
$users = DB::table('user')
    ->whereNotNull('username')
    ->where('username', '!=', '')
    ->select('id', 'username', 'email')
    ->orderBy('username')
    ->get();

// Fallback
if ($users->isEmpty()) {
    $users = DB::table('actor')
        ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
        ->join('user', 'actor.id', '=', 'user.id')
        ->where('actor_i18n.culture', 'en')
        ->whereNotNull('actor_i18n.authorized_form_of_name')
        ->select('user.id', 'actor_i18n.authorized_form_of_name')
        ->orderBy('actor_i18n.authorized_form_of_name')
        ->get();
}

$canEdit = auth()->check() && auth()->user()->is_admin;
@endphp

<h1>{{ __('Spectrum Workflow') }}: {{ $resource->title ?? $resource->slug ?? '' }}</h1>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ url('/informationobject/' . ($resource->slug ?? '')) }}">{{ $resource->title ?? $resource->slug ?? '' }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('ahgspectrum.index') }}?slug={{ $resource->slug ?? '' }}">{{ __('Spectrum') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Workflow') }}</li>
    </ol>
</nav>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">{{ __('Procedures') }}</h5>
            </div>
            <ul class="list-group list-group-flush">
                @foreach ($procedures ?? [] as $procId => $procDef)
                @php $isActive = $procedureType === $procId; @endphp
                <li class="list-group-item {{ $isActive ? 'active' : '' }}">
                    <a href="{{ route('ahgspectrum.workflow') }}?slug={{ $resource->slug ?? '' }}&procedure_type={{ $procId }}"
                       class="{{ $isActive ? 'text-white' : '' }} text-decoration-none d-block">
                        <i class="fa {{ $procDef['icon'] ?? 'fa-circle' }} me-2"></i>
                        {{ $procDef['label'] }}
                    </a>
                </li>
                @endforeach
            </ul>
        </div>

        <a href="{{ url('/informationobject/' . ($resource->slug ?? '')) }}" class="btn btn-secondary w-100">
            <i class="fas fa-arrow-left me-1"></i> {{ __('Back to record') }}
        </a>
    </div>

    <div class="col-md-9">
        @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if ($resource)
        <!-- Linked Record -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">{{ __('Linked Record') }}</h6>
            </div>
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-secondary me-2">{{ __('Item-Level Procedure') }}</span>
                        <strong>{{ $resource->title ?? $resource->slug ?? '' }}</strong>
                    </div>
                    <a href="{{ url('/informationobject/' . ($resource->slug ?? '')) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i> {{ __('Back to record') }}
                    </a>
                </div>
            </div>
        </div>
        @endif

        @if ($workflowConfig)

        <!-- Current Status Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $workflowConfig->name }}</h5>
                <span class="badge bg-primary fs-6">{{ ucwords(str_replace('_', ' ', $currentStateName)) }}</span>
            </div>
            <div class="card-body">
                <!-- Steps Progress -->
                @if (!empty($steps))
                <div class="mb-4">
                    <h6>{{ __('Steps') }}</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @php
                        $stateIndex = array_search($currentStateName, $states);
                        @endphp
                        @foreach ($steps as $index => $step)
                            @php
                            $stepStatus = 'pending';
                            if ($index < $stateIndex) $stepStatus = 'completed';
                            elseif ($index == $stateIndex) $stepStatus = 'current';

                            $badgeClass = match($stepStatus) {
                                'completed' => 'bg-success',
                                'current' => 'bg-warning',
                                default => 'bg-secondary'
                            };
                            @endphp
                        <div class="text-center">
                            <span class="badge {{ $badgeClass }} d-block mb-1" style="min-width: 30px;">
                                {{ $step['order'] }}
                            </span>
                            <small class="d-block" style="max-width: 80px; font-size: 0.7rem;">
                                {{ $step['name'] }}
                            </small>
                        </div>
                        @if ($index < count($steps) - 1)
                        <div class="d-flex align-items-center" style="margin-top: -15px;">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Available Actions -->
                @if ($canEdit && !empty($availableTransitions))
                <div class="mb-3">
                    <h6>{{ __('Available Actions') }}</h6>
                    <form method="post" action="{{ route('ahgspectrum.workflow-transition') }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="slug" value="{{ $resource->slug ?? '' }}">
                        <input type="hidden" name="procedure_type" value="{{ $procedureType }}">
                        <input type="hidden" name="from_state" value="{{ $currentStateName }}">

                        <div class="col-md-4">
                            <label class="form-label">{{ __('Action') }}</label>
                            <select name="transition_key" class="form-select" required>
                                <option value="">{{ __('Select action...') }}</option>
                                @foreach ($availableTransitions as $transKey => $transDef)
                                @php $isRestart = ($transKey === 'restart'); @endphp
                                <option value="{{ $transKey }}" data-to-state="{{ $transDef['to'] }}">
                                    @if ($isRestart)
                                        &#x21bb; {{ __('Restart') }} &rarr; {{ ucwords(str_replace('_', ' ', $transDef['to'])) }}
                                    @else
                                        {{ ucwords(str_replace('_', ' ', $transKey)) }} &rarr; {{ ucwords(str_replace('_', ' ', $transDef['to'])) }}
                                    @endif
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">{{ __('Assign to') }}</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">{{ __('Unassigned') }}</option>
                                @foreach ($users as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->username ?? $user->authorized_form_of_name ?? '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <input type="text" name="note" class="form-control" placeholder="{{ __('Optional') }}">
                        </div>

                        <div class="col-12">
                            @php
                            $hasRestart = isset($availableTransitions['restart']);
                            $hasOnlyRestart = $hasRestart && count($availableTransitions) === 1;
                            @endphp
                            <button type="submit" class="btn {{ $hasOnlyRestart ? 'btn-warning' : 'btn-primary' }}">
                                @if ($hasOnlyRestart)
                                <i class="fas fa-redo me-1"></i> {{ __('Restart Procedure') }}
                                @else
                                <i class="fas fa-play me-1"></i> {{ __('Execute Action') }}
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
                @elseif (!$canEdit)
                <div class="alert alert-info">
                    {{ __('You do not have permission to modify this workflow.') }}
                </div>
                @else
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ __('This procedure has been completed.') }}
                </div>
                @endif
            </div>
        </div>

        <!-- History -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">{{ __('Activity History') }}</h6>
            </div>
            <div class="card-body">
                @if ($history->isEmpty())
                <div class="alert alert-info mb-0">
                    {{ __('No activity recorded yet. Use the actions above to start the workflow.') }}
                </div>
                @else
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('From') }}</th>
                            <th>{{ __('To') }}</th>
                            <th>{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $event)
                        <tr>
                            <td><small>{{ $event->created_at }}</small></td>
                            <td>
                                @if ($event->transition_key === 'restart')
                                <span class="text-warning"><i class="fas fa-redo me-1"></i>{{ __('Restart') }}</span>
                                @else
                                {{ ucwords(str_replace('_', ' ', $event->transition_key)) }}
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $event->from_state)) }}</span></td>
                            <td><span class="badge bg-primary">{{ ucwords(str_replace('_', ' ', $event->to_state)) }}</span></td>
                            <td><small>{{ $event->note ?? '' }}</small></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        @else
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            {{ __('No workflow configuration found for this procedure type.') }}
            <br><small>{{ __('An administrator needs to configure workflow steps for: ') }}{{ $procedureType }}</small>
        </div>
        @endif
    </div>
</div>

@endsection
