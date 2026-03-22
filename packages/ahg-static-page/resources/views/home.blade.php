@php decorate_with('layout_2col'); @endphp
@php slot('title'); @endphp
  <h1>@php echo $resource->getTitle(['cultureFallback' => true]); @endphp</h1>
@php end_slot(); @endphp

@php slot('sidebar'); @endphp
  @php echo get_component('menu', 'staticPagesMenu'); @endphp
  
  @php $browseMenu = QubitMenu::getById(QubitMenu::BROWSE_ID); @endphp
  @if($browseMenu->hasChildren())
    <section class="card mb-3">
      <h2 class="h5 p-3 mb-0">
        {{ __('Browse by') }}
      </h2>
      <div class="list-group list-group-flush">
        @foreach($browseMenu->getChildren() as $item)
          <a class="list-group-item list-group-item-action" href="@php echo url_for($item->getPath(['getUrl' => true, 'resolveAlias' => true])); @endphp">
            @php echo esc_specialchars($item->getLabel(['cultureFallback' => true])); @endphp
          </a>
        @endforeach
      </div>
    </section>
  @endforeach
  
  @php echo get_component('default', 'popular', [
      'limit' => 10,
      'sf_cache_key' => $sf_user->getCulture(),
  ]); @endphp
@php end_slot(); @endphp

@php // Featured collection carousel - provided by ahgIiifPlugin @endphp
@if(class_exists('ahgIiifPluginConfiguration'))
  @php include_partial('iiif/featuredCollection') @endphp
@endif

<div class="page p-3">
  @php echo render_value_html($sf_data->getRaw('content')); @endphp
</div>

@if(\AtomExtensions\Services\AclService::check($resource, 'update'))
  @php slot('after-content'); @endphp
    <section class="actions mb-3">
      @php echo link_to(__('Edit'), [$resource, 'module' => 'staticpage', 'action' => 'edit'], ['class' => 'btn atom-btn-outline-light']); @endphp
    </section>
  @php end_slot(); @endphp
@endforeach
