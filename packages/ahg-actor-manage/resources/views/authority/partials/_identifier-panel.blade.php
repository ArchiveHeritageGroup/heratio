@php
/**
 * Embeddable panel: External identifier badges for actor view pages.
 * Usage: @include('ahg-actor-manage::authority.partials._identifier-panel', ['actorId' => $actorId])
 */
$actorId = $actorId ?? 0;
if (!$actorId) return;

$identifierService = new \AhgActorManage\Services\AuthorityIdentifierService();
$identifiers = $identifierService->getIdentifiers($actorId);
if (empty($identifiers)) return;
@endphp

<div class="card mb-3 authority-identifier-panel">
  <div class="card-header py-2" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-link me-1"></i>{{ __('External Identifiers') }}
  </div>
  <div class="card-body py-2">
    @foreach ($identifiers as $ident)
      <a href="{{ e($ident->uri ?? '#') }}"
         target="_blank" rel="noopener"
         class="badge bg-secondary text-decoration-none me-1 mb-1"
         title="{{ e($ident->identifier_value) }}">
        {{ strtoupper($ident->identifier_type) }}
        @if ($ident->is_verified)
          <i class="fas fa-check-circle ms-1"></i>
        @endif
      </a>
    @endforeach
    <a href="{{ route('actor.identifiers', ['actorId' => $actorId]) }}"
       class="btn btn-sm atom-btn-white ms-2">
      <i class="fas fa-edit"></i>
    </a>
  </div>
</div>
