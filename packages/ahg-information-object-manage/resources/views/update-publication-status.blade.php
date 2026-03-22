@php decorate_with('layout_2col.php'); @endphp

@php slot('sidebar'); @endphp

  @php include_component('repository', 'contextMenu'); @endphp

@php end_slot(); @endphp

@php slot('title'); @endphp

  <h1>@php echo render_title($resource); @endphp</h1>

@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(
    url_for(
      [
          $resource,
          'module' => 'informationobject',
          'action' => 'updatePublicationStatus',
      ]
    ),
    [
        'id' => 'update-publication-status-form',
        'data-cy' => 'update-publication-status-form',
    ]
  ); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="pub-status-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#pub-status-collapse" aria-expanded="true" aria-controls="pub-status-collapse">
            {{ __('Update publication status') }}
          </button>
        </h2>
        <div id="pub-status-collapse" class="accordion-collapse collapse show" aria-labelledby="pub-status-heading">
          <div class="accordion-body">
            @php echo render_field($form->publicationStatus->label(__('Publication status'))); @endphp

            @if($resource->rgt - $resource->lft > 1)
              @php echo render_field($form->updateDescendants->label(__('Update descendants'))); @endphp
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Update') }}"></li>
    </ul>

  </form>

@php end_slot(); @endphp
