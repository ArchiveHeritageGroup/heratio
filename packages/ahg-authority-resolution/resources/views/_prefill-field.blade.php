{{--
    auth-res::_prefill-field - Bootstrap 5 pre-filled input + provenance badge.

    Renders one pre-filled form input + a badge showing the source. Used by
    the create-new screen so every field carries an obvious "where did this
    come from" affordance. Provenance is round-tripped via hidden inputs so
    FieldProvenanceWriter can replay the source attribution to Fuseki.

    Props:
      - name              (string)  form field name
      - label             (string)  field label
      - value             (mixed)   current value
      - prov              (array|null) provenance entry from PrefillEngine:
                                       {source, uri, licence, licence_url, retrieved_at}
      - type              (string)  'text' | 'textarea' | 'number'  (default 'text')
      - required          (bool)
      - help              (string|null) caption underneath
      - rows              (int)     textarea rows (when type=textarea)
--}}
@php
    $type        = $type     ?? 'text';
    $required    = $required ?? false;
    $rows        = $rows     ?? 4;
    $help        = $help     ?? null;
    $prov        = $prov     ?? null;
    $sourceLabel = $prov['source'] ?? null;
    $sourceUri   = $prov['uri'] ?? null;
    $licence     = $prov['licence'] ?? null;
    $licenceUrl  = $prov['licence_url'] ?? null;
    $retrievedAt = $prov['retrieved_at'] ?? null;

    // Per-source badge colour (Bootstrap palette).
    $badgeColour = match ($sourceLabel) {
        'viaf'            => 'bg-success',
        'wikidata'        => 'bg-info text-dark',
        'geonames'        => 'bg-warning text-dark',
        'tgn'             => 'bg-primary',
        'gnd'             => 'bg-danger',
        'isni'            => 'bg-primary',
        'sagnc'           => 'bg-warning text-dark',
        'mention'         => 'bg-secondary',
        'mention_context' => 'bg-secondary',
        default           => 'bg-light text-dark border',
    };

    $tooltip = trim(
        ($licence    ? 'Licence: ' . $licence . '. ' : '')
        . ($retrievedAt ? 'Retrieved: ' . $retrievedAt . '. ' : '')
        . ($sourceUri   ? 'Source URI: ' . $sourceUri : '')
    );
@endphp

<div class="mb-3">
    <label class="form-label fw-bold" for="auth-res-field-{{ $name }}">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
        @if($sourceLabel)
            <span class="badge {{ $badgeColour }} ms-1"
                  title="{{ $tooltip }}">
                <i class="bi bi-link-45deg me-1"></i>pre-filled / {{ $sourceLabel }}
                @if($sourceUri)
                    <a href="{{ $sourceUri }}" target="_blank" rel="noopener noreferrer"
                       class="ms-1 text-white text-decoration-underline">link</a>
                @endif
            </span>
        @endif
    </label>

    @if($type === 'textarea')
        <textarea name="{{ $name }}"
                  id="auth-res-field-{{ $name }}"
                  class="form-control"
                  rows="{{ $rows }}"
                  @if($required) required @endif>{{ old($name, $value) }}</textarea>
    @elseif($type === 'number')
        <input type="number"
               step="any"
               name="{{ $name }}"
               id="auth-res-field-{{ $name }}"
               value="{{ old($name, $value) }}"
               class="form-control"
               @if($required) required @endif>
    @else
        <input type="text"
               name="{{ $name }}"
               id="auth-res-field-{{ $name }}"
               value="{{ old($name, $value) }}"
               class="form-control"
               @if($required) required @endif>
    @endif

    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif

    {{-- Hidden provenance carry: posted back so FieldProvenanceWriter
         knows where each surviving field actually came from. --}}
    @if($prov)
        <input type="hidden" name="provenance[{{ $name }}][source]"       value="{{ $sourceLabel }}">
        <input type="hidden" name="provenance[{{ $name }}][uri]"          value="{{ $sourceUri }}">
        <input type="hidden" name="provenance[{{ $name }}][licence]"      value="{{ $licence }}">
        <input type="hidden" name="provenance[{{ $name }}][licence_url]"  value="{{ $licenceUrl }}">
        <input type="hidden" name="provenance[{{ $name }}][retrieved_at]" value="{{ $retrievedAt }}">
    @endif
</div>
