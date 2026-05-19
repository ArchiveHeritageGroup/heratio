{{--
    auth-res::create-new - "Create new authority" pre-fill form (Bootstrap 5, Task 6).

    Renders one PERSON / ORG / PLACE record form pre-filled from PrefillEngine
    (external sources + mention context). Each pre-filled field carries a
    provenance badge so the archivist sees the source attribution before
    publishing.

    ISAAR-CPF mandatory fields (authorized_form_of_name, dates_of_existence,
    history) enforced via the HTML required attribute AND server-side
    in AuthorityCreator::assertIsaarCpf().
--}}
@extends('theme::layouts.1col')

@section('title', 'Create authority record from mention #' . $mention->id)

@section('content')
@php
    $typeBadges = [
        'PERSON' => 'primary',
        'ORG'    => 'info',
        'PLACE'  => 'success',
    ];
    $entityType = $isPlace ? 'PLACE' : $mention->entity_type;
@endphp
<div class="container py-4">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('auth-res.queue') }}">{{ __('Authority Resolution') }}</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('auth-res.review.show', ['mention' => $mention->id]) }}">
                    {{ __('Mention') }} #{{ (int) $mention->id }}
                </a>
            </li>
            <li class="breadcrumb-item active">{{ __('Create new') }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            <i class="bi bi-plus-circle me-2"></i>{{ __('Create new authority record') }}
            <span class="badge bg-{{ $typeBadges[$entityType] ?? 'secondary' }} ms-2">
                {{ $entityType }}
            </span>
        </h1>
        <a href="{{ route('auth-res.settings.show') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-sliders me-1"></i>{{ __('Configure lookup sources') }}
        </a>
    </div>

    @if(session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">

        {{-- ================ MAIN FORM ================ --}}
        <div class="col-lg-8">

            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="bi bi-magic me-1"></i>{{ __('Pre-filled from external sources + context') }}</strong>
                </div>
                <div class="card-body">

                    <p class="small text-muted mb-3">
                        {{ __('Source mention') }}:
                        <strong>{{ $mention->entity_value }}</strong>
                        {{ __('from') }}
                        <em>Object #{{ (int) $mention->object_id }}</em>
                    </p>

                    <form method="POST"
                          action="{{ route('auth-res.review.createNew', ['mention' => $mention->id]) }}">
                        @csrf

                        @if($isPlace)

                            @include('auth-res::_prefill-field', [
                                'name'     => 'name',
                                'label'    => __('Place name'),
                                'value'    => $mergedFields['name'] ?? $mention->entity_value,
                                'prov'     => $provenance['name'] ?? null,
                                'required' => true,
                                'help'     => __('Required. Becomes the term.name value.'),
                            ])

                            <div class="row g-2">
                                <div class="col-md-6">
                                    @include('auth-res::_prefill-field', [
                                        'name'     => 'latitude',
                                        'label'    => __('Latitude'),
                                        'value'    => $mergedFields['latitude'] ?? null,
                                        'prov'     => $provenance['latitude'] ?? null,
                                        'type'     => 'number',
                                        'required' => false,
                                        'help'     => __('Decimal degrees. Optional but if provided longitude is required.'),
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('auth-res::_prefill-field', [
                                        'name'     => 'longitude',
                                        'label'    => __('Longitude'),
                                        'value'    => $mergedFields['longitude'] ?? null,
                                        'prov'     => $provenance['longitude'] ?? null,
                                        'type'     => 'number',
                                        'required' => false,
                                        'help'     => __('Decimal degrees.'),
                                    ])
                                </div>
                            </div>

                            @include('auth-res::_prefill-field', [
                                'name'     => 'descriptive_standard',
                                'label'    => __('Descriptive standard'),
                                'value'    => $mergedFields['descriptive_standard'] ?? 'ISDF',
                                'prov'     => $provenance['descriptive_standard'] ?? null,
                                'required' => false,
                                'help'     => __('Default ISDF for places.'),
                            ])

                        @else /* PERSON / ORG */

                            @include('auth-res::_prefill-field', [
                                'name'     => 'authorized_form_of_name',
                                'label'    => __('Authorized form of name (ISAAR-CPF mandatory)'),
                                'value'    => $mergedFields['authorized_form_of_name'] ?? $mention->entity_value,
                                'prov'     => $provenance['authorized_form_of_name'] ?? null,
                                'required' => true,
                            ])

                            @include('auth-res::_prefill-field', [
                                'name'     => 'dates_of_existence',
                                'label'    => __('Dates of existence (ISAAR-CPF mandatory)'),
                                'value'    => $mergedFields['dates_of_existence'] ?? '',
                                'prov'     => $provenance['dates_of_existence'] ?? null,
                                'required' => true,
                                'help'     => __('e.g. 1918-2013, or fl. 1500, or unknown.'),
                            ])

                            @include('auth-res::_prefill-field', [
                                'name'     => 'history',
                                'label'    => __('History / biographical note (ISAAR-CPF mandatory)'),
                                'value'    => $mergedFields['history'] ?? '',
                                'prov'     => $provenance['history'] ?? null,
                                'type'     => 'textarea',
                                'required' => true,
                                'rows'     => 6,
                            ])

                            @include('auth-res::_prefill-field', [
                                'name'     => 'descriptive_standard',
                                'label'    => __('Descriptive standard'),
                                'value'    => $mergedFields['descriptive_standard'] ?? 'ISAAR-CPF',
                                'prov'     => $provenance['descriptive_standard'] ?? null,
                                'required' => false,
                            ])

                        @endif

                        <hr>

                        <div class="d-flex gap-2 align-items-center">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save me-1"></i>{{ __('Create authority record') }}
                            </button>
                            <a href="{{ route('auth-res.review.show', ['mention' => $mention->id]) }}"
                               class="btn btn-link">
                                <i class="bi bi-arrow-left me-1"></i>{{ __('Cancel') }}
                            </a>
                        </div>

                        <p class="text-muted small mt-3 mb-0">
                            {{ __('Submitting writes the new record to MySQL, emits per-field provenance triples to Fuseki (graph') }}
                            <code>urn:heratio:auth-res:graph:field-provenance</code>{{ __('), records a') }}
                            <code>create_new</code>
                            {{ __('decision, and back-links') }}
                            <code>ahg_ner_entity.linked_actor_id</code>.
                        </p>
                    </form>

                </div>
            </div>

        </div>

        {{-- ================ SIDEBAR: SOURCE CONTEXT + LOOKUP DEBUG ================ --}}
        <div class="col-lg-4">

            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="bi bi-quote me-1"></i>{{ __('Mention') }}</strong>
                </div>
                <div class="card-body">
                    <div class="fw-bold text-break">{{ $mention->entity_value }}</div>
                    <div class="text-muted small mt-1">
                        {{ $mention->entity_type }} / state {{ $mention->state }}
                    </div>
                    @if($context && $context->surrounding_text_before)
                        <div class="bg-light p-2 rounded small mt-3" style="font-family: monospace; line-height: 1.5;">
                            <span class="text-muted">...{{ $context->surrounding_text_before }}</span><mark class="bg-warning"><strong>{{ $mention->entity_value }}</strong></mark><span class="text-muted">{{ $context->surrounding_text_after }}...</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="bi bi-info-circle me-1"></i>{{ __('External lookup results') }}</strong>
                </div>
                <div class="card-body p-0">
                    @if(empty($lookupResults))
                        <p class="text-muted small p-3 mb-0">
                            {{ __('No external sources returned results. Either every source is disabled in') }}
                            <a href="{{ route('auth-res.settings.show') }}">{{ __('Lookup settings') }}</a>{{ __(', or none matched this query.') }}
                        </p>
                    @else
                        <ul class="list-group list-group-flush small">
                            @foreach($lookupResults as $source => $candidates)
                                <li class="list-group-item py-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong>{{ $source }}</strong>
                                        <span class="badge bg-success">{{ count($candidates) }} {{ __('hit(s)') }}</span>
                                    </div>
                                    @foreach(array_slice($candidates, 0, 5) as $c)
                                        <div class="border-start ps-2 mt-1" style="border-color: #dee2e6 !important;">
                                            <a href="{{ $c['external_uri'] ?? '#' }}" target="_blank" rel="noopener">
                                                {{ $c['authorized_name'] ?? '(unnamed)' }}
                                            </a>
                                            @if(!empty($c['dates_of_existence']))
                                                <span class="text-muted">/ {{ $c['dates_of_existence'] }}</span>
                                            @endif
                                            @if(!empty($c['history_snippet']))
                                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($c['history_snippet'], 120) }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong><i class="bi bi-shield-check me-1"></i>{{ __('After create') }}</strong>
                </div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li>{{ __('New record inserted (object + actor/term + i18n + slug).') }}</li>
                        <li>
                            {{ __('Per-field provenance written to Fuseki graph') }}:
                            <code>urn:heratio:auth-res:graph:field-provenance</code>
                        </li>
                        <li>{{ __('create_new decision row written to ahg_mention_decision.') }}</li>
                        <li>{{ __('Mention state advances to new_record_created.') }}</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
