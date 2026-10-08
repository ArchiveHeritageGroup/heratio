{{--
  Custom field values on a show page (heratio#1530). Variables: $objectId,
  optional $entityType (default informationobject). Fields not marked
  "visible to the public" are shown to signed-in users only.
--}}
@php
  $cfValues = app(\AhgCustomFields\Services\CustomFieldService::class)->exportValuesFor(
      [(int) ($objectId ?? 0)], $entityType ?? \AhgCustomFields\Services\CustomFieldService::IO, false
  )[(int) ($objectId ?? 0)] ?? [];
  if (! auth()->check()) {
      $cfPublic = app(\AhgCustomFields\Services\CustomFieldService::class)
          ->getFieldsForEntityType($entityType ?? \AhgCustomFields\Services\CustomFieldService::IO)
          ->where('is_visible_public', 1)->pluck('field_key')->all();
      $cfValues = array_values(array_filter($cfValues, fn ($v) => in_array($v['key'], $cfPublic, true)));
  }
@endphp
@if($cfValues)
  <section id="customFieldsArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      {{ __('Additional fields') }}
    </h2>
    @foreach($cfValues as $cf)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $cf['label'] }}</h3>
        <div class="col-9 p-2">
          @if(filter_var($cf['value'], FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $cf['value']))
            <a href="{{ $cf['value'] }}" target="_blank" rel="noopener">{{ $cf['value'] }}</a>
          @else
            {!! nl2br(e($cf['value'])) !!}
          @endif
        </div>
      </div>
    @endforeach
  </section>
@endif
