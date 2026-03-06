@extends('theme::layouts.1col')

@section('title', 'User ' . ($user->username ?? 'Profile') . ' - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user show')

@section('content')

  <h1>
    <i class="fas fa-user me-2"></i>
    User {{ $user->username }}
  </h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(!$user->active)
    <div class="alert alert-warning" role="alert">
      <i class="fas fa-exclamation-triangle me-1"></i>
      This user account is <strong>inactive</strong>. The user cannot log in.
    </div>
  @endif

  {{-- User details --}}
  <section class="mb-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> User details</h5>
      </div>
      <div class="card-body">
        <table class="table table-bordered mb-0">
          <tbody>
            <tr>
              <th style="width: 200px;">Username</th>
              <td>
                {{ $user->username }}
                @if(auth()->id() === $user->id)
                  <span class="badge bg-info ms-1">(you)</span>
                @endif
              </td>
            </tr>
            <tr>
              <th>Email</th>
              <td>{{ $user->email }}</td>
            </tr>
            <tr>
              <th>Password</th>
              <td>
                <a href="{{ route('user.password.edit') }}" class="text-decoration-none">
                  <i class="fas fa-key me-1"></i> Reset password
                </a>
              </td>
            </tr>
            <tr>
              <th>User groups</th>
              <td>
                @if($groups->count() > 0)
                  <ul class="list-unstyled mb-0">
                    @foreach($groups as $group)
                      <li>
                        <i class="fas fa-users me-1 text-muted"></i>
                        {{ $group->group_name ?? 'Group #' . $group->group_id }}
                      </li>
                    @endforeach
                  </ul>
                @else
                  <span class="text-muted">No groups assigned</span>
                @endif
              </td>
            </tr>
            @if($repository)
              <tr>
                <th>Repository affiliation</th>
                <td>
                  @if($repository->slug)
                    <a href="{{ url('/' . $repository->slug) }}">
                      {{ $repository->authorized_form_of_name ?? 'Unknown repository' }}
                    </a>
                  @else
                    {{ $repository->authorized_form_of_name ?? 'Unknown repository' }}
                  @endif
                </td>
              </tr>
            @endif
            <tr>
              <th>Account status</th>
              <td>
                @if($user->active)
                  <span class="badge bg-success"><i class="fas fa-check me-1"></i> Active</span>
                @else
                  <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Inactive</span>
                @endif
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  {{-- Security Clearance --}}
  <section class="mb-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Security Clearance</h5>
      </div>
      <div class="card-body">
        @if($securityClearance)
          <table class="table table-bordered mb-0">
            <tbody>
              <tr>
                <th style="width: 200px;">Classification</th>
                <td>
                  <span class="badge bg-primary">
                    {{ $securityClearance->classification_name ?? 'Classification #' . $securityClearance->classification_id }}
                  </span>
                </td>
              </tr>
              <tr>
                <th>Granted</th>
                <td>{{ $securityClearance->granted_at }}</td>
              </tr>
              @if($securityClearance->expires_at)
                <tr>
                  <th>Expires</th>
                  <td>
                    {{ $securityClearance->expires_at }}
                    @if(\Carbon\Carbon::parse($securityClearance->expires_at)->isPast())
                      <span class="badge bg-danger ms-1">Expired</span>
                    @endif
                  </td>
                </tr>
              @endif
              @if($securityClearance->notes)
                <tr>
                  <th>Notes</th>
                  <td>{{ $securityClearance->notes }}</td>
                </tr>
              @endif
            </tbody>
          </table>
        @else
          <p class="text-muted mb-0">
            <i class="fas fa-info-circle me-1"></i>
            No security clearance assigned.
          </p>
        @endif
      </div>
    </div>
  </section>

  {{-- Action buttons --}}
  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('user.profile.edit') }}" class="btn btn-primary">
      <i class="fas fa-pencil-alt me-1"></i> Edit
    </a>

    @if(auth()->user()->isAdministrator() && auth()->id() !== $user->id)
      <form method="POST" action="{{ url('/user/' . $user->id . '/delete') }}"
            onsubmit="return confirm('Are you sure you want to delete this user?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">
          <i class="fas fa-trash me-1"></i> Delete
        </button>
      </form>
    @endif

    @if(auth()->user()->isAdministrator())
      <a href="{{ url('/user/add') }}" class="btn btn-outline-secondary">
        <i class="fas fa-plus me-1"></i> Add new
      </a>
      <a href="{{ url('/user/browse') }}" class="btn btn-outline-secondary">
        <i class="fas fa-list me-1"></i> Return to user list
      </a>
    @endif
  </div>

@endsection
