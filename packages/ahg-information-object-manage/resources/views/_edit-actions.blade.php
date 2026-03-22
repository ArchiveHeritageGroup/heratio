<ul class="actions mb-3 nav gap-2">
  @if(QubitInformationObject::ROOT_ID != $resource->id)
    <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
    @if(isset($sf_request->parent))
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
    @php } else { @endphp
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    @endforeach
  @php } else { @endphp
    <li>@php echo link_to(__('Cancel'), ['module' => 'informationobject', 'action' => 'browse'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
    <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
  @endforeach
</ul>
