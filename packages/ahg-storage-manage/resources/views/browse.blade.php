@extends('theme::layouts.1col')

@section('title', config('app.ui_label_physicalobject', 'Physical storage'))
@section('body-class', 'browse physicalobject')

@section('content')
  <h1>Browse {{ config('app.ui_label_physicalobject', 'Physical storage') }}</h1>

  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search ' . mb_strtolower(config('app.ui_label_physicalobject', 'Physical storage')),
        'landmarkLabel' => config('app.ui_label_physicalobject', 'Physical storage'),
    ])
  </div>

  @if($pager->getNbResults())
    @php
      $currentSort = request('sort', 'alphabetic');
      $baseParams = request()->except(['sort', 'sortDir', 'page']);

      // Toggle sort direction helper
      function storageSortUrl($field, $currentSort, $baseParams) {
          $fieldMap = ['name' => 'alphabetic', 'location' => 'location'];
          $sortKey = $fieldMap[$field] ?? $field;
          $isActive = false;

          // Check current sort matches this field
          $activeField = match($currentSort) {
              'alphabetic', 'nameUp' => 'name',
              'nameDown' => 'name',
              'location', 'locationUp' => 'location',
              'locationDown' => 'location',
              default => '',
          };
          $isActive = ($activeField === $field);

          // Toggle direction
          $currentDir = str_contains($currentSort, 'Down') ? 'desc' : 'asc';
          $newDir = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';

          $params = array_merge($baseParams, ['sort' => $sortKey, 'sortDir' => $newDir]);
          return url('/physicalobject/browse') . '?' . http_build_query($params);
      }
    @endphp
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th class="sortable">
              <a title="{{ __('Sort') }}" class="sortable" href="{{ storageSortUrl('name', $currentSort, $baseParams) }}">{{ __('Name') }}</a>
            </th>
            <th class="sortable">
              <a title="{{ __('Sort') }}" class="sortable" href="{{ storageSortUrl('location', $currentSort, $baseParams) }}">{{ __('Location') }}</a>
            </th>
            <th>{{ __('Type') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td>
                <a href="{{ route('physicalobject.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              {{-- heratio#1545 - the tree path when the box is placed, else the flat
                   location fields; the old free-text location, if any, as a note. --}}
              <td>
                @if(! empty($doc['tree_path']))
                  @foreach($doc['tree_path'] as $place)
                    @if(! $loop->first) <span class="text-muted">&gt;</span> @endif
                    <a href="{{ route('storagelocation.show', $place['slug']) }}">{{ $place['name'] }}</a>
                  @endforeach
                @elseif(($doc['flat_path'] ?? '') !== '')
                  {{ $doc['flat_path'] }}
                @endif
                @if(trim((string) ($doc['location'] ?? '')) !== '')
                  <div class="small text-muted">{{ __('Note: :note', ['note' => $doc['location']]) }}</div>
                @endif
              </td>
              <td>{{ $typeNames[$doc['type_id']] ?? '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3">
      <a class="btn atom-btn-outline-light" href="{{ route('physicalobject.create') }}" title="{{ __('Add new') }}">{{ __('Add new') }}</a>
      <a class="btn atom-btn-outline-light" href="{{ url('/physicalobject/holdingsReportExport') }}" title="{{ __('Export storage report') }}">{{ __('Export storage report') }}</a>
      <a class="btn atom-btn-outline-light" href="{{ route('storagelocation.browse') }}" title="{{ __('Storage locations') }}">{{ __('Storage locations') }}</a>
    </section>
  @endauth

@push('css')
<style>
.table thead th a {
  color: var(--ahg-card-header-text, #fff);
  text-decoration: none;
}
.table thead th a:hover {
  color: var(--ahg-card-header-text, #fff);
  text-decoration: underline;
}
</style>
@endpush
@endsection
