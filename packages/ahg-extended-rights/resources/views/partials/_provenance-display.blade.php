{{-- Provenance display component --}}
{{-- Usage: @include('ahg-extended-rights::partials._provenance-display', ['rightsHolder' => $holder, 'provenance' => $provenance]) --}}
@if(!empty($rightsHolder) || !empty($provenance))
<div class="provenance-display mb-2">
  @if(!empty($rightsHolder))
    <p class="mb-1"><strong>Rights Holder:</strong>
      @if(!empty($rightsHolder->slug))
        <a href="/{{ $rightsHolder->slug }}">{{ e($rightsHolder->authorized_form_of_name ?? '') }}</a>
      @else
        {{ e($rightsHolder->authorized_form_of_name ?? $rightsHolder->name ?? '') }}
      @endif
      @if(!empty($rightsHolder->uri))
        <a href="{{ $rightsHolder->uri }}" target="_blank" class="ms-1"><i class="fas fa-external-link-alt"></i></a>
      @endif
    </p>
  @endif
  @if(!empty($provenance))
    <p class="mb-1"><strong>Provenance:</strong> {{ e($provenance) }}</p>
  @endif
</div>
@endif
