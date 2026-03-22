@if(\AtomExtensions\Services\AclService::check($resource, ['create', 'update', 'delete', 'list']))
  <ul class="actions mb-3 nav gap-2">

    @if(\AtomExtensions\Services\AclService::check($resource, 'update'))
      <li>@php echo link_to(__('Edit'), [$resource, 'module' => 'user', 'action' => str_replace('index', 'edit', $sf_context->getActionName())], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
    @endforeach

    @if($sf_user->user != $resource && 0 == count($resource->notes) && \AtomExtensions\Services\AclService::check($resource, 'delete'))
      <li>@php echo link_to(__('Delete'), [$resource, 'module' => 'user', 'action' => 'delete'], ['class' => 'btn atom-btn-outline-danger']); @endphp</li>
    @endforeach

    @if(\AtomExtensions\Services\AclService::check($resource, 'create'))
      <li>@php echo link_to(__('Add new'), ['module' => 'user', 'action' => 'add'], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
    @endforeach

    @if(\AtomExtensions\Services\AclService::check($resource, 'list'))
      <li>@php echo link_to(__('Return to user list'), ['module' => 'user', 'action' => 'list'], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
    @endforeach

  </ul>
@endforeach
