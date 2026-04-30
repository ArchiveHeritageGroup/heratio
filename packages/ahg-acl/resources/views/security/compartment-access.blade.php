{{-- Compartment Access - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/compartmentAccessSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Compartment Access')

@section('content')

<h1><i class="fas fa-users"></i> Compartment Access: {{ e($compartment->code ?? '') }}</h1>

<div class="mb-3">
  <a href="{{ route('acl.compartments') }}" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left"></i> {{ __('Back to Compartments') }}
  </a>
</div>

<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">{{ e($compartment->name ?? '') }}</h5>
  </div>
  <div class="card-body">
    @if($compartment->description ?? null)
    <p>{{ e($compartment->description) }}</p>
    @endif
    <p>
      <strong>{{ __('Requires Briefing:') }}</strong>
      {{ ($compartment->requires_briefing ?? false) ? 'Yes' : 'No' }}
    </p>
  </div>
</div>

{{-- Current Access --}}
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between">
    <h5 class="mb-0">{{ __('Users with Access') }}</h5>
    <span class="badge bg-primary">{{ count($users ?? []) }} users</span>
  </div>
  <div class="card-body">
    @if(empty($users))
    <p class="text-muted text-center">No users have access to this compartment.</p>
    @else
    <table class="table table-striped">
      <thead>
        <tr>
          <th>{{ __('User') }}</th>
          <th>{{ __('Granted Date') }}</th>
          <th>{{ __('Expires') }}</th>
          <th>{{ __('Briefing') }}</th>
          <th>{{ __('Granted By') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($users as $user)
        <tr>
          <td>
            <a href="{{ route('acl.view-classification', ['id' => $user->user_id]) }}">
              {{ e($user->username) }}
            </a>
            <br><small class="text-muted">{{ e($user->email ?? '') }}</small>
          </td>
          <td>{{ $user->granted_date ?? '' }}</td>
          <td>
            @if($user->expiry_date ?? null)
              @php $expired = strtotime($user->expiry_date) < time(); @endphp
              <span class="{{ $expired ? 'text-danger' : '' }}">
                {{ $user->expiry_date }}
              </span>
            @else
              <span class="text-muted">{{ __('No expiry') }}</span>
            @endif
          </td>
          <td>
            @if($user->briefing_date ?? null)
              {{ $user->briefing_date }}
              @if($user->briefing_reference ?? null)
              <br><small>{{ e($user->briefing_reference) }}</small>
              @endif
            @else
              <span class="text-muted">-</span>
            @endif
          </td>
          <td>{{ e($user->granted_by_name ?? '-') }}</td>
          <td>
            <form action="{{ route('acl.set-clearance') }}" method="POST" style="display:inline;"
                  onsubmit="return confirm('Revoke access for this user?');">
              @csrf
              <input type="hidden" name="action_type" value="revoke_compartment">
              <input type="hidden" name="user_id" value="{{ $user->user_id }}">
              <input type="hidden" name="compartment_id" value="{{ $compartment->id }}">
              <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-ban"></i> {{ __('Revoke') }}
              </button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

@endsection
