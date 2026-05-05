@extends('theme::layouts.1col')

@section('title', __('Move %1%', ['%1%' => $io->title ?? 'Untitled']))
@section('body-class', 'move informationobject')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ __('Move :title', ['title' => $io->title ?? 'Untitled']) }}</h1>
    <span class="small text-muted">{{ __('Browse to the new parent and click "Move here", or type to search.') }}</span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p class="mb-0">{{ $error }}</p>
      @endforeach
    </div>
  @endif

  {{-- Search box: matches identifier or title (LIKE). Submitting the form
       reloads the page with ?query=... and shows hits in the table below. --}}
  <form method="GET" action="{{ route('informationobject.move', $io->slug) }}" class="d-inline-block mb-3">
    @if(!empty($browsedParent->slug))
      <input type="hidden" name="parent" value="{{ $browsedParent->slug }}">
    @endif
    <div class="input-group input-group-sm" style="min-width:380px;">
      <input type="text" name="query" value="{{ $query ?? '' }}"
             class="form-control" placeholder="{{ __('Search title or identifier') }}">
      <button type="submit" class="btn btn-outline-secondary">
        <i class="fas fa-search"></i> {{ __('Search') }}
      </button>
      @if(!empty($query))
        <a href="{{ route('informationobject.move', ['slug' => $io->slug, 'parent' => $browsedParent->slug ?? null]) }}"
           class="btn btn-outline-secondary">{{ __('Clear') }}</a>
      @endif
    </div>
  </form>

  {{-- Breadcrumb of the BROWSED parent's ancestors. Click any rung to jump
       there — same pattern as PSIS's move template. --}}
  @if(!empty($breadcrumb) || !empty($browsedParent))
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
      <ol class="breadcrumb">
        @foreach($breadcrumb as $item)
          @if(!empty($item->slug))
            <li class="breadcrumb-item">
              <a href="{{ route('informationobject.move', ['slug' => $io->slug, 'parent' => $item->slug]) }}">
                {{ $item->title ?? $item->slug }}
              </a>
            </li>
          @endif
        @endforeach
        @if(!empty($browsedParent))
          <li class="breadcrumb-item active" aria-current="page">
            {{ $browsedParent->title ?? ($browsedParent->slug ?? __('Root')) }}
          </li>
        @endif
      </ol>
    </nav>
  @endif

  {{-- Children of the browsed parent (or search hits). Click a row to drill
       down (re-load with ?parent=<that slug>). Rows that are descendants of
       the moving record are not click-through (illegal destinations). --}}
  @if(count($results))
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th style="width:18%">{{ __('Identifier') }}</th>
            <th>{{ __('Title') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($results as $row)
            <tr>
              <td>{{ $row->identifier }}</td>
              <td>
                @if($row->is_descendant_of_moving)
                  <span class="text-muted" title="{{ __('Cannot move under a descendant') }}">
                    {{ $row->title ?? '(untitled)' }}
                    <i class="fas fa-ban small ms-1"></i>
                  </span>
                @else
                  <a href="{{ route('informationobject.move', ['slug' => $io->slug, 'parent' => $row->slug]) }}">
                    {{ $row->title ?? '(untitled)' }}
                  </a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="text-muted mb-3">
      @if(!empty($query))
        {{ __('No matches for ":q".', ['q' => $query]) }}
      @else
        {{ __('No descriptions under this parent.') }}
      @endif
    </p>
  @endif

  {{-- Move-here / Cancel form. Posts the BROWSED parent's slug; moveStore
       resolves to id, validates not-self / not-descendant, and applies the
       MPTT subtree shift. --}}
  <form method="POST" action="{{ route('informationobject.move.store', $io->slug) }}">
    @csrf
    <input type="hidden" name="parent" value="{{ $browsedParent->slug ?? '' }}">

    <ul class="actions mb-3 nav gap-2">
      <li>
        @php
          $cancelRoute = 'informationobject.show';
          if (($io->level_of_description_id ?? null) && \Illuminate\Support\Facades\Schema::hasTable('level_of_description_sector')) {
              $sector = \Illuminate\Support\Facades\DB::table('level_of_description_sector')
                  ->where('term_id', $io->level_of_description_id)
                  ->whereNotIn('sector', ['archive'])
                  ->orderBy('display_order')
                  ->value('sector');
              $sectorRoutes = ['library' => 'library.show', 'museum' => 'museum.show', 'gallery' => 'gallery.show', 'dam' => 'dam.show'];
              if ($sector && isset($sectorRoutes[$sector]) && \Illuminate\Support\Facades\Route::has($sectorRoutes[$sector])) {
                  $cancelRoute = $sectorRoutes[$sector];
              }
          }
        @endphp
        <a href="{{ route($cancelRoute, $io->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a>
      </li>
      <li>
        <button type="submit" class="btn atom-btn-outline-success"
                @if(empty($browsedParent->slug)) disabled @endif>
          {{ __('Move here') }}
        </button>
      </li>
    </ul>
  </form>

@endsection
