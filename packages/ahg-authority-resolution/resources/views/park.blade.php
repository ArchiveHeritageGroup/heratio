{{--
    auth-res::park - Heratio authority-resolution parked-mention queue.

    Dedicated screen (Task 7). Lists every active row in ahg_mention_park,
    grouped by parked_at desc. The archivist can:
        - filter by parked_by / entity_type / reason text / new-candidate-only
        - sort by parked_at / entity_type / new-candidate flag
        - unpark + re-review (POST -> back to /admin/authority-resolution/review/{id})

    Tailwind 4 utility classes only (matches queue.blade.php).
--}}
@extends('theme::layouts.1col')

@section('title', 'Parked mentions')

@section('content')
<div class="px-4 py-6 max-w-7xl mx-auto">

    <div class="flex items-start justify-between gap-4 mb-2">
        <div>
            <p class="text-xs text-slate-500">
                <a href="{{ route('auth-res.queue') }}" class="hover:underline">&larr; Review queue</a>
            </p>
            <h1 class="text-2xl font-semibold text-slate-900 mt-1">Parked mentions</h1>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6">
        Mentions an archivist could not resolve at first pass. The Task 7 background
        scan flags rows whose candidate set has changed since parking - those rows
        get a green "new candidate" badge. Unparking flips state back to 'pending'
        and re-runs candidate generation + evidence scoring.
    </p>

    @if(session('notice'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('notice') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Top-line tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
        <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide font-medium text-amber-700">Total parked</div>
            <div class="text-2xl font-semibold text-amber-900 mt-1">{{ number_format($totalParked) }}</div>
        </div>
        <div class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide font-medium text-emerald-700">New candidate available</div>
            <div class="text-2xl font-semibold text-emerald-900 mt-1">{{ number_format($totalNewCandidate) }}</div>
        </div>
        <div class="rounded-md border border-slate-300 bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide font-medium text-slate-700">Archivists involved</div>
            <div class="text-2xl font-semibold text-slate-900 mt-1">{{ number_format(count($countsByArchivist)) }}</div>
        </div>
    </div>

    {{-- Dashboard widget partial --}}
    @include('auth-res::_park-dashboard-widget', [
        'countsByArchivist' => $countsByArchivist,
        'archivistNames' => $archivistNames,
    ])

    {{-- Filters --}}
    <form method="GET" action="{{ route('auth-res.park.index') }}"
          class="flex flex-wrap items-end gap-3 my-6 p-4 rounded-md border border-slate-200 bg-slate-50">
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Parked by</label>
            <select name="parked_by" class="rounded-md border-slate-300 text-sm">
                <option value="0">All archivists</option>
                @foreach($allParkedBy as $u)
                    <option value="{{ (int) $u->id }}" {{ $filterParkedBy === (int) $u->id ? 'selected' : '' }}>
                        {{ $u->name ?: 'user #' . (int) $u->id }} ({{ (int) $u->c }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Entity type</label>
            <select name="entity_type" class="rounded-md border-slate-300 text-sm">
                <option value="">All</option>
                @foreach(['PERSON','ORG','GPE','PLACE','LOC'] as $et)
                    <option value="{{ $et }}" {{ $filterEntityType === $et ? 'selected' : '' }}>{{ $et }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Reason contains</label>
            <input type="text" name="reason_q" value="{{ $filterReasonQ }}"
                   class="rounded-md border-slate-300 text-sm w-48" placeholder="text search">
        </div>
        <div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 mt-5">
                <input type="checkbox" name="new_candidate_only" value="1"
                       {{ $filterNewCandidateOnly ? 'checked' : '' }}
                       class="rounded border-slate-300">
                <span>New candidate only</span>
            </label>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Sort</label>
            <select name="sort_by" class="rounded-md border-slate-300 text-sm">
                @foreach([
                    'parked_at_desc' => 'Parked at (newest)',
                    'parked_at_asc' => 'Parked at (oldest)',
                    'entity_type' => 'Entity type',
                    'new_candidate' => 'New-candidate flag',
                ] as $key => $label)
                    <option value="{{ $key }}" {{ $filterSortBy === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Apply filters
        </button>
        <a href="{{ route('auth-res.park.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
    </form>

    {{-- Result table --}}
    <div class="overflow-x-auto border border-slate-200 rounded-md">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">#</th>
                    <th class="px-3 py-2 text-left font-medium">Entity</th>
                    <th class="px-3 py-2 text-left font-medium">Value</th>
                    <th class="px-3 py-2 text-left font-medium">Source IO</th>
                    <th class="px-3 py-2 text-left font-medium">Parked by</th>
                    <th class="px-3 py-2 text-left font-medium">Parked at</th>
                    <th class="px-3 py-2 text-left font-medium">Reason</th>
                    <th class="px-3 py-2 text-left font-medium">Cands</th>
                    <th class="px-3 py-2 text-left font-medium">Flag</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $r)
                    @include('auth-res::_park-row', [
                        'r' => $r,
                        'archivistNames' => $archivistNames,
                    ])
                @empty
                    <tr><td colspan="10" class="px-3 py-8 text-center text-slate-500">No parked mentions match the current filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(count($rows) === 200)
        <p class="text-xs text-slate-500 mt-2">Showing first 200 parked rows. Tighten the filters to narrow the list.</p>
    @endif

    <p class="text-xs text-slate-500 mt-4">
        Background scan: <code>php artisan auth-res:scan-parked</code> sweeps every parked row
        and flips <code>new_candidate_available=1</code> when the candidate set has changed
        since parking. Wire it via cron or <code>php artisan schedule:run</code>.
    </p>
</div>
@endsection
