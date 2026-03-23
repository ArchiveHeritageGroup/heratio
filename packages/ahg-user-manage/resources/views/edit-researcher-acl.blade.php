@extends('theme::layouts.1col')

@section('title', __('User %1% - Edit researcher permissions', ['%1%' => $user->username ?? '']))
@section('body-class', 'edit user-acl')

@section('title-block')
  <h1>{{ __('User %1%', ['%1%' => $user->username ?? '']) }}</h1>
@endsection

@section('content')

  @include('ahg-user-manage::_acl-menu')

  <form action="{{ route('user.editResearcherAcl', ['slug' => $user->slug]) }}" method="POST" id="editForm">
    @csrf

    <div class="section border rounded mb-3">
      <div class="section-heading rounded-top bg-light p-3">
        <h4 class="mb-0">{{ __('Edit researcher permissions') }}</h4>
      </div>
      <div class="p-3">
        @if(isset($permissions) && count($permissions) > 0)
          <table class="table table-bordered mb-3">
            <thead>
              <tr>
                <th>{{ __('Resource') }}</th>
                <th>{{ __('Action') }}</th>
                <th>{{ __('Permission') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($permissions as $permission)
                <tr>
                  <td>{{ $permission->object_name ?? __('All resources') }}</td>
                  <td>{{ $permission->action ?? __('All privileges') }}</td>
                  <td>
                    <select name="permissions[{{ $permission->id }}]" class="form-select form-select-sm">
                      <option value="grant" @if(($permission->grant_deny ?? 1) == 1) selected @endif>{{ __('Grant') }}</option>
                      <option value="deny" @if(($permission->grant_deny ?? 1) == 0) selected @endif>{{ __('Deny') }}</option>
                      <option value="inherit">{{ __('Inherit') }}</option>
                    </select>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p class="text-muted">{{ __('No researcher permissions defined for this user.') }}</p>
        @endif
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('user.show', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@endsection
