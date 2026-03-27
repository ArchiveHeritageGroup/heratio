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
              <a title="Sort" class="sortable" href="{{ storageSortUrl('name', $currentSort, $baseParams) }}">Name</a>
            </th>
            <th class="sortable">
              <a title="Sort" class="sortable" href="{{ storageSortUrl('location', $currentSort, $baseParams) }}">Location</a>
            </th>
            <th>Type</th>
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
              <td>{{ $doc['location'] ?? '' }}</td>
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
      <a class="btn atom-btn-outline-light" href="{{ route('physicalobject.create') }}" title="Add new">Add new</a>
      <a class="btn atom-btn-outline-light" href="{{ url('/physicalobject/holdingsReportExport') }}" title="Export storage report">Export storage report</a>
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
