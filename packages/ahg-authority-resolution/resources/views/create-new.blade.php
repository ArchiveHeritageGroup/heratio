{{--
    auth-res::create-new

    Task 6 of the AHG Authority Resolution Engine. Pre-filled form for
    creating a new authority record from a mention. Each input shows a
    provenance badge naming the source (VIAF / Wikidata / GeoNames /
    mention context / etc) and links out to that source's record so the
    archivist can verify before publishing.

    ISAAR-CPF mandatory fields (authorized_form_of_name, dates_of_existence,
    history) enforced via the HTML `required` attribute AND server-side
    in AuthorityCreator::assertIsaarCpf().
--}}
@extends('theme::layouts.1col')

@section('title', 'Create authority record from mention #' . $mention->id)

@section('content')
<div class="px-4 py-6 max-w-screen-xl mx-auto">

    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <p class="text-xs text-slate-500">
                <a href="{{ route('auth-res.review.show', ['mention' => $mention->id]) }}"
                   class="hover:underline">&larr; Back to review</a>
                &middot;
                <a href="{{ route('auth-res.queue') }}" class="hover:underline">Queue</a>
            </p>
            <h1 class="text-xl font-semibold text-slate-900 mt-1">
                Create {{ $isPlace ? 'place' : ($mention->entity_type === 'ORG' ? 'organisation' : 'person') }} authority
                <span class="text-slate-400 font-normal text-sm">from mention #{{ $mention->id }}</span>
            </h1>
        </div>
        <a href="{{ route('auth-res.settings.show') }}"
           class="text-xs text-indigo-700 hover:underline">Configure lookup sources</a>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ============================ LEFT: source context ============================ --}}
        <aside class="lg:col-span-4 space-y-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">Mention</h3>
                <div class="text-sm text-slate-900 font-semibold break-words">{{ $mention->entity_value }}</div>
                <div class="text-[11px] text-slate-500 mt-1">
                    {{ $mention->entity_type }} / state {{ $mention->state }}
                </div>
                @if($context && $context->surrounding_text_before)
                    <p class="mt-3 text-xs leading-relaxed text-slate-600">
                        <span class="text-slate-400">{{ $context->surrounding_text_before }}</span><mark class="bg-yellow-200 text-slate-900 px-1 rounded">{{ $mention->entity_value }}</mark><span class="text-slate-400">{{ $context->surrounding_text_after }}</span>
                    </p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="text-xs uppercase tracking-wide font-medium text-slate-500 mb-2">External lookup results</h3>
                @if(empty($lookupResults))
                    <p class="text-xs text-slate-500 italic">
                        No external sources returned results. Either every source is
                        disabled in settings, or none matched this query.
                    </p>
                @else
                    @foreach($lookupResults as $source => $candidates)
                        <div class="mb-3">
                            <div class="text-[11px] font-semibold uppercase text-slate-600 mb-1">{{ $source }} ({{ count($candidates) }})</div>
                            <ul class="text-xs space-y-1">
                                @foreach(array_slice($candidates, 0, 5) as $c)
                                    <li class="border-l-2 border-slate-200 pl-2">
                                        <a href="{{ $c['external_uri'] ?? '#' }}" target="_blank" rel="noopener"
                                           class="text-indigo-700 hover:underline">{{ $c['authorized_name'] ?? '(unnamed)' }}</a>
                                        @if(!empty($c['dates_of_existence']))
                                            <span class="text-slate-400">/ {{ $c['dates_of_existence'] }}</span>
                                        @endif
                                        @if(!empty($c['history_snippet']))
                                            <div class="text-[10px] text-slate-500 mt-0.5">{{ \Illuminate\Support\Str::limit($c['history_snippet'], 120) }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                @endif
            </div>
        </aside>

        {{-- ============================ MIDDLE / RIGHT: the form ============================ --}}
        <section class="lg:col-span-8">
            <form method="POST"
                  action="{{ route('auth-res.review.createNew', ['mention' => $mention->id]) }}"
                  class="space-y-1 rounded-lg border border-slate-200 bg-white p-6">
                @csrf

                @if($isPlace)
                    @include('auth-res::_prefill-field', [
                        'name' => 'name',
                        'label' => 'Name',
                        'value' => $mergedFields['name'] ?? $mention->entity_value,
                        'prov' => $provenance['name'] ?? null,
                        'required' => true,
                    ])

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @include('auth-res::_prefill-field', [
                            'name' => 'latitude',
                            'label' => 'Latitude',
                            'value' => $mergedFields['latitude'] ?? null,
                            'prov' => $provenance['latitude'] ?? null,
                            'type' => 'number',
                            'required' => false,
                            'help' => 'Decimal degrees. Required only if a source supplied one; archivist may add manually.',
                        ])
                        @include('auth-res::_prefill-field', [
                            'name' => 'longitude',
                            'label' => 'Longitude',
                            'value' => $mergedFields['longitude'] ?? null,
                            'prov' => $provenance['longitude'] ?? null,
                            'type' => 'number',
                            'required' => false,
                            'help' => 'Decimal degrees.',
                        ])
                    </div>

                    @include('auth-res::_prefill-field', [
                        'name' => 'descriptive_standard',
                        'label' => 'Descriptive standard',
                        'value' => $mergedFields['descriptive_standard'] ?? 'ISDF',
                        'prov' => $provenance['descriptive_standard'] ?? null,
                        'required' => false,
                        'help' => 'Default ISDF for places.',
                    ])
                @else
                    @include('auth-res::_prefill-field', [
                        'name' => 'authorized_form_of_name',
                        'label' => 'Authorized form of name (ISAAR-CPF mandatory)',
                        'value' => $mergedFields['authorized_form_of_name'] ?? $mention->entity_value,
                        'prov' => $provenance['authorized_form_of_name'] ?? null,
                        'required' => true,
                    ])
                    @include('auth-res::_prefill-field', [
                        'name' => 'dates_of_existence',
                        'label' => 'Dates of existence (ISAAR-CPF mandatory)',
                        'value' => $mergedFields['dates_of_existence'] ?? '',
                        'prov' => $provenance['dates_of_existence'] ?? null,
                        'required' => true,
                        'help' => 'e.g. 1918-2013, or fl. 1500, or unknown.',
                    ])
                    @include('auth-res::_prefill-field', [
                        'name' => 'history',
                        'label' => 'History / biographical note (ISAAR-CPF mandatory)',
                        'value' => $mergedFields['history'] ?? '',
                        'prov' => $provenance['history'] ?? null,
                        'type' => 'textarea',
                        'required' => true,
                        'rows' => 6,
                    ])
                    @include('auth-res::_prefill-field', [
                        'name' => 'descriptive_standard',
                        'label' => 'Descriptive standard',
                        'value' => $mergedFields['descriptive_standard'] ?? 'ISAAR-CPF',
                        'prov' => $provenance['descriptive_standard'] ?? null,
                        'required' => false,
                    ])
                @endif

                <div class="mt-6 flex items-center justify-between pt-4 border-t border-slate-100">
                    <a href="{{ route('auth-res.review.show', ['mention' => $mention->id]) }}"
                       class="text-sm text-slate-600 hover:underline">Cancel</a>
                    <button type="submit"
                            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Create authority record and link mention
                    </button>
                </div>

                <p class="text-[10px] text-slate-400 leading-relaxed pt-4 border-t border-slate-100 mt-4">
                    Submitting writes the new record to MySQL, emits per-field provenance
                    triples to Fuseki (graph
                    <code>urn:heratio:auth-res:graph:field-provenance</code>), records
                    a <code>create_new</code> decision, and back-links
                    <code>ahg_ner_entity.linked_actor_id</code>.
                </p>
            </form>
        </section>
    </div>
</div>
@endsection
