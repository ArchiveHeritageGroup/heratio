<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0"
     href="#"
     id="clipboard-menu"
     role="button"
     data-bs-toggle="dropdown"
     aria-expanded="false"
     data-total-count-label="records added"
     data-alert-close="Close"
     data-load-alert-message="There was an error loading the clipboard content."
     data-export-alert-message="The clipboard is empty for this entity type."
     data-export-check-url="{{ url('/clipboard/exportCheck') }}"
     data-delete-alert-message="Note: clipboard items unclipped in this page will be removed from the clipboard when the page is refreshed. You can re-select them now, or reload the page to remove them completely. Using the sort or print preview buttons will also cause a page reload — so anything currently deselected will be lost!">
    <i class="fas fa-2x fa-fw fa-paperclip px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="Clipboard" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">Clipboard</span>
    <span class="visually-hidden">Clipboard</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="clipboard-nav">
    <li><h6 class="dropdown-header">Clipboard</h6></li>
    <li class="text-muted text-nowrap px-3 pb-2">
      <span id="counts-block"
            data-information-object-label="Archival description"
            data-actor-object-label="Authority record"
            data-repository-object-label="Archival institution">
      </span>
    </li>
    <li>
      <form method="POST" action="{{ route('clipboard.clear') }}" id="clipboard-clear-form">
        @csrf
        <button type="submit" class="dropdown-item" title="Clear all selections">
          <i class="fas fa-trash-alt me-2"></i>Clear all selections
        </button>
      </form>
    </li>
    <li>
      <a class="dropdown-item" href="{{ route('clipboard.view') }}" title="Go to clipboard">
        <i class="fas fa-clipboard-list me-2"></i>Go to clipboard
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="{{ url('/clipboard/load') }}" title="Load clipboard">
        <i class="fas fa-upload me-2"></i>Load clipboard
      </a>
    </li>
    <li>
      <form method="POST" action="{{ route('clipboard.save') }}" id="clipboard-save-form">
        @csrf
        <button type="submit" class="dropdown-item" title="Save clipboard">
          <i class="fas fa-download me-2"></i>Save clipboard
        </button>
      </form>
    </li>
  </ul>
</li>
