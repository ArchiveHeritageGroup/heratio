@extends('ahg-theme-b5::layout')

@section('title', 'API — Search Information Objects')

@section('content')
<div class="container-fluid mt-3">
  <h1><i class="fas fa-search"></i> API — Search Information Objects</h1>

  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-8">
          <input type="text" name="q" class="form-control" value="{{ e($query ?? '') }}" placeholder="Search by title, identifier, or scope..." autofocus>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
        </div>
      </form>
    </div>
  </div>

  @if(!empty($query))
  <p class="text-muted">{{ $total }} result(s) for "{{ e($query) }}"</p>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead><tr><th>ID</th><th>Identifier</th><th>Title</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse($results as $row)
          <tr>
            <td>{{ $row->id }}</td>
            <td><code>{{ e($row->identifier ?? '') }}</code></td>
            <td>
              @if($row->slug)
                <a href="/{{ $row->slug }}">{{ e($row->title ?? 'Untitled') }}</a>
              @else
                {{ e($row->title ?? 'Untitled') }}
              @endif
            </td>
            <td>
              @if($row->slug)
                <a href="/{{ $row->slug }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                <a href="/{{ $row->slug }}/edit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="4" class="text-muted">No results found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($totalPages > 1)
  <nav class="mt-3">
    <ul class="pagination">
      @if($page > 1)
        <li class="page-item"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'page' => $page - 1]) }}">Prev</a></li>
      @endif
      @for($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++)
        <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'page' => $i]) }}">{{ $i }}</a></li>
      @endfor
      @if($page < $totalPages)
        <li class="page-item"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'page' => $page + 1]) }}">Next</a></li>
      @endif
    </ul>
  </nav>
  @endif
  @endif
</div>
@endsection
