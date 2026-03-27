<div class="dropdown d-grid">
  <button class="btn atom-btn-white dropdown-toggle text-wrap" type="button" id="browse-menu-institution-button" data-bs-toggle="dropdown" aria-expanded="false">
    {{ $browseMenuInstitution->getLabel(['cultureFallback' => true]) }}
  </button>
  <ul class="dropdown-menu mt-2" aria-labelledby="browse-menu-institution-button">
    @foreach($browseMenuInstitution->getChildren() as $child)
      <li>
        <a class="dropdown-item" href="{{ $child->getPath(['getUrl' => true, 'resolveAlias' => true]) }}">
          {{ $child->getLabel(['cultureFallback' => true]) }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
