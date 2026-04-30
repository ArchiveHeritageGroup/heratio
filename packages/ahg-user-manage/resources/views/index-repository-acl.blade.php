@extends('theme::layouts.1col')

@section('title', __('User %1% - Repository permissions', ['%1%' => $user->username ?? '']))
@section('body-class', 'view user-acl')

@section('title-block')
  <h1>{{ __('User %1%', ['%1%' => $user->username ?? '']) }}</h1>
@endsection

@section('content')

  @include('ahg-user-manage::_acl-menu')

  <div class="section">
    @if(isset($acl) && count($acl) > 0)
      <table id="userPermissions" class="table table-bordered">
        <thead>
          <tr>
            <th colspan="2">{{ __('&nbsp;') }}</th>
            @foreach($userGroups as $item)
              @if(isset($groupNames[$item]))
                <th>{{ $groupNames[$item] }}</th>
              @elseif($user->username == $item)
                <th>{{ $user->username }}</th>
              @endif
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($acl as $objectId => $actions)
            <tr>
              <td colspan="{{ $tableCols }}"><strong>
                @if($objectId > 1 && isset($repositoryNames[$objectId]))
                  {{ $repositoryNames[$objectId] }}
                @else
                  <em>{{ __('All %1%', ['%1%' => lcfirst(config('app.ui_label_repository', 'repositories'))]) }}</em>
                @endif
              </strong></td>
            </tr>
            @foreach($actions as $action => $groupPermission)
              <tr>
                <td>&nbsp;</td>
                <td>
                  @if($action !== '')
                    {{ $aclActions[$action] ?? ucfirst($action) }}
                  @else
                    <em>{{ __('All privileges') }}</em>
                  @endif
                </td>
                @foreach($userGroups as $groupId)
                  <td>
                    @if(isset($groupPermission[$groupId]))
                      @php $permission = $groupPermission[$groupId]; @endphp
                      @if($permission->action === 'translate' && !empty($permission->languages))
                        {{ $permission->access_label ?? ($permission->grant_deny ? 'Grant' : 'Deny') }}: {{ implode(', ', $permission->languages) }}
                      @else
                        {{ $permission->access_label ?? ($permission->grant_deny ? 'Grant' : 'Deny') }}
                      @endif
                    @else
                      -
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted p-3">{{ __('No repository permissions defined for this user.') }}</p>
    @endif
  </div>

@endsection

@section('after-content')
  @include('ahg-user-manage::_show-actions')
@endsection
