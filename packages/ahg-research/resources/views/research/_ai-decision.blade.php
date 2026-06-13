{{--
  _ai-decision.blade.php - Accept/Reject control for an AI suggestion (heratio#1252)

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0

  Canonical implementation, used two ways:
    @include('research::research._ai-decision', ['slice'=>'writing', 'id'=>$v->id, 'decision'=>$v->ai_decision ?? null])
    <x-research::ai-decision slice="writing" :id="$v->id" :decision="$v->ai_decision" />  (component delegates here)

  Vars:
    - slice    (string)  one of: writing|question|analysis|grant|publication|copilot
    - id       (int)     primary-key id of the AI-produced row in that slice's table
    - decision (?string) the row's ai_decision: pending|accepted|rejected, or null
--}}
@php
    $slice    = $slice ?? '';
    $id       = $id ?? 0;
    $decision = $decision ?? null;
    $state    = $decision === null ? 'pending' : (string) $decision;
    $wrapId   = 'ai-decision-' . e($slice) . '-' . (int) $id;
@endphp

<span class="ai-decision d-inline-flex align-items-center gap-1" id="{{ $wrapId }}"
      data-slice="{{ e($slice) }}" data-id="{{ (int) $id }}">
    @if ($state === 'accepted')
        <span class="badge bg-success" data-role="badge">{{ __('AI - accepted') }}</span>
    @elseif ($state === 'rejected')
        <span class="badge bg-secondary" data-role="badge">{{ __('AI - rejected') }}</span>
    @else
        <span class="badge bg-info text-dark" data-role="label">{{ __('AI suggestion') }}</span>
        <button type="button" class="btn btn-sm btn-outline-success"
                data-role="accept" data-decision="accepted">{{ __('Accept') }}</button>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                data-role="reject" data-decision="rejected">{{ __('Reject') }}</button>
    @endif
</span>

@once
@push('scripts')
<script>
(function () {
    var ENDPOINT = @json(route('research.ai.decision'));

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : @json(csrf_token());
    }

    function badge(decision) {
        var span = document.createElement('span');
        if (decision === 'accepted') {
            span.className = 'badge bg-success';
            span.textContent = @json(__('AI - accepted'));
        } else {
            span.className = 'badge bg-secondary';
            span.textContent = @json(__('AI - rejected'));
        }
        span.setAttribute('data-role', 'badge');
        return span;
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.ai-decision [data-role="accept"], .ai-decision [data-role="reject"]');
        if (!btn) { return; }
        ev.preventDefault();

        var wrap = btn.closest('.ai-decision');
        if (!wrap) { return; }

        var payload = {
            slice: wrap.getAttribute('data-slice'),
            id: parseInt(wrap.getAttribute('data-id'), 10),
            decision: btn.getAttribute('data-decision')
        };

        wrap.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

        fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            if (data && data.ok) {
                wrap.innerHTML = '';
                wrap.appendChild(badge(data.decision || payload.decision));
            } else {
                wrap.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
            }
        })
        .catch(function () {
            wrap.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
        });
    });
})();
</script>
@endpush
@endonce
