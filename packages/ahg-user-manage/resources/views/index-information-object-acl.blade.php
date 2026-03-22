<h1>{{ __('User %1%', ['%1%' => render_title($resource)]) }}</h1>

@php echo get_component('user', 'aclMenu'); @endphp

<div class="section">
  @if(0 < count($acl))
    <table id="userPermissions" class="table table-bordered sticky-enabled">
      <thead>
        <tr>
          <th colspan="2">&nbsp;</th>
          @foreach($userGroups as $item)
            @if(null !== $group = QubitAclGroup::getById($item))
              <th>@php echo esc_entities($group->__toString()); @endphp</th>
            @php } elseif ($resource->username == $item) { @endphp
              <th>@php echo $resource->username; @endphp</th>
            @endforeach
          @endforeach
        </tr>
      </thead><tbody>
        @foreach($acl as $repository => $objects)
          @foreach($objects as $objectId => $actions)
            <tr>
              <td colspan="@php echo $tableCols; @endphp"><strong>
                @if('' == $repository && '' == $objectId)
                  <em>{{ __('All %1%', ['%1%' => lcfirst(sfConfig::get('app_ui_label_informationobject'))]) }}</em>
                @php } elseif ('' != $repository) { @endphp
                  @php echo sfConfig::get('app_ui_label_repository').': '.esc_entities(render_title(QubitRepository::getBySlug($repository))); @endphp
                @php } else { @endphp
                  @php echo esc_entities(render_title(QubitInformationObject::getById($objectId))); @endphp
                @endforeach
              </strong></td>
            </tr>
            @php $row = 0; @endphp
            @foreach($actions as $action => $groupPermission)
              <tr class="@php echo 0 == @++$row % 2 ? 'even' : 'odd'; @endphp">
                <td>&nbsp;</td>
                <td>
                  @if('' != $action)
                    @php echo QubitInformationObjectAcl::$ACTIONS[$action]; @endphp
                  @php } else { @endphp
                    <em>{{ __('All privileges') }}</em>
                  @endforeach
                </td>
                @foreach($sf_data->getRaw('userGroups') as $groupId)
                  <td>
                    @if(isset($groupPermission[$groupId]) && $permission = $groupPermission[$groupId])
                      @if('translate' == $permission->action && null !== $permission->getConstants(['name' => 'languages']))
                        @php $permission = sfOutputEscaper::unescape($permission); @endphp
                        @php echo $permission->renderAccess().': '.implode(',', $permission->getConstants(['name' => 'languages'])); @endphp
                      @php } else { @endphp
                        @php echo $permission->renderAccess(); @endphp
                      @endforeach
                    @php } else { @endphp
                      @php echo '-'; @endphp
                    @endforeach
                  </td>
                @endforeach
              </tr>
            @endforeach
          @endforeach
        @endforeach
      </tbody>
    </table>
  @endforeach
</div>

@php echo get_partial('showActions', ['resource' => $resource]); @endphp
