@extends('theme::layouts.1col')

@section('title', 'Gallery artists')
@section('body-class', 'browse gallery-artists')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-users me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">{{ __('Gallery artists') }}</span>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search artists',
        'landmarkLabel' => 'Gallery artist',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])

      @auth
        <a href="{{ route('gallery.artists.create') }}" class="btn btn-sm atom-btn-outline-success">
          <i class="fas fa-plus me-1"></i> {{ __('Add new') }}
        </a>
      @endauth
    </div>
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Nationality') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Medium / Specialty') }}</th>
            <th>{{ __('Movement / Style') }}</th>
            <th>{{ __('Active period') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $artist)
            <tr>
              <td>
                <a href="{{ route('gallery.artists.show', $artist['id']) }}">
                  {{ $artist['display_name'] ?: '[Unknown]' }}
                </a>
              </td>
              <td>{{ $artist['nationality'] ?? '' }}</td>
              <td>{{ $artist['artist_type'] ?? '' }}</td>
              <td>{{ $artist['medium_specialty'] ?? '' }}</td>
              <td>{{ $artist['movement_style'] ?? '' }}</td>
              <td>{{ $artist['active_period'] ?? '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
