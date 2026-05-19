{{--
    auth-res::_park-dashboard-widget - small Bootstrap 5 card: parked count per archivist.

    Reusable from /park (Task 7 dedicated screen) and from any future admin
    dashboard. Pulls $countsByArchivist + $archivistNames from the parent
    view (caller's responsibility).

    Args:
        $countsByArchivist : array<int,int>  - user_id => parked_count
        $archivistNames    : array<int,string> - user_id => display name
--}}
@if(!empty($countsByArchivist))
    <div class="card mb-3">
        <div class="card-header">
            <strong><i class="bi bi-people me-1"></i>{{ __('Parked mentions by archivist') }}</strong>
        </div>
        <ul class="list-group list-group-flush small">
            @foreach($countsByArchivist as $userId => $count)
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <a href="{{ route('auth-res.park.index', ['parked_by' => (int) $userId]) }}">
                        {{ $archivistNames[$userId] ?? ('user #' . (int) $userId) }}
                    </a>
                    <span class="badge bg-secondary rounded-pill">{{ number_format($count) }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
