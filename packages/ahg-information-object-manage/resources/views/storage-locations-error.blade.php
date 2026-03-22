@php decorate_with('layout_2col'); @endphp

@php slot('sidebar'); @endphp
  @php include_component('informationobject', 'contextMenu'); @endphp
@php end_slot(); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Physical storage locations') }}</h1>
  <h2>{{ __('No results') }}</h2>
@php end_slot(); @endphp

<fieldset class="single">

  <div class="fieldset-wrapper">

    <p>{{ __('Oops, we couldn\'t find any physical storage locations for the current resource.') }}</p>

  </div>

</fieldset>

@php slot('after-content'); @endphp
  <section class="actions mb-3">
    @php echo link_to(__('Back'), [$resource, 'module' => 'informationobject', 'action' => 'reports'], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
  </section>
@php end_slot(); @endphp
