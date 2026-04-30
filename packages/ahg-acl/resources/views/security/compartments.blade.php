{{-- Security Compartments - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/compartmentsSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Security Compartments')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-project-diagram me-2"></i>Security Compartments</h1>
    <a href="{{ route('acl.security-dashboard') }}" class="btn btn-primary">
        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Compartments</h5>
    </div>
    <div class="card-body p-0">
        @if(empty($compartments) || (is_countable($compartments) && count($compartments) === 0))
        <p class="text-muted text-center py-4">No compartments defined</p>
        @else
        <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th class="text-center">{{ __('Users') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compartments as $comp)
                <tr>
                    <td><strong>{{ e($comp->name) }}</strong></td>
                    <td><code>{{ e($comp->code) }}</code></td>
                    <td>{{ e($comp->description ?? '-') }}</td>
                    <td class="text-center">
                        <span class="badge bg-primary">{{ $userCounts[$comp->id] ?? 0 }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@endsection
