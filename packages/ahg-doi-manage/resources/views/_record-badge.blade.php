{{-- Partial: DOI badge --}}
@props(['doi' => null])
@if($doi)<span class="badge bg-info" title="DOI: {{ $doi }}"><i class="fas fa-link me-1"></i>{{ $doi }}</span>@endif
