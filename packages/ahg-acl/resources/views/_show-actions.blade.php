@auth
@if(\AhgCore\Services\AclService::check($group, ['create', 'update', 'delete', 'list']))

  <ul class="actions mb-3 nav gap-2">

    @if(\AhgCore\Services\AclService::check($group, 'update'))
      <li>
        <a href="{{ route('acl.edit-group', ['id' => $group->id]) }}" class="btn atom-btn-outline-light">
          {{ __('Edit') }}
        </a>
      </li>
    @endif

    @if(\AhgCore\Services\AclService::check($group, 'delete'))
      <li>
        <a href="{{ route('acl.groups') }}?delete={{ $group->id }}" class="btn atom-btn-outline-danger">
          {{ __('Delete') }}
        </a>
      </li>
    @endif

    @if(\AhgCore\Services\AclService::check($group, 'create'))
      <li>
        <a href="{{ route('acl.edit-group', ['id' => 0]) }}" class="btn atom-btn-outline-light">
          {{ __('Add new') }}
        </a>
      </li>
    @endif

    @if(\AhgCore\Services\AclService::check($group, 'list'))
      <li>
        <a href="{{ route('acl.groups') }}" class="btn atom-btn-outline-light">
          {{ __('Return to group list') }}
        </a>
      </li>
    @endif

  </ul>

@endif
@endauth
