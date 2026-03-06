@extends('theme::layouts.1col')

@section('title', 'Edit User ' . ($user->username ?? 'Profile') . ' - ' . ($themeData['siteTitle'] ?? 'Heratio'))
@section('body-class', 'user edit')

@section('content')

  <h1>
    <i class="fas fa-user-edit me-2"></i>
    User {{ $user->username }}
  </h1>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('user.profile.update') }}">
    @csrf
    @method('PUT')

    {{-- Basic info accordion --}}
    <div class="accordion mb-3" id="basicInfoAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingBasicInfo">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBasicInfo" aria-expanded="true" aria-controls="collapseBasicInfo">
            <i class="fas fa-user me-2"></i> Basic info
          </button>
        </h2>
        <div id="collapseBasicInfo" class="accordion-collapse collapse show" aria-labelledby="headingBasicInfo" data-bs-parent="#basicInfoAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" class="form-control @error('username') is-invalid @enderror"
                     id="username" name="username"
                     value="{{ old('username', $user->username) }}" required>
              @error('username')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control @error('email') is-invalid @enderror"
                     id="email" name="email"
                     value="{{ old('email', $user->email) }}" required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">
                Password
                <span class="text-muted">(leave blank to keep current)</span>
              </label>
              <input type="password" class="form-control @error('password') is-invalid @enderror"
                     id="password" name="password"
                     autocomplete="new-password" minlength="8">
              <div class="form-text">
                Minimum 8 characters. Use a mix of letters, numbers, and symbols for a strong password.
              </div>
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirm password</label>
              <input type="password" class="form-control"
                     id="password_confirmation" name="password_confirmation"
                     autocomplete="new-password" minlength="8">
            </div>

            @if($isAdmin && auth()->id() !== $user->id)
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                       {{ old('active', $user->active) ? 'checked' : '' }}>
                <label class="form-check-label" for="active">
                  Active
                </label>
                <div class="form-text">Inactive users cannot log in.</div>
              </div>
            @endif

          </div>
        </div>
      </div>
    </div>

    {{-- Access control accordion (admin only) --}}
    @if($isAdmin)
      <div class="accordion mb-3" id="accessControlAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingAccessControl">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccessControl" aria-expanded="true" aria-controls="collapseAccessControl">
              <i class="fas fa-shield-alt me-2"></i> Access control
            </button>
          </h2>
          <div id="collapseAccessControl" class="accordion-collapse collapse show" aria-labelledby="headingAccessControl" data-bs-parent="#accessControlAccordion">
            <div class="accordion-body">

              <div class="mb-3">
                <label class="form-label">User groups</label>
                @foreach($allGroups as $group)
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="groups[]" value="{{ $group->id }}"
                           id="group_{{ $group->id }}"
                           {{ in_array($group->id, old('groups', $userGroupIds)) ? 'checked' : '' }}>
                    <label class="form-check-label" for="group_{{ $group->id }}">
                      {{ $group->name ?? 'Group #' . $group->id }}
                    </label>
                  </div>
                @endforeach
              </div>

            </div>
          </div>
        </div>
      </div>
    @endif

    {{-- Buttons --}}
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save
      </button>
      <a href="{{ route('user.profile') }}" class="btn btn-outline-secondary">
        Cancel
      </a>
    </div>

  </form>

@endsection
