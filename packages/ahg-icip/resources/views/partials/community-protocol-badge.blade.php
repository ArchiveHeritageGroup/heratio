{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+

  #1388 / #1406 P1 - TK/BC community-protocol badge for an information object.
  Renders one badge per protocol attached to the record, whether:
    - DIRECTLY on the object  (object_protocol, #1406 P1), or
    - INHERITED from a tagged term (term_protocol).

  A Local Contexts Label is community-authored/enforcing; a Notice is an
  institution-applied advisory placeholder - both are meant to be publicly
  VISIBLE (the restricted content is gated elsewhere; the label is not).
  Renders nothing when the object carries no protocols, or when neither
  protocol table is present (fresh install / CI schema without the migrations).

  Usage:  @include('icip::partials.community-protocol-badge', ['ioId' => $io->id])
--}}
@php
  use AhgCore\Services\TermProtocolService as __TPS;

  $__cpRows = collect();
  try {
      $__cpRows = __TPS::protocolsForObject((int) $ioId)   // direct object protocols (P1)
          ->map(function ($r) { $r->_scope = 'object'; return $r; })
          ->concat(
              __TPS::protocolsForRecord((int) $ioId)         // inherited from tagged terms
                  ->map(function ($r) { $r->_scope = 'term'; return $r; })
          );
  } catch (\Throwable $e) {
      $__cpRows = collect();
  }

  // Restricted conditions render red; usage-obligation labels amber; else neutral.
  $__cpClass = function (?string $cond): string {
      if (in_array($cond, __TPS::RESTRICTED, true)) {
          return 'bg-danger';
      }
      if (in_array($cond, ['attribution', 'non_commercial'], true)) {
          return 'bg-warning text-dark';
      }
      return 'bg-secondary';
  };
  $__cpLabel = function (?string $cond): string {
      return ucwords(str_replace('_', ' ', (string) ($cond ?: 'open')));
  };
@endphp

@if($__cpRows->isNotEmpty())
  <div class="community-protocol-badges mb-2" aria-label="{{ __('Community protocols') }}">
    <small class="text-muted me-2"><strong>{{ __('Community protocol') }}</strong></small>
    @foreach($__cpRows as $__p)
      @php
        $__fam    = strtoupper((string) ($__p->label_family ?? ''));
        $__meta   = __TPS::labelMeta($__p->label_code ?? null);
        $__name   = $__meta->name ?? ($__p->label_code ? ucwords(str_replace('_', ' ', $__p->label_code)) : $__cpLabel($__p->access_condition));
        $__isNote = (int) ($__p->is_notice ?? 0) === 1;
        $__tip    = trim(
            ($__fam ? "$__fam " : '')
            . ($__isNote ? __('Notice') : __('Label')) . ': '
            . ($__meta->description ?? $__cpLabel($__p->access_condition) . ' access')
            . (($__p->_scope ?? '') === 'object' ? ' (' . __('on this item') . ')' : ' (' . __('inherited from a subject term') . ')')
        );
      @endphp
      <span class="badge {{ $__cpClass($__p->access_condition ?? null) }} me-1"
            data-bs-toggle="tooltip"
            title="{{ $__tip }}">
        <i class="fas fa-hands-holding-circle me-1"></i>{{ $__fam ? $__fam . ' ' : '' }}{{ $__name }}@if($__isNote) <span class="opacity-75">· {{ __('Notice') }}</span>@endif
      </span>
    @endforeach
  </div>
@endif
