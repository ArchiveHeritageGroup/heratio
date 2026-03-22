<section id="action-icons">

  <h4 class="h5 mb-2">{{ __('Clipboard') }}</h4>
  <ul class="list-unstyled">
    <li>
      @php echo get_component('clipboard', 'button', ['slug' => $resource->slug, 'wide' => true, 'type' => 'informationObject']); @endphp
    </li>
  </ul>

  <h4 class="h5 mb-2">{{ __('Explore') }}</h4>
  <ul class="list-unstyled">

    <li>
      <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'informationobject', 'action' => 'reports']); @endphp">
        <i class="fas fa-fw fa-print me-1" aria-hidden="true">
        </i>{{ __('Reports') }}
      </a>
    </li>

    @if(InformationObjectInventoryAction::showInventory($resource))
      <li>
        <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'informationobject', 'action' => 'inventory']); @endphp">
          <i class="fas fa-fw fa-list-alt me-1" aria-hidden="true">
          </i>{{ __('Inventory') }}
        </a>
      </li>
    @endforeach

    <li>
      @if(isset($resource) && sfConfig::get('app_enable_institutional_scoping') && $sf_user->hasAttribute('search-realm'))
        <a class="atom-icon-link" href="@php echo url_for([
            'module' => 'informationobject',
            'action' => 'browse',
            'collection' => $resource->getCollectionRoot()->id,
            'repos' => $sf_user->getAttribute('search-realm'),
            'topLod' => false, ]); @endphp">
      @php } else { @endphp
        <a class="atom-icon-link" href="@php echo url_for([
            'module' => 'informationobject',
            'action' => 'browse',
            'collection' => $resource->getCollectionRoot()->id,
            'topLod' => false, ]); @endphp">
      @endforeach
        <i class="fas fa-fw fa-list me-1" aria-hidden="true">
        </i>{{ __('Browse as list') }}
      </a>
    </li>

    @if(!empty($resource->getDigitalObject()))
      <li>
        <a class="atom-icon-link" href="@php echo url_for([
            'module' => 'informationobject',
            'action' => 'browse',
            'collection' => $resource->getCollectionRoot()->id,
            'topLod' => false,
            'view' => 'card',
            'onlyMedia' => true, ]); @endphp">
          <i class="fas fa-fw fa-image me-1" aria-hidden="true">
          </i>{{ __('Browse digital objects') }}
        </a>
      </li>
    @endforeach
  </ul>

  @if($sf_user->isAdministrator())
    <h4 class="h5 mb-2">{{ __('Import') }}</h4>
    <ul class="list-unstyled">
      <li>
        <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'object', 'action' => 'importSelect', 'type' => 'xml']); @endphp">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('XML') }}
        </a>
      </li>

      <li>
        <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'object', 'action' => 'importSelect', 'type' => 'csv']); @endphp">
          <i class="fas fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('CSV') }}
        </a>
      </li>
    </ul>
  @endforeach

  <h4 class="h5 mb-2">{{ __('Export') }}</h4>
  <ul class="list-unstyled">
    @if($sf_context->getConfiguration()->isPluginEnabled('sfDcPlugin'))
      <li>
        <a class="atom-icon-link" href="@php echo $resource->urlForDcExport(); @endphp">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('Dublin Core 1.1 XML') }}
        </a>
      </li>
    @endforeach

    @if($sf_context->getConfiguration()->isPluginEnabled('sfEadPlugin'))
      <li>
        <a class="atom-icon-link" href="@php echo $resource->urlForEadExport(); @endphp">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('EAD 2002 XML') }}
        </a>
      </li>
    @endforeach

    @if('sfModsPlugin' == $sf_context->getModuleName() && $sf_context->getConfiguration()->isPluginEnabled('sfModsPlugin'))
      <li>
        <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'sfModsPlugin', 'sf_format' => 'xml']); @endphp">
          <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
          </i>{{ __('MODS 3.5 XML') }}
        </a>
      </li>
    @endforeach
  </ul>

  @php echo get_component('informationobject', 'findingAid', ['resource' => $resource, 'contextMenu' => true]); @endphp

  @php echo get_component('informationobject', 'calculateDatesLink', ['resource' => $resource, 'contextMenu' => true]); @endphp

</section>
