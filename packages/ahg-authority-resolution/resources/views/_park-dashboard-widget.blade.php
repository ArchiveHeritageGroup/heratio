{{--
    auth-res::_park-dashboard-widget - small partial: parked count per archivist.

    Reusable from /park (Task 7 dedicated screen) and from any future admin
    dashboard. Pulls $countsByArchivist + $archivistNames from the parent
    view (caller's responsibility).

    Args:
        $countsByArchivist : array<int,int>  - user_id => parked_count
        $archivistNames    : array<int,string> - user_id => display name
--}}
@if(!empty($countsByArchivist))
    <div class="rounded-md border border-slate-200 bg-white p-4">
        <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-3">Parked mentions by archivist</h3>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($countsByArchivist as $userId => $count)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('auth-res.park.index', ['parked_by' => (int) $userId]) }}"
                       class="text-indigo-700 hover:underline">
                        {{ $archivistNames[$userId] ?? ('user #' . (int) $userId) }}
                    </a>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                        {{ number_format($count) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
