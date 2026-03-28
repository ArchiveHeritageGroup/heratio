@extends('ahg-theme-b5::layout')

@section('title', 'User Clearance — ' . e($targetUser->authorized_form_of_name ?? $targetUser->username ?? ''))

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.index') }}">Security Clearances</a></li>
    <li class="breadcrumb-item active">{{ e($targetUser->authorized_form_of_name ?? $targetUser->username ?? 'User') }}</li>
  </ol></nav>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <div class="row">
    <div class="col-md-5">
      {{-- Current Clearance --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-shield-alt"></i> Current Clearance</h5></div>
        <div class="card-body">
          @if($clearance)
            <p><span class="badge" style="background-color: {{ $clearance->color ?? '#666' }}; font-size: 1.1em;">{{ e($clearance->classification_name ?? '') }}</span></p>
            <p><strong>Granted:</strong> {{ $clearance->granted_at ?? '' }}</p>
            <p><strong>Expires:</strong> {{ $clearance->expires_at ?? 'Never' }}</p>
            @if(!empty($clearance->notes))
              <p><strong>Notes:</strong> {{ e($clearance->notes) }}</p>
            @endif
          @else
            <p class="text-muted">No clearance assigned.</p>
          @endif
        </div>
      </div>

      {{-- Update Form --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-edit"></i> Update Clearance</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('security-clearance.user-update', ['slug' => $targetUser->slug]) }}">
            @csrf
            <input type="hidden" name="action_type" value="update">
            <div class="mb-3">
              <label class="form-label">Classification Level</label>
              <select name="classification_id" class="form-select" required>
                @foreach($classifications ?? [] as $cl)
                  <option value="{{ $cl->id }}" {{ ($clearance->classification_id ?? 0) == $cl->id ? 'selected' : '' }}
                          style="color: {{ $cl->color ?? '#333' }}">
                    {{ e($cl->name) }} (Level {{ $cl->level }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Expires At</label>
              <input type="date" name="expires_at" class="form-control" value="{{ $clearance->expires_at ?? '' }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Clearance</button>
          </form>
        </div>
      </div>

      {{-- Revoke --}}
      @if($clearance)
      <div class="card mb-3">
        <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="fas fa-ban"></i> Revoke Clearance</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('security-clearance.user-update', ['slug' => $targetUser->slug]) }}"
                onsubmit="return confirm('Are you sure you want to revoke this clearance?')">
            @csrf
            <input type="hidden" name="action_type" value="revoke">
            <div class="mb-3">
              <label class="form-label">Reason for Revocation</label>
              <textarea name="revoke_reason" class="form-control" rows="2" required></textarea>
            </div>
            <button type="submit" class="btn btn-danger">Revoke</button>
          </form>
        </div>
      </div>
      @endif
    </div>

    <div class="col-md-7">
      {{-- History --}}
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-history"></i> Clearance History</h5></div>
        <div class="card-body table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Action</th><th>Level</th><th>By</th><th>Notes</th><th>Date</th></tr></thead>
            <tbody>
              @forelse($history ?? [] as $entry)
              <tr>
                <td>
                  <span class="badge bg-{{ ($entry->action ?? '') === 'grant' ? 'success' : (($entry->action ?? '') === 'revoke' ? 'danger' : 'info') }}">
                    {{ ucfirst($entry->action ?? '') }}
                  </span>
                </td>
                <td>{{ e($entry->classification_name ?? '') }}</td>
                <td>{{ e($entry->performed_by_name ?? '') }}</td>
                <td>{{ e($entry->notes ?? '') }}</td>
                <td>{{ $entry->created_at ?? '' }}</td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-muted">No history.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
