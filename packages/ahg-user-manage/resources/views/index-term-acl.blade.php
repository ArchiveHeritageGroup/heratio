<h1>{{ __('User %1%', ['%1%' => $resource->authorized_form_of_name ?? $resource->username ?? '']) }}</h1>

@include('ahg-user-manage::_acl-menu', ['resource' => $resource])

<div class="section">

  @if(0 < count($acl))

    <table id="userPermissions" class="table table-bordered sticky-enabled">
      <thead>
        <tr>
          <th colspan="2">{{ __('&nbsp;') }}</th>
          @foreach($roles as $item)
            @php $group = \AhgCore\Models\AclGroup::find($item); @endphp
            @if($group)
              <th>{{ e($group->__toString()) }}</th>
            @elseif($resource->username == $item)
              <th>{{ $resource->username }}</th>
            @endif
          @endforeach
        </tr>
      </thead><tbody>
        @foreach($acl as $taxonomy => $actions)
          <tr>
            <td colspan="{{ $tableCols }}"><strong>
              @if('' == $taxonomy)
                <em>{{ __('All %1%', ['%1%' => lcfirst(config('atom.ui_label_term', __('Term')))]) }}</em>
              @else
                @php $taxObj = \AhgCore\Models\Taxonomy::where('slug', $taxonomy)->first(); @endphp
                {{ __('Taxonomy: %1%', ['%1%' => e($taxObj->authorized_form_of_name ?? $taxObj->title ?? $taxonomy)]) }}
              @endif
            </strong></td>
          </tr>
          @php $row = 0; @endphp
          @foreach($actions as $action => $groupPermission)
            <tr class="{{ 0 == ++$row % 2 ? 'even' : 'odd' }}">
              <td>&nbsp;</td>
              <td>
                @if('' != $action)
                  {{ \AhgCore\Models\Acl::$ACTIONS[$action] ?? $action }}
                @else
                  <em>{{ __('All privileges') }}</em>
                @endif
              </td>
              @foreach($roles as $roleId)
                <td>
                  @if(isset($groupPermission[$roleId]) && $permission = $groupPermission[$roleId])
                    @if('translate' == $permission->action && null !== ($languages = $permission->getConstants(['name' => 'languages'])))
                      {{ $permission->renderAccess() }}: {{ implode(',', $languages) }}
                    @else
                      {{ $permission->renderAccess() }}
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

  @endif

</div>

@include('ahg-user-manage::_show-actions', ['resource' => $resource])
