{{--
    auth-res::_park-row - one parked-mention row in the /park table (Bootstrap 5).

    Args:
        $r              : object - listFor() row (joined park + mention + ner_entity + context)
        $archivistNames : array<int,string> - id -> display name
--}}
@php
    $typeBadges = [
        'PERSON'     => 'primary',
        'ORG'        => 'info',
        'GPE'        => 'success',
        'LOC'        => 'success',
        'PLACE'      => 'success',
        'ISAD_PLACE' => 'success',
    ];
    $hasNewCand = (int) ($r->new_candidate_available ?? 0) === 1;
    $uid = (int) $r->parked_by_user_id;
@endphp
<tr class="{{ $hasNewCand ? 'table-warning' : '' }}">
    <td class="text-muted small">#{{ (int) $r->mention_id }}</td>
    <td>
        <span class="badge bg-{{ $typeBadges[$r->entity_type] ?? 'secondary' }}">
            {{ $r->entity_type }}
        </span>
    </td>
    <td><strong>{{ $r->entity_value }}</strong></td>
    <td class="text-muted small">
        @if(!empty($r->io_slug))
            <a href="/{{ $r->io_slug }}" target="_blank" rel="noopener">
                {{ $r->io_title ?? ('Object #' . (int) $r->object_id) }}
                <i class="bi bi-box-arrow-up-right ms-1 small text-muted"></i>
            </a>
        @else
            <span class="text-muted">Object #{{ (int) $r->object_id }}</span>
        @endif
    </td>
    <td class="text-muted small">
        {{ $archivistNames[$uid] ?? ('user #' . $uid) }}
    </td>
    <td class="text-muted small text-nowrap">{{ $r->parked_at }}</td>
    <td class="small">
        <span title="{{ $r->reason }}">{{ \Illuminate\Support\Str::limit((string) $r->reason, 80) }}</span>
    </td>
    <td class="text-center">
        <span class="badge bg-secondary">{{ (int) ($r->candidate_count ?? 0) }}</span>
    </td>
    <td class="text-center">
        @if($hasNewCand)
            <span class="badge bg-warning text-dark"
                  title="Candidate set changed since parking ({{ $r->new_candidate_check_at ?? '' }})">
                <i class="bi bi-bell me-1"></i>{{ __('New') }}
            </span>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>
    <td class="text-nowrap">
        <a href="{{ route('auth-res.review.show', ['mention' => (int) $r->mention_id]) }}"
           class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye me-1"></i>{{ __('View') }}
        </a>
        <form method="POST"
              action="{{ route('auth-res.park.unpark', ['mention' => (int) $r->mention_id]) }}"
              class="d-inline"
              onsubmit="return confirm('{{ __('Un-park this mention? Candidates will be regenerated and the mention returns to the pending queue.') }}');">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-success">
                <i class="bi bi-play-fill me-1"></i>{{ __('Un-park & re-review') }}
            </button>
        </form>
    </td>
</tr>
