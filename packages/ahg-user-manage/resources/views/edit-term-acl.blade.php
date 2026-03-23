@extends('theme::layouts.1col')

@section('title', __('User %1% - Edit taxonomy permissions', ['%1%' => $user->username ?? '']))
@section('body-class', 'edit user-acl')

@section('title-block')
  <h1>{{ __('User %1%', ['%1%' => $user->username ?? '']) }}</h1>
@endsection

@section('content')

  @include('ahg-user-manage::_acl-menu')

  <form action="{{ route('user.editTermAcl', ['slug' => $user->slug]) }}" method="POST" id="editForm">
    @csrf

    <div class="section border rounded mb-3">
      <div class="section-heading rounded-top bg-light p-3">
        <h4 class="mb-0">{{ __('Edit taxonomy permissions') }}</h4>
      </div>
      <div class="p-3">
        @if(isset($permissions) && count($permissions) > 0)
          <table class="table table-bordered mb-3">
            <thead>
              <tr>
                <th>{{ __('Taxonomy') }}</th>
                <th>{{ __('Action') }}</th>
                <th>{{ __('Permission') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($permissions as $permission)
                <tr>
                  <td>{{ $permission->taxonomy_name ?? __('All taxonomies') }}</td>
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
          <p class="text-muted">{{ __('No taxonomy permissions defined for this user.') }}</p>
        @endif

        <div class="mb-3">
          <label class="form-label">{{ __('Add taxonomy permission') }}</label>
          <div class="input-group">
            <select name="new_taxonomy_id" class="form-select">
              <option value="">{{ __('All taxonomies') }}</option>
              @if(isset($taxonomies))
                @foreach($taxonomies as $taxonomy)
                  <option value="{{ $taxonomy->id }}">{{ $taxonomy->name ?? '' }}</option>
                @endforeach
              @endif
            </select>
            <select name="new_action" class="form-select">
              <option value="">{{ __('All privileges') }}</option>
              <option value="create">{{ __('Create') }}</option>
              <option value="read">{{ __('Read') }}</option>
              <option value="update">{{ __('Update') }}</option>
              <option value="delete">{{ __('Delete') }}</option>
            </select>
            <select name="new_grant_deny" class="form-select">
              <option value="grant">{{ __('Grant') }}</option>
              <option value="deny">{{ __('Deny') }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('user.indexTermAcl', ['slug' => $user->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>

@endsection
