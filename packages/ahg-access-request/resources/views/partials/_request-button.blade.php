{{-- Partial: Request Access Button - include on IO show pages for restricted records --}}
@auth
    <a href="{{ route('accessRequest.requestObject', $slug) }}" class="atom-btn-white btn-sm">
        <i class="fas fa-key me-1"></i>{{ __('Request Access') }}
    </a>
@endauth
