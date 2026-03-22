@php decorate_with('layout_2col'); @endphp

@php slot('sidebar'); @endphp
  @php include_component('informationobject', 'contextMenu'); @endphp
@php end_slot(); @endphp

@php slot('title'); @endphp
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('%1 - report criteria', ['%1' => $type]) }}
    </h1>
    <span class="small" id="heading-label">
      @php echo render_title($resource); @endphp
    </span>
  </div>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'informationobject', 'action' => 'storageLocations', 'type' => $type]), ['class' => 'form-inline']); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="report-criteria-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#report-criteria-collapse" aria-expanded="true" aria-controls="report-criteria-collapse">
            {{ __('Report criteria') }}
          </button>
        </h2>
        <div id="report-criteria-collapse" class="accordion-collapse collapse show" aria-labelledby="report-criteria-heading">
          <div class="accordion-body">

            @php echo render_field($form->format->label(__('Format')), $resource); @endphp

          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Continue') }}"></li>
    </ul>

  </form>

@php end_slot(); @endphp
