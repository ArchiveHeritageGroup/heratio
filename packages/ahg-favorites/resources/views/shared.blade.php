@extends('theme::layouts.1col')
@section('title', 'Shared Favorites: ' . $folder->name)
@section('body-class', 'favorites shared')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1><i class="fas fa-share-alt me-2"></i>{{ $folder->name }}</h1>
    @if($folder->description)<p class="text-muted">{{ $folder->description }}</p>@endif
  </div>
  <span class="badge bg-primary fs-6">{{ $items->count() }} {{ Str::plural('item', $items->count()) }}</span>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Title</th><th>Reference Code</th><th>Added</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $item)
        <tr>
          <td>{{ $item->archival_description }}</td>
          <td><code>{{ $item->reference_code ?? '' }}</code></td>
          <td>{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') }}</td>
          <td><a href="{{ url('/' . $item->slug) }}" class="btn btn-sm atom-btn-white">View</a></td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted text-center">No items in this folder.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@auth
  <div class="mt-3">
    <p class="text-muted">Want to save these to your own favorites?</p>
    <form method="post" action="{{ route('favorites.import') }}">
      @csrf
      <input type="hidden" name="slugs" value="{{ $items->pluck('slug')->implode("\n") }}">
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-heart me-1"></i>Copy to My Favorites</button>
    </form>
  </div>
@else
  <div class="alert alert-info mt-3"><a href="{{ route('login') }}">Log in</a> to save these to your favorites.</div>
@endauth
@endsection
