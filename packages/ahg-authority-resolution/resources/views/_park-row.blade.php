{{--
    auth-res::_park-row - one parked-mention row in the /park table.

    Args:
        $r              : object - listFor() row (joined park + mention + ner_entity + context)
        $archivistNames : array<int,string> - id -> display name
--}}
<tr class="hover:bg-slate-50 {{ (int) $r->new_candidate_available === 1 ? 'bg-emerald-50/40' : '' }}">
    <td class="px-3 py-2 text-slate-500">{{ (int) $r->mention_id }}</td>
    <td class="px-3 py-2">
        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
            {{ $r->entity_type }}
        </span>
    </td>
    <td class="px-3 py-2 text-slate-900 font-medium break-words max-w-xs">{{ $r->entity_value }}</td>
    <td class="px-3 py-2 text-slate-500 text-xs">#{{ (int) $r->object_id }}</td>
    <td class="px-3 py-2 text-slate-700 text-xs">
        @php($uid = (int) $r->parked_by_user_id)
        {{ $archivistNames[$uid] ?? ('user #' . $uid) }}
    </td>
    <td class="px-3 py-2 text-slate-500 text-xs whitespace-nowrap">{{ $r->parked_at }}</td>
    <td class="px-3 py-2 text-slate-700 text-xs max-w-xs">
        <span title="{{ $r->reason }}">{{ \Illuminate\Support\Str::limit((string) $r->reason, 80) }}</span>
    </td>
    <td class="px-3 py-2 text-slate-700 text-xs">
        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ (int) $r->candidate_count }}</span>
    </td>
    <td class="px-3 py-2">
        @if((int) $r->new_candidate_available === 1)
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                  title="Candidate set changed since parking ({{ $r->new_candidate_check_at }})">
                new candidate
            </span>
        @else
            <span class="text-xs text-slate-400">-</span>
        @endif
    </td>
    <td class="px-3 py-2 whitespace-nowrap">
        <div class="flex items-center gap-2">
            <a href="{{ route('auth-res.review.show', ['mention' => (int) $r->mention_id]) }}"
               class="text-xs font-medium text-indigo-700 hover:underline">View</a>
            <form method="POST"
                  action="{{ route('auth-res.park.unpark', ['mention' => (int) $r->mention_id]) }}"
                  class="inline">
                @csrf
                <button type="submit"
                        class="rounded-md border border-emerald-400 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100"
                        onclick="return confirm('Unpark mention #{{ (int) $r->mention_id }} and re-run candidate generation?');">
                    Unpark + re-review
                </button>
            </form>
        </div>
    </td>
</tr>
