@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $user ? 'Edit' : 'Add new' }} user</h1>
    @if($user)
      <span class="small">{{ $user->authorized_form_of_name ?? $user->username }}</span>
    @endif
  </div>
@endsection

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $user ? route('user.update', $user->slug) : route('user.store') }}">
    @csrf

    <div class="accordion mb-3">
      {{-- Account information --}}
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#account-collapse" aria-expanded="true">Account information</button>
        </h2>
        <div id="account-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" name="username" id="username" class="form-control" required
                     value="{{ old('username', $user->username ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="email" class="form-control" required
                     value="{{ old('email', $user->email ?? '') }}">
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">
                Password
                @if(!$user)
                  <span class="text-danger">*</span>
                @endif
              </label>
              <input type="password" name="password" id="password" class="form-control"
                     {{ $user ? '' : 'required' }}>
              @if($user)
                <div class="form-text">Leave blank to keep the current password.</div>
              @endif
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">Display name</label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control"
                     value="{{ old('authorized_form_of_name', $user->authorized_form_of_name ?? '') }}">
            </div>

            <div class="mb-3 form-check">
              <input type="checkbox" name="active" id="active" class="form-check-input" value="1"
                     {{ old('active', $user ? $user->active : 1) ? 'checked' : '' }}>
              <label for="active" class="form-check-label">Active</label>
            </div>
          </div>
        </div>
      </div>

      {{-- Group membership --}}
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#groups-collapse">User groups</button>
        </h2>
        <div id="groups-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            @php
              $currentGroupIds = [];
              if ($user && !empty($user->groups)) {
                  $currentGroupIds = array_map(fn($g) => (int) $g->id, $user->groups);
              }
            @endphp

            @foreach($assignableGroups as $group)
              <div class="form-check mb-2">
                <input type="checkbox" name="groups[]" id="group_{{ $group->id }}"
                       class="form-check-input" value="{{ $group->id }}"
                       {{ in_array((int) $group->id, old('groups', $currentGroupIds)) ? 'checked' : '' }}>
                <label for="group_{{ $group->id }}" class="form-check-label">{{ $group->name }}</label>
              </div>
            @endforeach

            @if(empty($assignableGroups))
              <p class="text-muted mb-0">No assignable groups found.</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($user)
        <li><a href="{{ route('user.show', $user->slug) }}" class="btn btn-outline-secondary">Cancel</a></li>
        <li><input class="btn btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('user.browse') }}" class="btn btn-outline-secondary">Cancel</a></li>
        <li><input class="btn btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>
@endsection
