{{--
  #1731 - cultural notices, TK / BC labels and (for staff) active ICIP
  restrictions on a record view. Included by the archival, museum, library,
  gallery and DAM show pages with ['ioId' => ...]. Renders nothing when the
  record carries no ICIP data.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems.
  Part of Heratio. GNU AGPL v3 or later.
--}}
@php
  $__icip = class_exists(\AhgIcip\Services\IcipAccessService::class) ? app(\AhgIcip\Services\IcipAccessService::class) : null;
  $__staff = auth()->check() && \AhgCore\Services\AclService::hasPermission(auth()->id(), 'update', (int) $ioId);
  $__notices = $__icip ? $__icip->displayNotices((int) $ioId, auth()->check()) : collect();
  $__labels = $__icip ? $__icip->displayLabels((int) $ioId) : collect();
  $__restrictions = ($__icip && $__staff) ? $__icip->restrictionsFor((int) $ioId) : collect();
  $__severity = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];
@endphp
@if($__notices->isNotEmpty() || $__labels->isNotEmpty() || $__restrictions->isNotEmpty())
<div class="icip-record-notices mb-3">
  @foreach($__notices as $n)
    <div class="alert alert-{{ $__severity[$n->severity] ?? 'info' }} d-flex align-items-start mb-2" role="note">
      <i class="bi bi-exclamation-diamond me-2 fs-5" aria-hidden="true"></i>
      <div>
        <strong>{{ $n->name }}</strong>
        @if($n->community_name)<span class="ms-1">- {{ $n->community_name }}</span>@endif
        @php $__text = $n->custom_text ?: ($n->default_text ?: $n->description); @endphp
        @if($__text)<div class="small mt-1">{{ $__text }}</div>@endif
      </div>
    </div>
  @endforeach

  @if($__restrictions->isNotEmpty())
    <div class="alert alert-secondary mb-2 small" role="note">
      <i class="bi bi-lock me-1" aria-hidden="true"></i>
      {{ __('Restricted by a cultural protocol:') }}
      {{ $__restrictions->map(fn ($r) => (\AhgIcip\Services\IcipAccessService::RESTRICTION_TYPES[$r->restriction_type] ?? $r->restriction_type).($r->community_name ? ' ('.$r->community_name.')' : ''))->implode('; ') }}.
      {{ __('Visitors cannot open this record; you can because you are allowed to edit it.') }}
    </div>
  @endif

  @if($__labels->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="small text-muted">{{ __('Local Contexts labels:') }}</span>
      @foreach($__labels as $l)
        @php $__chip = $l->name.($l->community_name ? ' - '.$l->community_name : ''); @endphp
        @if($l->local_contexts_url)
          <a href="{{ $l->local_contexts_url }}" target="_blank" rel="noopener" @if(!empty($l->language_tag)) lang="{{ $l->language_tag }}" @endif class="badge text-white text-decoration-none {{ $l->category === 'BC' ? 'icip-bc-label' : 'icip-tk-label' }}" title="{{ $l->description }}">{{ $__chip }}</a>
        @else
          <span @if(!empty($l->language_tag)) lang="{{ $l->language_tag }}" @endif class="badge text-white {{ $l->category === 'BC' ? 'icip-bc-label' : 'icip-tk-label' }}" title="{{ $l->description }}">{{ $__chip }}</span>
        @endif
      @endforeach
    </div>
    <style>.icip-tk-label{background-color:#8B4513;color:#fff}.icip-bc-label{background-color:#228B22;color:#fff}</style>
  @endif
</div>
@endif
