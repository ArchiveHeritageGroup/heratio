{{-- Deleted digital objects (heratio#1734). --}}
@extends('theme::layouts.1col')
@section('title', __('Deleted files'))

@section('content')
<h1 class="h3"><i class="fas fa-trash-can-arrow-up me-2"></i>{{ __('Deleted files') }}</h1>
@if($enabled)
  <p class="text-muted">{{ __('Deleted digital objects are kept here for :days days and can be restored. After that the purge removes their files.', ['days' => $days]) }}</p>
@else
  <div class="alert alert-warning">{{ __('The trash is off (trash retention is 0 days): deleted files are removed at once.') }}</div>
@endif

<ul class="nav nav-tabs mb-3">
  @foreach(['trashed' => __('In the trash'), 'restored' => __('Restored'), 'purged' => __('Purged')] as $k => $label)
    <li class="nav-item"><a class="nav-link {{ $status === $k ? 'active' : '' }}" href="{{ route('admin.trash', ['status' => $k]) }}">{{ $label }}</a></li>
  @endforeach
</ul>

@if(count($items) === 0)
  <p class="text-muted">{{ __('Nothing here.') }}</p>
@else
  <div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Record') }}</th><th>{{ __('Deleted') }}</th><th>{{ __('By') }}</th><th>{{ $status === 'trashed' ? __('Purged after') : __('Done') }}</th><th></th></tr></thead>
    <tbody>
      @foreach($items as $t)
        <tr>
          <td>{{ $t->name }}</td>
          <td>@if($t->slug)<a href="{{ url('/'.$t->slug) }}">{{ $t->title ?: $t->slug }}</a>@else{{ $t->information_object_id ? '#'.$t->information_object_id : '' }}@endif</td>
          <td class="text-nowrap">{{ $t->deleted_at }}</td>
          <td>{{ $t->deleted_by_name ?? '' }}</td>
          <td class="text-nowrap">{{ $status === 'trashed' ? $t->purge_after : ($t->restored_at ?? $t->purged_at) }}</td>
          <td class="text-nowrap">
            @if($status === 'trashed')
              <form method="post" action="{{ route('admin.trash.restore', $t->id) }}" class="d-inline">@csrf<button class="btn btn-sm atom-btn-white"><i class="fas fa-rotate-left me-1"></i>{{ __('Restore') }}</button></form>
              <form method="post" action="{{ route('admin.trash.purge', $t->id) }}" class="d-inline" onsubmit="return confirm(@json(__('Delete these files for good?')))">@csrf<button class="btn btn-sm atom-btn-outline-danger"><i class="fas fa-xmark me-1"></i>{{ __('Delete now') }}</button></form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  {{ method_exists($items, 'links') ? $items->links() : '' }}
@endif
@endsection
