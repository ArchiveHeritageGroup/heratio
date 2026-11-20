{{-- Master files checked out for editing (heratio#1757 / #1758). --}}
@extends('theme::layouts.1col')
@section('title', __('Checked-out files'))

@section('content')
<h1 class="h3"><i class="fas fa-file-export me-2"></i>{{ __('Checked-out files') }}</h1>
<p class="text-muted">{{ __('Master files checked out for editing. Nobody else can replace a file while it is checked out. An administrator can check a file in or cancel the check-out from its edit page.') }}</p>

@if(count($items) === 0)
  <p class="text-muted">{{ __('No files are checked out.') }}</p>
@else
  <div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Record') }}</th><th>{{ __('Checked out by') }}</th><th>{{ __('Since') }}</th><th>{{ __('Version') }}</th><th>{{ __('Note') }}</th><th></th></tr></thead>
    <tbody>
      @foreach($items as $c)
        <tr>
          <td>{{ $c->file_name }}</td>
          <td>@if($c->slug)<a href="{{ url('/'.$c->slug) }}">{{ $c->title ?: $c->slug }}</a>@else{{ $c->information_object_id ? '#'.$c->information_object_id : '' }}@endif</td>
          <td>{{ $c->username ?? '#'.$c->user_id }}</td>
          <td class="text-nowrap">{{ $c->checked_out_at }}</td>
          <td>{{ $c->version_at_checkout }}</td>
          <td class="small">{{ $c->note }}</td>
          <td class="text-nowrap">
            <a href="{{ route('io.digitalobject.show', $c->digital_object_id) }}" class="btn btn-sm atom-btn-white">{{ __('Open') }}</a>
            <form method="post" action="{{ route('io.digitalobject.checkout.cancel', $c->digital_object_id) }}" class="d-inline" onsubmit="return confirm(@json(__('Cancel this check-out? The user\'s edits will not be stored.')))">@csrf<input type="hidden" name="return" value="admin"><button class="btn btn-sm atom-btn-outline-danger">{{ __('Cancel check-out') }}</button></form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
  </div>
@endif
@endsection
