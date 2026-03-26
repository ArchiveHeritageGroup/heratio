@php decorate_with('layout_2col.php'); @endphp

@php slot('sidebar'); @endphp

  @php include_component('repository', 'contextMenu'); @endphp

@php end_slot(); @endphp

@php slot('title'); @endphp

  <h1>@php echo render_title($resource); @endphp</h1>

@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'informationobject', 'action' => 'uploadFindingAid'])); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="load-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#load-collapse" aria-expanded="true" aria-controls="load-collapse">
            {{ __('Upload finding aid') }}
          </button>
        </h2>
        <div id="load-collapse" class="accordion-collapse collapse show" aria-labelledby="load-heading">
          <div class="accordion-body">
            @if(isset($errorMessage))
              <div class="alert alert-danger" role="alert">
                @php echo $errorMessage; @endphp</li>
              </div>
            @endif

            @php echo render_field($form->file->label(__('%1% file', ['%1%' => strtoupper($format)]))); @endphp
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Upload') }}"></li>
    </ul>

  </form>

@php end_slot(); @endphp
