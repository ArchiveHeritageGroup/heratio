@extends('theme::layouts.1col')

@section('title', 'Users')
@section('body-class', 'browse user')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-users me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">List users</h1>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search users',
        'landmarkLabel' => 'User',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      <a href="{{ route('user.create') }}" class="btn btn-sm atom-btn-outline-light">
        Add new
      </a>

      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])
    </div>
  </div>

  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ request('status', 'active') === 'active' ? 'active' : '' }}" href="?status=active">Show active only</a></li>
    <li class="nav-item"><a class="nav-link {{ request('status') === 'inactive' ? 'active' : '' }}" href="?status=inactive">Show inactive only</a></li>
    <li class="nav-item"><a class="nav-link {{ request('status') === 'all' ? 'active' : '' }}" href="?status=all">Show all</a></li>
  </ul>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>User name</th>
            <th>Email</th>
            <th>User groups</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td>
                <a href="{{ route('user.show', $doc['slug']) }}">
                  {{ $doc['username'] ?? $doc['name'] ?? '[Untitled]' }}
                </a>
                @if(isset($currentUserId) && $doc['id'] == $currentUserId)
                  <span class="badge bg-info ms-1">(you)</span>
                @endif
              </td>
              <td>{{ $doc['email'] ?? '' }}</td>
              <td>{{ $doc['groups'] ?? '' }}</td>
              <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('F j, Y g:i A') : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  <section class="actions mb-3">
    <a class="btn atom-btn-outline-light" href="{{ route('user.create') }}">Add new</a>
  </section>

@push('css')
<style>
.table thead th {
  background-color: var(--ahg-primary, #005837);
  color: var(--ahg-card-header-text, #fff);
  border-color: var(--ahg-primary, #005837);
}
</style>
@endpush
@endsection
