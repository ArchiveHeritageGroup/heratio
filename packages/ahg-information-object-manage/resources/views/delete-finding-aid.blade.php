@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Are you sure you want to delete the finding aid of %1%?', ['%1%' => $resource->title]) }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'informationobject', 'action' => 'deleteFindingAid']), ['method' => 'delete']); @endphp

    @php echo $form->renderHiddenFields(); @endphp
    
    <div id="content" class="p-3">
      {{ __('The following file will be deleted from the file system:') }}

      <ul class="mb-0">
        <li><a href="@php echo public_path($path); @endphp" target="_blank">@php echo $filename; @endphp</a></li>
        <li>{{ __('If the finding aid is an uploaded PDF, the transcript will be deleted too.') }}</li>
      </ul>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="{{ __('Delete') }}"></li>
    </ul>

  </form>

@php end_slot(); @endphp
