{{-- Partial: Provenance display panel for IO show page --}}
@if(isset($provenance) && $provenance)
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>{{ __('Provenance') }}</h6>
        </div>
        <div class="card-body">
            @if($provenance['record'] ?? false)
                @if($provenance['record']->summary ?? false)
                    <p>{{ $provenance['record']->summary }}</p>
                @endif
                @if($provenance['record']->acquisition_method ?? false)
                    <p><strong>{{ __('Acquisition:') }}</strong> {{ $provenance['record']->acquisition_method }}</p>
                @endif
            @endif
            @if(isset($provenance['events']) && $provenance['events']->count())
                <p class="text-muted mb-0">{{ $provenance['events']->count() }} provenance event(s) recorded.
                    <a href="{{ route('provenance.view', $slug) }}">View details</a>
                </p>
            @endif
        </div>
    </div>
@endif
