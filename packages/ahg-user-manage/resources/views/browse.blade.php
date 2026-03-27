@extends('theme::layouts.1col')

@section('title', 'Users')
@section('body-class', 'browse user')

@section('content')
  <h1>List users</h1>

  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search users',
        'landmarkLabel' => 'User',
    ])
  </div>

  <nav>
    <ul class="nav nav-pills mb-3 d-flex gap-2">
      <li class="nav-item">
        <a class="btn atom-btn-white active-primary text-wrap {{ request('filter', 'onlyActive') !== 'onlyInactive' ? 'active' : '' }}" href="?filter=onlyActive" {{ request('filter', 'onlyActive') !== 'onlyInactive' ? 'aria-current=page' : '' }}>Show active only</a>
      </li>
      <li class="nav-item">
        <a class="btn atom-btn-white active-primary text-wrap {{ request('filter') === 'onlyInactive' ? 'active' : '' }}" href="?filter=onlyInactive" {{ request('filter') === 'onlyInactive' ? 'aria-current=page' : '' }}>Show inactive only</a>
      </li>
    </ul>
  </nav>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>User name</th>
            <th>Email</th>
            <th>User groups</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td>
                <a href="{{ route('user.show', $doc['slug']) }}">
                  {{ $doc['username'] ?? $doc['name'] ?? '[Untitled]' }}
                </a>
                @if(!($doc['active'] ?? true))
                  (inactive)
                @endif
                @if(isset($currentUserId) && $doc['id'] == $currentUserId)
                  (you)
                @endif
              </td>
              <td>{{ $doc['email'] ?? '' }}</td>
              <td>
                @if(!empty($doc['groups']))
                  <ul>
                    @foreach(explode(', ', $doc['groups']) as $group)
                      <li>{{ $group }}</li>
                    @endforeach
                  </ul>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  <section class="actions mb-3">
    <a class="btn atom-btn-outline-light" href="{{ route('user.add') }}">Add new</a>
  </section>
@endsection
