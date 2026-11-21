{{-- Faces with their record and similarity; $confirmFor: person id to confirm/reject against, or null. --}}
<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr><th>{{ __('Face') }}</th><th>{{ __('Record') }}</th><th>{{ __('Time') }}</th><th>{{ __('Similarity (approx.)') }}</th><th></th></tr></thead>
  <tbody>
  @foreach($hits as $h)
    <tr>
      <td><img src="{{ route('faces.face.image', $h->id) }}" alt="{{ __('Face :id', ['id' => $h->id]) }}" width="64" height="64" class="rounded border" style="object-fit:cover" loading="lazy"></td>
      <td>@if($h->slug)<a href="{{ url('/'.$h->slug) }}">{{ $h->title ?: $h->slug }}</a>@else#{{ $h->object_id }}@endif
        @if(! empty($h->person_name))<div class="small text-muted">{{ __('Named as :name', ['name' => $h->person_name]) }}</div>@endif</td>
      <td>{{ $h->frame_time_seconds !== null ? gmdate('H:i:s', (int) $h->frame_time_seconds) : '' }}</td>
      <td>{{ number_format($h->similarity, 2) }}</td>
      <td class="text-nowrap">
        @if($confirmFor)
          <form method="POST" action="{{ route('faces.face.confirm', $h->id) }}" class="d-inline">@csrf
            <input type="hidden" name="person_id" value="{{ $confirmFor }}"><input type="hidden" name="similarity" value="{{ $h->similarity }}">
            <button class="btn btn-sm btn-success">{{ __('Confirm') }}</button></form>
          <form method="POST" action="{{ route('faces.face.reject', $h->id) }}" class="d-inline">@csrf
            <input type="hidden" name="person_id" value="{{ $confirmFor }}">
            <button class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button></form>
        @else
          <a class="btn btn-sm btn-outline-primary" href="{{ route('faces.object', $h->do_id) }}">{{ __('Open') }}</a>
        @endif
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
