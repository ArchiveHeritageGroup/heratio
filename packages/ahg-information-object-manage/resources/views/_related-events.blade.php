<table id="relatedEvents" class="table table-bordered">
  <thead>
    <tr>
      <th style="width: 25%">
        {{ __('Name') }}
      </th><th style="width: 20%">
        {{ __('Role/event') }}
      </th><th style="width: 20%">
        {{ __('Place') }}
      </th><th style="width: 25%">
        {{ __('Date(s)') }}
      </th><th style="width: 10%">
        &nbsp;
      </th>
    </tr>
  </thead><tbody>
    @php $row = 0; @endphp
    @foreach($resource->eventsRelatedByobjectId ?? [] as $item)
      <tr class="{{ 0 == ++$row % 2 ? 'even' : 'odd' }} related_obj_{{ $item->id }}" id="event-{{ $item->id }}">
        <td>
          <div>
            @if(isset($item->actor))
              {{ $item->actor->authorized_form_of_name ?? $item->actor->title ?? '' }}
            @endif
          </div>
        </td><td>
          <div>
            {{ $item->type ?? '' }}
          </div>
        </td><td>
          <div>
            @if(isset($item->place))
              {{ $item->place ?? '' }}
            @endif
          </div>
        </td><td>
          <div>
            {{ $item->date ?? '' }}
            @if(!empty($item->startDate) || !empty($item->endDate))
              ({{ $item->startDate ?? '' }} - {{ $item->endDate ?? '' }})
            @endif
          </div>
        </td><td style="text-align: right">
          <input class="multiDelete" name="deleteEvents[]" type="checkbox" value="event-{{ $item->id }}"/>
        </td>
      </tr>
    @endforeach
  </tbody>
</table>
