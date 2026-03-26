@php $sf_response->addJavaScript('multiDelete', 'last'); @endphp

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
    @foreach($resource->eventsRelatedByobjectId as $item)
      <tr class="@php echo 0 == @++$row % 2 ? 'even' : 'odd'; @endphp related_obj_@php echo $item->id; @endphp" id="@php echo url_for([$item, 'module' => 'event']); @endphp">
        <td>
          <div>
            @if(isset($item->actor))
              @php echo render_title($item->actor); @endphp
            @endif
          </div>
        </td><td>
          <div>
            @php echo render_value_inline($item->type); @endphp
          </div>
        </td><td>
          <div>
            @if(null !== $relation = QubitObjectTermRelation::getOneByObjectId($item->id))
              @php echo render_value_inline($relation->term); @endphp
            @endif
          </div>
        </td><td>
          <div>
            @php echo render_value_inline(Qubit::renderDateStartEnd($item->getDate(['cultureFallback' => true]), $item->startDate, $item->endDate)); @endphp
          </div>
        </td><td style="text-align: right">
          <input class="multiDelete" name="deleteEvents[]" type="checkbox" value="@php echo url_for([$item, 'module' => 'event']); @endphp"/>
        </td>
      </tr>
    @endforeach
  </tbody>
</table>
