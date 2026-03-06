@extends('theme::layouts.1col')

@section('title', $term->name ?? 'Term')
@section('body-class', 'view term')

@section('content')
  <h1>{{ $term->name }}</h1>

  {{-- Identity --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity</h2>

    <div class="row mb-2">
      <div class="col-md-3 fw-bold">Name</div>
      <div class="col-md-9">{{ $term->name }}</div>
    </div>

    @if($taxonomyName)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Taxonomy</div>
        <div class="col-md-9">
          <a href="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">
            {{ $taxonomyName }}
          </a>
        </div>
      </div>
    @endif
  </section>

  {{-- Scope note --}}
  @if($scopeNote && $scopeNote->content)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Scope note</h2>
      <div>{!! nl2br(e($scopeNote->content)) !!}</div>
    </section>
  @endif

  {{-- Related descriptions --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Related descriptions</h2>
    <div class="row mb-2">
      <div class="col-md-3 fw-bold">Related descriptions</div>
      <div class="col-md-9">{{ number_format($relatedDescriptionsCount) }}</div>
    </div>
  </section>
@endsection
