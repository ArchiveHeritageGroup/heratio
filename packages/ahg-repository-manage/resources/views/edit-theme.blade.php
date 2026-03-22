@php decorate_with('layout_2col'); @endphp

@php slot('sidebar'); @endphp
  @php echo get_component('repository', 'contextMenu'); @endphp
@php end_slot(); @endphp

@php slot('title'); @endphp
  <h1>@php echo render_title($resource); @endphp</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp
  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'repository', 'action' => 'editTheme'])); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="style-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#style-collapse" aria-expanded="false" aria-controls="style-collapse">
            {{ __('Style') }}
          </button>
        </h2>
        <div id="style-collapse" class="accordion-collapse collapse" aria-labelledby="style-heading">
          <div class="accordion-body">
            @php echo render_field($form->backgroundColor); @endphp

            @php echo render_field($form->banner); @endphp

            @php echo render_field($form->logo); @endphp
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="content-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-collapse" aria-expanded="false" aria-controls="content-collapse">
            {{ __('Page content') }}
          </button>
        </h2>
        <div id="content-collapse" class="accordion-collapse collapse" aria-labelledby="content-heading">
          <div class="accordion-body">
            @php echo render_field($form->htmlSnippet
                ->label(__('Description'))
                ->help(__('Content in this area will appear below an uploaded banner and above the institution\'s description areas. It can be used to offer a summary of the institution\'s mandate, include a tag line or important information, etc. HTML and inline CSS can be used to style the contents.')), $resource, ['class' => 'resizable']); @endphp
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'repository'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>
@php end_slot(); @endphp
