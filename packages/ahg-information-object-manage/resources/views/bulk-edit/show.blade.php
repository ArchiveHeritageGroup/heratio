@extends('theme::layouts.1col')
@section('title', __('Bulk edit :id', ['id' => $batch->id]))
@section('body-class', 'admin bulk-edit')

@section('content')
@php $nonce = function_exists('csp_nonce') ? csp_nonce() : ''; @endphp
<h1>{{ __('Bulk edit :id', ['id' => $batch->id]) }}</h1>
<p><a href="{{ route('bulk-edit.index') }}">{{ __('Back to bulk edit') }}</a></p>

<dl class="row">
  <dt class="col-sm-3">{{ __('Operation') }}</dt><dd class="col-sm-9">{{ $batch->kind }}</dd>
  <dt class="col-sm-3">{{ __('Status') }}</dt><dd class="col-sm-9"><span id="batchStatus">{{ $batch->status }}</span></dd>
  <dt class="col-sm-3">{{ __('Progress') }}</dt><dd class="col-sm-9"><span id="batchDone">{{ $batch->done }}</span> / <span id="batchTotal">{{ $batch->total }}</span></dd>
  @if($batch->error)<dt class="col-sm-3">{{ __('Error') }}</dt><dd class="col-sm-9 text-danger">{{ $batch->error }}</dd>@endif
  @if($batch->undone_at)<dt class="col-sm-3">{{ __('Undone') }}</dt><dd class="col-sm-9">{{ $batch->undone_at }}</dd>@endif
</dl>

@if($batch->status === 'done')
  <form method="post" action="{{ route('bulk-edit.undo', $batch->id) }}" class="mb-3"
        onsubmit="return confirm(@json(__('Undo this batch? Values edited since the batch are left as they are.')));">
    @csrf
    <button type="submit" class="btn atom-btn-outline-danger">{{ __('Undo this batch') }}</button>
  </form>
@endif

<h2 class="h5">{{ __('Changes') }}</h2>
<div class="table-responsive">
  <table class="table table-sm table-bordered">
    <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Field') }}</th><th scope="col">{{ __('Before') }}</th><th scope="col">{{ __('After') }}</th></tr></thead>
    <tbody>
      @forelse($changes as $c)
        <tr>
          <td>@if($c->slug)<a href="{{ url('/'.$c->slug) }}">{{ $c->slug }}</a>@else #{{ $c->object_id }} @endif</td>
          <td>{{ $c->field }}</td>
          <td class="text-break">{{ \Illuminate\Support\Str::limit((string) $c->before_value, 300) }}</td>
          <td class="text-break">{{ \Illuminate\Support\Str::limit((string) $c->after_value, 300) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted">{{ __('No changes recorded yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if(in_array($batch->status, ['queued', 'running'], true))
<script nonce="{{ $nonce }}">
(function poll() {
  fetch(@json(route('bulk-edit.status', $batch->id)), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (b) {
      document.getElementById('batchStatus').textContent = b.status;
      document.getElementById('batchDone').textContent = b.done;
      document.getElementById('batchTotal').textContent = b.total;
      if (b.status === 'queued' || b.status === 'running') { setTimeout(poll, 2000); } else { window.location.reload(); }
    })
    .catch(function () { setTimeout(poll, 5000); });
})();
</script>
@endif
@endsection
