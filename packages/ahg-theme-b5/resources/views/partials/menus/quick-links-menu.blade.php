@php
  $quickLinks = $themeData['quickLinks'] ?? [];
@endphp

@if(count($quickLinks) > 0)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="quick-links-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-info-circle px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Quick links" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Quick links</span>
    <span class="visually-hidden">Quick links</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="quick-links-menu">
    <li><h6 class="dropdown-header">Quick links</h6></li>
    @foreach($quickLinks as $item)
      <li>
        <a class="dropdown-item" href="{{ \AhgCore\Services\MenuService::resolvePath($item->path) }}">
          {{ $item->label }}
        </a>
      </li>
    @endforeach
  </ul>
</li>
@endif
