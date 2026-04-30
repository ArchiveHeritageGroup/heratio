{{-- User Clearance Detail - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/userClearanceSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'User Clearance: ' . e($targetUser->username ?? ''))

@section('content')

<h1><i class="fas fa-user-shield"></i> User Clearance: {{ e($targetUser->username ?? '') }}</h1>

<div class="mb-3">
  <a href="{{ route('acl.security-index') }}" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left"></i> Back to Clearances
  </a>
</div>

<div class="row">
  <div class="col-md-8">
    {{-- Current Clearance --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Current Clearance') }}</h5>
      </div>
      <div class="card-body">
        @if($clearance ?? null)
        <div class="row">
          <div class="col-md-6">
            <p>
              <strong>Level:</strong><br>
              <span class="badge fs-5" style="background-color: {{ $clearance->color ?? '#666' }}">
                {{ e($clearance->name ?? '') }}
              </span>
            </p>
            <p>
              <strong>Granted:</strong><br>
              {{ $clearance->granted_date ?? '' }}
            </p>
            <p>
              <strong>Expires:</strong><br>
              @if($clearance->expiry_date ?? null)
                @php
                $daysLeft = (strtotime($clearance->expiry_date) - time()) / 86400;
                $class = $daysLeft <= 7 ? 'text-danger' : ($daysLeft <= 30 ? 'text-warning' : 'text-success');
                @endphp
                <span class="{{ $class }}">{{ $clearance->expiry_date }}</span>
                ({{ round($daysLeft) }} days)
              @else
                <span class="text-muted">No expiry</span>
              @endif
            </p>
          </div>
          <div class="col-md-6">
            <p>
              <strong>Vetting Reference:</strong><br>
              {{ e($clearance->vetting_reference ?? '-') }}
            </p>
            <p>
              <strong>Vetting Authority:</strong><br>
              {{ e($clearance->vetting_authority ?? '-') }}
            </p>
            <p>
              <strong>2FA Verified:</strong><br>
              @if($clearance->two_factor_verified ?? false)
                <span class="badge bg-success">Yes</span>
                <small class="text-muted">({{ $clearance->two_factor_verified_at ?? '' }})</small>
              @else
                <span class="badge bg-warning">No</span>
              @endif
            </p>
          </div>
        </div>

        @if(($clearance->renewal_status ?? '') === 'pending')
        <div class="alert alert-warning">
          <strong>Renewal Requested:</strong> {{ $clearance->renewal_requested_date ?? '' }}
          <form action="{{ route('acl.set-clearance') }}" method="post" class="mt-2">
            @csrf
            <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
            <input type="hidden" name="action_type" value="approve_renewal">
            <div class="row">
              <div class="col-md-4">
                <input type="date" name="expiry_date" class="form-control"
                       value="{{ date('Y-m-d', strtotime('+1 year')) }}">
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-success">{{ __('Approve Renewal') }}</button>
              </div>
            </div>
          </form>
        </div>
        @endif

        @else
        <p class="text-muted">No active clearance.</p>
        @endif
      </div>
    </div>

    {{-- Grant/Update Clearance --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">{{ ($clearance ?? null) ? 'Update Clearance' : 'Grant Clearance' }}</h5>
      </div>
      <div class="card-body">
        <form action="{{ route('acl.set-clearance') }}" method="post">
          @csrf
          <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
          <input type="hidden" name="action_type" value="grant">

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">{{ __('Clearance Level *') }}</label>
                <select name="classification_id" class="form-select" required>
                  @foreach($classifications ?? [] as $level)
                  <option value="{{ $level->id }}"
                          {{ ($clearance && ($clearance->classification_id ?? null) == $level->id) ? 'selected' : '' }}>
                    {{ e($level->name) }}
                  </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">{{ __('Expiry Date') }}</label>
                <input type="date" name="expiry_date" class="form-control"
                       value="{{ $clearance->expiry_date ?? '' }}">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">{{ __('Vetting Reference') }}</label>
                <input type="text" name="vetting_reference" class="form-control"
                       value="{{ e($clearance->vetting_reference ?? '') }}">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">{{ __('Vetting Date') }}</label>
                <input type="date" name="vetting_date" class="form-control"
                       value="{{ $clearance->vetting_date ?? '' }}">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">{{ __('Vetting Authority') }}</label>
                <input type="text" name="vetting_authority" class="form-control"
                       value="{{ e($clearance->vetting_authority ?? '') }}">
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" class="form-control" rows="2">{{ e($clearance->notes ?? '') }}</textarea>
          </div>

          <button type="submit" class="btn btn-primary">{{ __('Save Clearance') }}</button>

          @if($clearance ?? null)
          <form action="{{ route('acl.set-clearance') }}" method="post" style="display:inline;"
                onsubmit="return confirm('Are you sure you want to revoke this clearance?');">
            @csrf
            <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
            <input type="hidden" name="action_type" value="revoke">
            <button type="submit" class="btn btn-danger">
              {{ __('Revoke Clearance') }}
            </button>
          </form>
          @endif
        </form>
      </div>
    </div>

    {{-- Compartment Access --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Compartment Access') }}</h5>
      </div>
      <div class="card-body">
        @if(empty($compartments))
        <p class="text-muted">No compartment access granted.</p>
        @else
        <table class="table table-sm">
          <thead>
            <tr>
              <th>{{ __('Compartment') }}</th>
              <th>{{ __('Granted') }}</th>
              <th>{{ __('Expires') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($compartments as $comp)
            <tr>
              <td>
                <strong>{{ e($comp->code ?? '') }}</strong> -
                {{ e($comp->name ?? '') }}
              </td>
              <td>{{ $comp->granted_date ?? '' }}</td>
              <td>{{ $comp->expiry_date ?? '-' }}</td>
              <td>
                <form action="{{ route('acl.set-clearance') }}" method="post" style="display:inline;"
                      onsubmit="return confirm('Revoke compartment access?');">
                  @csrf
                  <input type="hidden" name="action_type" value="revoke_compartment">
                  <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
                  <input type="hidden" name="compartment_id" value="{{ $comp->compartment_id ?? $comp->id ?? '' }}">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    {{ __('Revoke') }}
                  </button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @endif

        <hr>
        <h6>{{ __('Grant Compartment Access') }}</h6>
        <form action="{{ route('acl.set-clearance') }}" method="post" class="row">
          @csrf
          <input type="hidden" name="action_type" value="grant_compartment">
          <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
          <div class="col-md-6">
            <select name="compartment_id" class="form-select">
              @foreach($allCompartments ?? [] as $comp)
              <option value="{{ $comp->id }}">{{ e($comp->code . ' - ' . $comp->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <input type="date" name="expiry_date" class="form-control" placeholder="{{ __('Expiry (optional)') }}">
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-primary">{{ __('Grant') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    {{-- User Info --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">{{ __('User Information') }}</h5>
      </div>
      <div class="card-body">
        <p><strong>Username:</strong><br>{{ e($targetUser->username ?? '') }}</p>
        <p><strong>Email:</strong><br>{{ e($targetUser->email ?? '') }}</p>
        <a href="{{ route('acl.user-security', ['id' => $targetUser->id]) }}" class="btn btn-sm btn-outline-info">
          <i class="fas fa-history"></i> View Audit Log
        </a>
      </div>
    </div>

    {{-- Clearance History --}}
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Clearance History') }}</h5>
      </div>
      <div class="card-body" style="max-height: 400px; overflow-y: auto;">
        @if(empty($history))
        <p class="text-muted">No history.</p>
        @else
        <ul class="list-unstyled">
          @foreach($history as $h)
          <li class="mb-2 pb-2 border-bottom">
            <strong>{{ ucfirst($h->action ?? '') }}</strong>
            <br>
            <small class="text-muted">{{ date('Y-m-d H:i', strtotime($h->created_at ?? '')) }}</small>
            <br>
            @if($h->previous_name ?? null)
              {{ e($h->previous_name) }} &rarr;
            @endif
            {{ e($h->new_name ?? 'None') }}
            <br>
            <small>by {{ e($h->changed_by_name ?? '') }}</small>
            @if($h->reason ?? null)
            <br><small class="text-muted">{{ e($h->reason) }}</small>
            @endif
          </li>
          @endforeach
        </ul>
        @endif
      </div>
    </div>
  </div>
</div>

@endsection
