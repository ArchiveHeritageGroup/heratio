{{--
  Graph Explorer - one entity (north-star #1204, next slice).

  Renders ONE entity (record, actor or term) as a human page: its label and key
  facts, then its connections grouped into human categories (other records,
  people, places, subjects, repository, broader / narrower). Every connection
  that has a slug is a CLICKABLE link back into this same explorer for the
  connected entity, so a visitor walks the graph hop by hop.

  Each page also links OUT to the machine linked-data document (/id/...) and to
  the canonical human page (the record page, the actor authority page, or the
  filtered browse for a term), so the explorer bridges the human and machine
  views and is never a dead end.

  All links are built from url() in the controller. The page covers PUBLISHED
  records only and never 500s: a node with no recorded connections shows a calm
  empty-state.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column theme layout.
--}}
@extends('theme::layouts.1col')
@section('title', $node['label'].' - '.__('Graph explorer'))

@section('content')
@php
  $typeChrome = [
    'record' => ['icon' => 'fas fa-file-alt', 'class' => 'text-primary', 'label' => __('Record')],
    'actor'  => ['icon' => 'fas fa-user',     'class' => 'text-success', 'label' => __('Agent')],
    'term'   => ['icon' => 'fas fa-tag',       'class' => 'text-info',    'label' => __('Concept')],
  ];
  $chrome = $typeChrome[$node['type']] ?? $typeChrome['record'];
@endphp
<div class="container py-4" style="max-width:960px">

  <nav class="mb-3" aria-label="breadcrumb">
    <a href="{{ url('/graph-explorer') }}" class="text-decoration-none small">
      <i class="fas fa-project-diagram me-1"></i>{{ __('Graph explorer') }}
    </a>
  </nav>

  <header class="mb-4">
    <div class="d-flex align-items-start">
      <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light me-3 flex-shrink-0"
            style="width:3rem;height:3rem">
        <i class="{{ $chrome['icon'] }} fs-4 {{ $chrome['class'] }}"></i>
      </span>
      <div class="flex-grow-1">
        <span class="badge bg-light text-muted border mb-1">{{ $chrome['label'] }} &middot; {{ $node['type_label'] }}</span>
        <h1 class="h3 mb-1">{{ $node['label'] }}</h1>
      </div>
    </div>
  </header>

  @if(!empty($node['description']))
    <p class="text-body mb-4" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($node['description'], 1200) }}</p>
  @endif

  @if(!empty($node['facts']))
    <dl class="row mb-4">
      @foreach($node['facts'] as $fact)
        <dt class="col-sm-3 text-muted small">{{ $fact['label'] }}</dt>
        <dd class="col-sm-9">{{ $fact['value'] }}</dd>
      @endforeach
    </dl>
  @endif

  <h2 class="h5 mb-3"><i class="fas fa-link text-muted me-2"></i>{{ __('Connections') }}</h2>

  @if(empty($node['groups']))
    <div class="alert alert-info" role="note">
      <i class="fas fa-info-circle me-1"></i>{{ __('No connections recorded for this entity yet. As the collection grows and is linked, related entities will appear here to follow.') }}
    </div>
  @else
    @foreach($node['groups'] as $group)
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
          <i class="{{ $group['icon'] }} text-muted me-2"></i>{{ __($group['heading']) }}
          <span class="badge bg-light text-muted border ms-1">{{ count($group['items']) }}</span>
        </div>
        <div class="list-group list-group-flush">
          @foreach($group['items'] as $item)
            @if(!empty($item['url']))
              <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action d-flex align-items-center">
                <span class="flex-grow-1">
                  {{ $item['label'] }}
                  @if(!empty($item['relation']))
                    <span class="badge bg-light text-muted border ms-1">{{ __($item['relation']) }}</span>
                  @endif
                </span>
                <i class="fas fa-chevron-right text-muted small"></i>
              </a>
            @else
              <span class="list-group-item d-flex align-items-center text-muted">
                <span class="flex-grow-1">{{ $item['label'] }}</span>
                <span class="small">{{ __('(not navigable)') }}</span>
              </span>
            @endif
          @endforeach
        </div>
      </div>
    @endforeach
  @endif

  <hr class="my-4">

  <div class="d-flex flex-wrap gap-2 align-items-center">
    @if(!empty($node['authority_url']))
      <a href="{{ $node['authority_url'] }}" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-external-link-alt me-1"></i>
        @if($node['type'] === 'record'){{ __('View the full record') }}
        @elseif($node['type'] === 'actor'){{ __('View the authority record') }}
        @else{{ __('Browse records with this term') }}
        @endif
      </a>
    @endif

    @if(!empty($node['machine_url']))
      <a href="{{ $node['machine_url'] }}" class="btn btn-outline-secondary btn-sm" rel="nofollow">
        <i class="fas fa-code me-1"></i>{{ __('Linked data') }} (<code>/id/...</code>)
      </a>
      <span class="small text-muted">{{ __('JSON-LD, Turtle and RDF/XML via content negotiation') }}</span>
    @endif
  </div>

</div>
@endsection
