{{--
    auth-res::_prefill-field

    Renders one pre-filled form input + provenance badge. Used by the
    create-new screen so every field carries an obvious "where did this
    come from" affordance.

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
    $type     = $type     ?? 'text';
    $required = $required ?? false;
    $rows     = $rows     ?? 4;
    $help     = $help     ?? null;
    $prov     = $prov     ?? null;
    $sourceLabel = $prov['source'] ?? null;
    $sourceUri   = $prov['uri'] ?? null;
    $licence     = $prov['licence'] ?? null;
    $licenceUrl  = $prov['licence_url'] ?? null;
    $retrievedAt = $prov['retrieved_at'] ?? null;
    $badgeColour = match ($sourceLabel) {
        'viaf'           => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'wikidata'       => 'bg-sky-100 text-sky-800 border-sky-200',
        'geonames'       => 'bg-amber-100 text-amber-800 border-amber-200',
        'tgn'            => 'bg-violet-100 text-violet-800 border-violet-200',
        'gnd'            => 'bg-rose-100 text-rose-800 border-rose-200',
        'isni'           => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        'sagnc'          => 'bg-orange-100 text-orange-800 border-orange-200',
        'mention'        => 'bg-slate-100 text-slate-700 border-slate-200',
        'mention_context' => 'bg-slate-100 text-slate-700 border-slate-200',
        default          => 'bg-slate-50 text-slate-600 border-slate-200',
    };
    $tooltip = trim(
        ($licence ? 'Licence: ' . $licence . '. ' : '')
        . ($retrievedAt ? 'Retrieved: ' . $retrievedAt . '. ' : '')
        . ($sourceUri ? 'Source URI: ' . $sourceUri : '')
    );
@endphp

<div class="mb-4">
    <div class="flex items-center justify-between mb-1">
        <label for="auth-res-field-{{ $name }}" class="text-xs font-medium text-slate-700">
            {{ $label }}
            @if($required)
                <span class="text-rose-600">*</span>
            @endif
        </label>
        @if($sourceLabel)
            <span class="inline-flex items-center gap-1 rounded-full border {{ $badgeColour }} px-2 py-0.5 text-[10px] font-medium"
                  title="{{ $tooltip }}">
                pre-filled / {{ $sourceLabel }}
                @if($sourceUri)
                    <a href="{{ $sourceUri }}" target="_blank" rel="noopener noreferrer"
                       class="ml-1 text-[10px] underline">link</a>
                @endif
            </span>
        @endif
    </div>

    @if($type === 'textarea')
        <textarea name="{{ $name }}" id="auth-res-field-{{ $name }}"
                  rows="{{ $rows }}"
                  @if($required) required @endif
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old($name, $value) }}</textarea>
    @elseif($type === 'number')
        <input type="number" step="any" name="{{ $name }}" id="auth-res-field-{{ $name }}"
               value="{{ old($name, $value) }}"
               @if($required) required @endif
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
    @else
        <input type="text" name="{{ $name }}" id="auth-res-field-{{ $name }}"
               value="{{ old($name, $value) }}"
               @if($required) required @endif
               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
    @endif

    @if($help)
        <p class="mt-1 text-[10px] text-slate-500">{{ $help }}</p>
    @endif

    {{-- Hidden provenance carry: posted back so FieldProvenanceWriter
         knows where each surviving field actually came from. --}}
    @if($prov)
        <input type="hidden" name="provenance[{{ $name }}][source]"       value="{{ $sourceLabel }}" />
        <input type="hidden" name="provenance[{{ $name }}][uri]"          value="{{ $sourceUri }}" />
        <input type="hidden" name="provenance[{{ $name }}][licence]"      value="{{ $licence }}" />
        <input type="hidden" name="provenance[{{ $name }}][licence_url]"  value="{{ $licenceUrl }}" />
        <input type="hidden" name="provenance[{{ $name }}][retrieved_at]" value="{{ $retrievedAt }}" />
    @endif
</div>
