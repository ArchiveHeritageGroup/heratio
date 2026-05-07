{{--
    Federation provenance badge.

    Renders a small "Harvested from {peer}" panel when the current IO was
    pulled in via federation. Safe to include unconditionally; renders
    nothing for local records.

    Usage from the (locked) IO show page:
        @includeIf('ahg-federation::_provenance-badge', ['objectId' => $io->id])
--}}

@php
    $federationProvenance = null;
    if (!empty($objectId) && app()->bound(\AhgFederation\Services\FederationProvenance::class)) {
        try {
            $federationProvenance = app(\AhgFederation\Services\FederationProvenance::class)->getProvenance((int) $objectId);
        } catch (\Throwable $e) {
            // Silently degrade so a federation outage never breaks the show page.
            $federationProvenance = null;
        }
    } elseif (!empty($objectId) && class_exists(\AhgFederation\Services\FederationProvenance::class)) {
        try {
            $federationProvenance = (new \AhgFederation\Services\FederationProvenance())->getProvenance((int) $objectId);
        } catch (\Throwable $e) {
            $federationProvenance = null;
        }
    }
@endphp

@if (!empty($federationProvenance))
    <div class="alert alert-info ahg-federation-provenance" role="status">
        <strong>{{ __('Harvested via federation') }}</strong>
        <div>
            {{ __('Source repository') }}:
            @if (!empty($federationProvenance['sourcePeerUrl']))
                <a href="{{ $federationProvenance['sourcePeerUrl'] }}" target="_blank" rel="noopener noreferrer">
                    {{ $federationProvenance['sourcePeerName'] }}
                </a>
            @else
                {{ $federationProvenance['sourcePeerName'] }}
            @endif
        </div>
        @if (!empty($federationProvenance['sourceOaiIdentifier']))
            <div>{{ __('Original identifier') }}: <code>{{ $federationProvenance['sourceOaiIdentifier'] }}</code></div>
        @endif
        @if (!empty($federationProvenance['harvestDate']))
            <div>{{ __('Last harvested') }}: {{ $federationProvenance['harvestDate'] }}</div>
        @endif
        @if (!empty($federationProvenance['metadataFormat']))
            <div>{{ __('Metadata format') }}: <code>{{ $federationProvenance['metadataFormat'] }}</code></div>
        @endif
    </div>
@endif
