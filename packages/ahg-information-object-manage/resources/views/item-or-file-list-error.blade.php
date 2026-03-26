<div class="content">
    <h1>{{ __('%1 list', ['%1' => $type]) }}</h1>

    <h1 class="label">{{ __('No results') }}</h1>

    <p>{{ __("Oops, we couldn't find any %1 level descriptions.", ['%1' => strtolower($type)]) }}</p>

    <p><a href="{{ route('informationobject.reports', $resource->slug) }}">{{ __('Back') }}</a></p>
  </div>
</div>
