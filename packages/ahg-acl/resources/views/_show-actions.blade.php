@if(\AtomExtensions\Services\AclService::check($group, ['create', 'update', 'delete', 'list']))

  <ul class="actions mb-3 nav gap-2">

    @if(\AtomExtensions\Services\AclService::check($group, 'update'))
      <li>@php echo link_to(__('Edit'), [$group, 'module' => 'aclGroup', 'action' => str_replace('index', 'edit', $sf_context->getActionName())], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
    @endif

    @if(\AtomExtensions\Services\AclService::check($group, 'delete'))
      <li>@php echo link_to(__('Delete'), [$group, 'module' => 'aclGroup', 'action' => 'delete'], ['class' => 'btn atom-btn-outline-danger']); @endphp</li>
    @endif

    @if(\AtomExtensions\Services\AclService::check($group, 'create'))
      <li>@php echo link_to(__('Add new'), ['module' => 'aclGroup', 'action' => 'add'], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
    @endif
    
    @if(\AtomExtensions\Services\AclService::check($group, 'list'))
      <li>@php echo link_to(__('Return to group list'), ['module' => 'aclGroup', 'action' => 'list'], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
    @endif

  </ul>

@endif
