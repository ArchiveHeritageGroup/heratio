{{-- Catalog layout - formal catalog entry view --}}
<div class="catalog-entry border-bottom pb-4 mb-4">
    <div class="row">
        @if($digitalObject && ($data['thumbnail_size'] ?? '') !== 'none')
        <div class="col-md-3 mb-3">
            <img src="{{ $digitalObject->path }}"
                 class="img-fluid rounded"
                 alt="{{ $object->title ?? '' }}">
        </div>
        @endif

        <div class="col-md-{{ $digitalObject ? '9' : '12' }}">
            <div class="catalog-header mb-3">
                @if(!empty($fields['identity']['identifier']))
                <span class="catalog-number text-muted me-3">{{ $fields['identity']['identifier']['value'] }}</span>
                @endif

                @if(!empty($fields['identity']['artist']) || !empty($fields['identity']['creator']))
                <span class="catalog-creator">
                    <strong>{{ $fields['identity']['artist']['value'] ?? $fields['identity']['creator']['value'] ?? '' }}</strong>
                </span>
                @endif
            </div>

            <h4 class="catalog-title mb-2">
                <em>{{ $object->title ?? 'Untitled' }}</em>
                @if(!empty($fields['identity']['date']))
                <span class="text-muted">, {{ $fields['identity']['date']['value'] }}</span>
                @endif
            </h4>

            {{-- Physical description line --}}
            <p class="catalog-physical text-muted mb-2">
                @php
                $physParts = [];
                if (!empty($fields['identity']['medium'])) $physParts[] = $fields['identity']['medium']['value'];
                if (!empty($fields['identity']['dimensions'])) $physParts[] = $fields['identity']['dimensions']['value'];
                if (!empty($fields['identity']['materials'])) $physParts[] = $fields['identity']['materials']['value'];
                @endphp
                {{ implode('; ', $physParts) }}
            </p>

            {{-- Description --}}
            @if(!empty($fields['description']['description']) || !empty($fields['description']['scope_content']))
            <p class="catalog-description">
                {{ Str::limit(strip_tags($fields['description']['description']['value'] ?? $fields['description']['scope_content']['value'] ?? ''), 400) }}
            </p>
            @endif

            {{-- Provenance --}}
            @if(!empty($fields['context']['provenance']))
            <p class="catalog-provenance small text-muted">
                <strong>Provenance:</strong> {{ Str::limit(strip_tags($fields['context']['provenance']['value']), 200) }}
            </p>
            @endif
        </div>
    </div>
</div>
