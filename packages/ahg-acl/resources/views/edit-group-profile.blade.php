@extends('theme::layouts.1col')

@section('title', 'Edit Group — ' . ($group->name ?? 'Unnamed'))

@section('content')
<div class="container py-4">

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL Groups</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $group->name ?? 'Unnamed' }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users-cog me-2"></i> {{ $group->name ?? 'Unnamed' }}</h2>
    <a href="{{ route('acl.groups') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Groups') }}
    </a>
  </div>

  @include('ahg-acl::_tabs', ['groupsMenu' => $groupsMenu])

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    {{-- Profile (name / description / translate) --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> {{ __('Profile') }}</h5>
        </div>
        <div class="card-body">
          <form action="{{ route('acl.edit-group', ['id' => $group->id]) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label for="name" class="form-label">{{ __('Name') }}</label>
              <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $group->name) }}">
            </div>
            <div class="mb-3">
              <label for="description" class="form-label">{{ __('Description') }}</label>
              <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $group->description) }}</textarea>
            </div>
            <fieldset class="mb-3">
              <legend class="form-label small">{{ __('Translate') }}</legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="translate" id="translate_yes" value="1" {{ $translateFlag ? 'checked' : '' }}>
                <label class="form-check-label" for="translate_yes">{{ __('Yes') }}</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="translate" id="translate_no" value="0" {{ $translateFlag ? '' : 'checked' }}>
                <label class="form-check-label" for="translate_no">{{ __('No') }}</label>
              </div>
            </fieldset>
            <button type="submit" class="btn atom-btn-outline-success">
              <i class="fas fa-save me-1"></i> {{ __('Save') }}
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- Members --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-users me-2"></i> {{ __('Members') }} ({{ $group->members->count() }})</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('User') }}</th>
                  <th>{{ __('Username') }}</th>
                  <th class="text-end">{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($group->members as $member)
                  <tr>
                    <td>{{ $member->display_name ?? $member->username }}</td>
                    <td><code>{{ $member->username }}</code></td>
                    <td class="text-end">
                      <form action="{{ route('acl.remove-member', ['groupId' => $group->id, 'userId' => $member->user_id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this member from the group?');">
                        @csrf
                        <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="{{ __('Remove member') }}">
                          <i class="fas fa-user-minus"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">{{ __('No members in this group.') }}</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <form action="{{ route('acl.add-member', ['groupId' => $group->id]) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col">
              <label for="user_id" class="form-label form-label-sm">{{ __('Add Member') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select name="user_id" id="user_id" class="form-select form-select-sm" required>
                <option value="">-- {{ __('Select User') }} --</option>
                @foreach($allUsers as $user)
                  <option value="{{ $user->id }}">{{ $user->display_name ?? $user->username }} ({{ $user->username }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-sm atom-btn-outline-success">
                <i class="fas fa-user-plus me-1"></i> {{ __('Add') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
