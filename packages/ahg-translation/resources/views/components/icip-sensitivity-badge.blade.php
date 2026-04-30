{{--
  ICIP cultural-sensitivity badge — renders the localised label for a
  museum object's icip_sensitivity URI.

  Usage:
    @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $museum->icip_sensitivity])

  Renders nothing if $uri is empty. Colour code matches the gravity of the
  classification (open → secondary, restricted → warning, sacred → dark).
  Hover reveals the SKOS definition.
--}}
@php
    $uri = $uri ?? null;
    if ($uri) {
        $svc = app(\AhgCore\Services\VocabularyResolverService::class);
        $label = $svc->preferredLabel($uri, app()->getLocale());
        $fragment = str_contains($uri, '#') ? substr($uri, strrpos($uri, '#') + 1) : $uri;
        // Severity colour map — keyed on the fragment so the badge style
        // doesn't depend on the localised label.
        $colourMap = [
            'Open'                    => 'bg-success',
            'Restricted'              => 'bg-warning text-dark',
            'CulturallySensitive'     => 'bg-warning text-dark',
            'GenderRestricted'        => 'bg-danger',
            'AgeRestricted'           => 'bg-danger',
            'DeceasedPersonsContent'  => 'bg-dark',
            'SacredSecret'            => 'bg-dark',
        ];
        $cls = $colourMap[$fragment] ?? 'bg-secondary';
    }
@endphp

@if($uri ?? null)
    <span class="badge {{ $cls }} ms-1 icip-sensitivity-badge"
          title="{{ __('ICIP cultural-sensitivity classification') }} — {{ $uri }}"
          data-bs-toggle="tooltip">
        <i class="fas fa-shield-alt me-1" aria-hidden="true"></i>{{ $label }}
    </span>
@endif
