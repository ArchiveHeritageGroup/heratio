<section id="action-icons">

  <h4 class="h5 mb-2">{{ __('Clipboard') }}</h4>
  <ul class="list-unstyled">
    <li>
      @include('ahg-core::partials._clipboard-button', ['slug' => $resource->slug, 'wide' => true, 'type' => 'informationObject'])
    </li>
  </ul>

  <h4 class="h5 mb-2">{{ __('Explore') }}</h4>
  <ul class="list-unstyled">

    <li>
      <a class="atom-icon-link" href="{{ route('informationobject.reports', $resource->slug) }}">
        <i class="fas fa-fw fa-print me-1" aria-hidden="true">
        </i>{{ __('Reports') }}
      </a>
    </li>

    @if(!empty($showInventory))
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.inventory', $resource->slug) }}">
          <i class="fas fa-fw fa-list-alt me-1" aria-hidden="true">
          </i>{{ __('Inventory') }}
        </a>
      </li>
    @endif

    <li>
      <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $resource->getCollectionRoot()->id ?? '', 'topLod' => false]) }}">
        <i class="fas fa-fw fa-list me-1" aria-hidden="true">
        </i>{{ __('Browse as list') }}
      </a>
    </li>

    @if(!empty($resource->getDigitalObject()))
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $resource->getCollectionRoot()->id ?? '', 'topLod' => false, 'view' => 'card', 'onlyMedia' => true]) }}">
          <i class="fas fa-fw fa-image me-1" aria-hidden="true">
          </i>{{ __('Browse digital objects') }}
        </a>
      </li>
    @endif
  </ul>

  @if(auth()->check() && auth()->user()->isAdministrator())
    <h4 class="h5 mb-2">{{ __('Import') }}</h4>
    <ul class="list-unstyled">
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.import.xml', $resource->slug) }}">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('XML') }}
        </a>
      </li>

      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.import.csv', $resource->slug) }}">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('CSV') }}
        </a>
      </li>
    </ul>
  @endif

  <h4 class="h5 mb-2">{{ __('Export') }}</h4>
  <ul class="list-unstyled">
    <li>
      <a class="atom-icon-link" href="{{ route('informationobject.export.dc', $resource->slug) }}">
        <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
        </i>{{ __('Dublin Core 1.1 XML') }}
      </a>
    </li>

    <li>
      <a class="atom-icon-link" href="{{ route('informationobject.export.ead', $resource->slug) }}">
        <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
        </i>{{ __('EAD 2002 XML') }}
      </a>
    </li>

    @if(!empty($showMods))
      <li>
        <a class="atom-icon-link" href="{{ route('informationobject.export.mods', $resource->slug) }}">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('MODS 3.5 XML') }}
        </a>
      </li>
    @endif
  </ul>

  @include('ahg-information-object-manage::_finding-aid-link', ['resource' => $resource, 'contextMenu' => true])

  @include('ahg-information-object-manage::_calculate-dates-link', ['resource' => $resource, 'contextMenu' => true])

</section>
