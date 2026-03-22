@extends('theme::layouts.1col')

@section('title', $user->authorized_form_of_name ?? $user->username ?? 'User')
@section('body-class', 'view user')

@section('content')
  <h1>{{ $user->authorized_form_of_name ?? $user->username }}</h1>

  @if($user->active)
    <span class="badge bg-success mb-3">Active</span>
  @else
    <span class="badge bg-secondary mb-3">Inactive</span>
  @endif

  {{-- User information --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">User information</h2>

    @if($user->username)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Username</div>
        <div class="col-md-9">{{ $user->username }}</div>
      </div>
    @endif

    @if($user->email)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Email</div>
        <div class="col-md-9">{{ $user->email }}</div>
      </div>
    @endif

    <div class="row mb-2">
      <div class="col-md-3 fw-bold">Active</div>
      <div class="col-md-9">
        @if($user->active)
          <span class="badge bg-success">Active</span>
        @else
          <span class="badge bg-secondary">Inactive</span>
        @endif
      </div>
    </div>
  </section>

  {{-- Profile --}}
  @if($user->authorized_form_of_name)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Profile</h2>

      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Authorized form of name</div>
        <div class="col-md-9">{{ $user->authorized_form_of_name }}</div>
      </div>
    </section>
  @endif

  {{-- Groups --}}
  @if($groups->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Groups</h2>

      <ul class="list-group">
        @foreach($groups as $group)
          <li class="list-group-item">
            <strong>{{ $group->name }}</strong>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  {{-- Dates --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Dates</h2>

    @if($user->created_at)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Created</div>
        <div class="col-md-9">{{ \Carbon\Carbon::parse($user->created_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif

    @if($user->updated_at)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Updated</div>
        <div class="col-md-9">{{ \Carbon\Carbon::parse($user->updated_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif
  </section>

  {{-- Admin action buttons --}}
  <ul class="actions mb-3 nav gap-2">
    <li><a href="{{ route('user.edit', $user->slug) }}" class="btn atom-btn-white">Edit</a></li>
    <li><a href="{{ route('user.confirmDelete', $user->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
    <li><a href="{{ route('user.browse') }}" class="btn atom-btn-white">Back to list</a></li>
  </ul>
@endsection
