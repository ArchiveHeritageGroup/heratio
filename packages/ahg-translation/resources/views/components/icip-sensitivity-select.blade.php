{{--
  ICIP cultural-sensitivity select — dropdown populated from the
  vocabulary_label_cache for the icip vocabulary, filtered to the seven
  SensitivityLevel narrower concepts.

  Usage on edit forms:
    @include('ahg-translation::components.icip-sensitivity-select', [
        'name' => 'icip_sensitivity',
        'value' => $museum->icip_sensitivity ?? null,
    ])

  The full SKOS URI is the option value (so the resolver can localise it
  later). Labels render in the current request culture.
--}}
@php
    $name = $name ?? 'icip_sensitivity';
    $value = $value ?? old($name);

    // Seven canonical SensitivityLevel concepts in increasing-restriction order.
    $sensitivityUris = [
        'https://heratio.theahg.co.za/vocabulary/icip#Open',
        'https://heratio.theahg.co.za/vocabulary/icip#Restricted',
        'https://heratio.theahg.co.za/vocabulary/icip#CulturallySensitive',
        'https://heratio.theahg.co.za/vocabulary/icip#GenderRestricted',
        'https://heratio.theahg.co.za/vocabulary/icip#AgeRestricted',
        'https://heratio.theahg.co.za/vocabulary/icip#DeceasedPersonsContent',
        'https://heratio.theahg.co.za/vocabulary/icip#SacredSecret',
    ];
    $options = \AhgTranslation\Helpers\VocabularyOptions::pickFromUris(
        $sensitivityUris,
        app()->getLocale()
    );
@endphp

<select name="{{ $name }}" id="{{ $id ?? $name }}" class="form-select form-select-sm">
    <option value="">{{ __('— Not classified —') }}</option>
    @foreach($options as $opt)
        <option value="{{ $opt['uri'] }}" {{ $value === $opt['uri'] ? 'selected' : '' }}>
            {{ $opt['label'] }}
        </option>
    @endforeach
</select>
@if(empty($options))
    <small class="text-danger d-block mt-1">
        <i class="fas fa-exclamation-triangle me-1"></i>
        {{ __('ICIP vocabulary not loaded. Run: php artisan ahg:vocabulary-import data/vocabularies/icip.ttl --vocabulary=icip --format=turtle') }}
    </small>
@endif
