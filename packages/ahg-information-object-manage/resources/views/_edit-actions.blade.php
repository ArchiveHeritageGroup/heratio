<ul class="actions mb-3 nav gap-2">
  @if(AhgCoreModelsInformationObject::ROOT_ID != $resource->id)
    <li><a href="{{ route('informationobject.show', ['slug' => $resource->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
    @if(request()->input('parent'))
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
    @else
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    @endif
  @else
    <li><a href="{{ route('informationobject.browse') }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
    <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
  @endif
</ul>
