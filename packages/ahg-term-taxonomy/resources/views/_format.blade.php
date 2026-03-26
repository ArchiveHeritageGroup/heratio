@if(config('atom.skos_plugin_enabled', false))

  @if(Auth::check() && Auth::user()->can('create', $resource))
    <h4 class="h5 mb-2">{{ __('Import') }}</h4>
    <ul class="list-unstyled">
      <li>
        <a class="atom-icon-link" href="{{ route('skos.import', ['slug' => $resource->slug]) }}">
          <i class="fa fa-fw fa-download me-1" aria-hidden="true">
          </i>{{ __('SKOS') }}
        </a>
      </li>
    </ul>
  @endif

  <h4 class="h5 mb-2">{{ __('Export') }}</h4>
  <ul class="list-unstyled">
    <li>
      <a class="atom-icon-link" href="{{ route('skos.export', ['slug' => $resource->slug]) }}">
        <i class="fas fa-fw fa-upload me-1" aria-hidden="true">
        </i>{{ __('SKOS') }}
      </a>
    </li>
  </ul>

@endif
