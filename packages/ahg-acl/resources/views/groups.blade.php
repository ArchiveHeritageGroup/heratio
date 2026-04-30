@extends('theme::layouts.1col')

@section('title', 'List groups')
@section('body-class', 'browse aclGroup')

@section('title-block')
  <h1>{{ __('List groups') }}</h1>
@endsection

@section('content')
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>{{ __('Group') }}</th>
          <th>{{ __('Members') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($groups as $group)
          <tr>
            <td>
              <a href="{{ route('acl.edit-group', ['id' => $group->id]) }}">
                {{ $group->name ?? 'Unnamed' }}
              </a>
            </td>
            <td>{{ $group->member_count }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="2" class="text-muted">No groups found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection

@section('after-content')
  <section class="actions mb-3">
    <a class="btn atom-btn-outline-light" href="{{ route('acl.groups') }}">Add new</a>
  </section>
@endsection
