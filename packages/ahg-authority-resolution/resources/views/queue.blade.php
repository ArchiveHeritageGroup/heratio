{{--
    auth-res::queue - Heratio authority-resolution review queue

    Pending-mentions list. Click any row to jump to /admin/authority-resolution/review/{id}.
    Tailwind 4 utility classes only (the ahg-theme-b5 package name is a misnomer; the
    Laravel Heratio CSS framework is Tailwind 4 - see feedback_heratio_tailwind.md).
--}}
@extends('theme::layouts.1col')

@section('title', 'Authority resolution queue')

@section('content')
<div class="px-4 py-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">Authority resolution - review queue</h1>
    <p class="text-sm text-slate-600 mb-6">
        Pending NER mentions promoted into the authority-resolution workflow.
        Pick a mention to see its evidence packet and ranked candidates.
    </p>

    @if(session('notice'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('notice') }}
        </div>
    @endif

    {{-- State summary tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-6" id="auth-res-state-tiles">
        @php
            $tiles = [
                'pending'  => ['Pending', 'bg-slate-100 text-slate-800 border-slate-300'],
                'linked'   => ['Linked', 'bg-emerald-50 text-emerald-800 border-emerald-300'],
                'parked'   => ['Parked', 'bg-amber-50 text-amber-800 border-amber-300'],
                'rejected' => ['Rejected', 'bg-rose-50 text-rose-800 border-rose-300'],
                'new_record_created' => ['New record', 'bg-indigo-50 text-indigo-800 border-indigo-300'],
            ];
        @endphp
        @foreach($tiles as $state => [$label, $cls])
            <a href="{{ route('auth-res.queue', ['state' => $state]) }}"
               class="block rounded-md border px-4 py-3 {{ $cls }} hover:opacity-80 transition">
                <div class="text-xs uppercase tracking-wide font-medium">{{ $label }}</div>
                <div class="text-2xl font-semibold mt-1">{{ number_format($counts[$state] ?? 0) }}</div>
            </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('auth-res.queue') }}"
          class="flex flex-wrap items-end gap-3 mb-6 p-4 rounded-md border border-slate-200 bg-slate-50">
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
            <label class="block text-xs font-medium text-slate-700 mb-1">State</label>
            <select name="state" class="rounded-md border-slate-300 text-sm">
                @foreach(['pending','linked','parked','rejected','new_record_created','any'] as $s)
                    <option value="{{ $s }}" {{ $filterState === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Object ID</label>
            <input type="number" name="object_id" value="{{ $filterObjectId ?: '' }}"
                   class="rounded-md border-slate-300 text-sm w-32" placeholder="any">
        </div>
        <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Apply filters
        </button>
        <a href="{{ route('auth-res.queue') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
    </form>

    {{-- Result table --}}
    <div class="overflow-x-auto border border-slate-200 rounded-md">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">#</th>
                    <th class="px-3 py-2 text-left font-medium">Entity type</th>
                    <th class="px-3 py-2 text-left font-medium">Value</th>
                    <th class="px-3 py-2 text-left font-medium">Source IO</th>
                    <th class="px-3 py-2 text-left font-medium">State</th>
                    <th class="px-3 py-2 text-left font-medium">Candidates</th>
                    <th class="px-3 py-2 text-left font-medium">Promoted</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-slate-500">{{ $r->id }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                {{ $r->entity_type }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-900 font-medium">{{ $r->entity_value }}</td>
                        <td class="px-3 py-2 text-slate-500">{{ $r->object_id }}</td>
                        <td class="px-3 py-2">
                            <span class="text-xs text-slate-600">{{ $r->state }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                {{ (int) $r->candidate_count }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-500 text-xs">{{ $r->promoted_at }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('auth-res.review.show', ['mention' => $r->id]) }}"
                               class="text-sm font-medium text-indigo-700 hover:underline">
                                Review &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">No mentions match the current filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->count() === 200)
        <p class="text-xs text-slate-500 mt-2">Showing first 200 rows. Tighten the filters to narrow the list.</p>
    @endif
</div>
@endsection
