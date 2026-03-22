@if(in_array('sfSkosPlugin', $sf_data->getRaw('sf_context')->getConfiguration()->getPlugins()))

  @if(\AtomExtensions\Services\AclService::check($resource, 'create'))
    <h4 class="h5 mb-2">{{ __('Import') }}</h4>
    <ul class="list-unstyled">
      <li>
        <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'sfSkosPlugin', 'action' => 'import']); @endphp">
          <i class="fa fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('SKOS') }}
        </a>
      </li>
    </ul>
  @endforeach

  <h4 class="h5 mb-2">{{ __('Export') }}</h4>
  <ul class="list-unstyled">
    <li>
      <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'sfSkosPlugin']); @endphp">
        <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
        </i>{{ __('SKOS') }}
      </a>
    </li>
  </ul>

@endforeach
