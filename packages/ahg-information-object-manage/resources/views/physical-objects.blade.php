<h1>{{ __('Physical storage') }}</h1>

<h1 class="label">{{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}</h1>

<table class="sticky-enabled">
  <thead>
    <tr>
      <th>
        {{ __('Name') }}
      </th><th>
        {{ __('Location') }}
      </th><th>
        {{ __('Type') }}
      </th>
    </tr>
  </thead><tbody>
    @php $row = 0; @endphp
    @foreach($physicalObjects as $item)
      <tr class="{{ 0 == ++$row % 2 ? 'even' : 'odd' }}">
        <td>
          <a href="{{ route('physicalobject.show', $item->slug ?? $item->id) }}">{{ $item->authorized_form_of_name ?? $item->title ?? $item->name ?? '' }}</a>
        </td><td>
          {{ $item->location ?? '' }}
        </td><td>
          {{ $item->type ?? '' }}
        </td>
      </tr>
    @endforeach
  </tbody>
</table>

@if(isset($pager))
  @include('ahg-core::partials._pager', ['pager' => $pager])
@endif
