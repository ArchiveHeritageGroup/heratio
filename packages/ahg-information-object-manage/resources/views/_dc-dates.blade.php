<h3 class="fs-6 mb-2">
  {{ __('Date(s)') }}
</h3>

<div class="table-responsive mb-2">
  <table class="table table-bordered mb-0 multi-row">
    <thead class="table-light">
      <tr>
        <th id="dc-dates-date-head" class="w-40">
          {{ __('Date') }}
        </th>
        <th id="dc-dates-start-head" class="w-30">
          {{ __('Start') }}
        </th>
        <th id="dc-dates-end-head" class="w-30">
          {{ __('End') }}
        </th>
        <th>
          <span class="visually-hidden">{{ __('Delete') }}</span>
        </th>
      </tr>
    </thead>
    <tbody>
      @php $i = 0; @endphp
      @foreach($resource->getDates() as $item)
        <tr class="date related_obj_{{ $item->id }}">
          <td>
            <input
              type="hidden"
              name="editDates[{{ $i }}][id]"
              value="{{ $item->id }}">
            <input type="text" class="form-control" name="editDates[{{ $i }}][date]"
              value="{{ $item->date ?? '' }}"
              aria-labelledby="dc-dates-date-head"
              aria-describedby="dc-dates-table-help">
          </td>
          <td>
            <input type="text" class="form-control" name="editDates[{{ $i }}][startDate]"
              value="{{ $item->startDate ?? '' }}"
              aria-labelledby="dc-dates-start-head"
              aria-describedby="dc-dates-table-help">
          </td>
          <td>
            <input type="text" class="form-control" name="editDates[{{ $i }}][endDate]"
              value="{{ $item->endDate ?? '' }}"
              aria-labelledby="dc-dates-end-head"
              aria-describedby="dc-dates-table-help">
          </td>
          <td>
            <button type="button" class="multi-row-delete btn atom-btn-white">
              <i class="fas fa-times" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Delete row') }}</span>
            </button>
          </td>
        </tr>
        @php ++$i; @endphp
      @endforeach

      <tr class="date">
        <td>
          <input type="text" class="form-control" name="editDates[{{ $i }}][date]"
            value=""
            aria-labelledby="dc-dates-date-head"
            aria-describedby="dc-dates-table-help">
        </td>
        <td>
          <input type="text" class="form-control" name="editDates[{{ $i }}][startDate]"
            value=""
            aria-labelledby="dc-dates-start-head"
            aria-describedby="dc-dates-table-help">
        </td>
        <td>
          <input type="text" class="form-control" name="editDates[{{ $i }}][endDate]"
            value=""
            aria-labelledby="dc-dates-end-head"
            aria-describedby="dc-dates-table-help">
        </td>
        <td>
          <button type="button" class="multi-row-delete btn atom-btn-white">
            <i class="fas fa-times" aria-hidden="true"></i>
            <span class="visually-hidden">{{ __('Delete row') }}</span>
          </button>
        </td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4">
          <button type="button" class="multi-row-add btn atom-btn-white">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __('Add new') }}
          </button>
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="form-text mb-3" id="dc-dates-table-help">
  {{ __(
      'Identify and record the date(s) of the unit of description. Identify the type of date given. Record as a single date or a range of dates as appropriate. The Date display field can be used to enter free-text date information, including typographical marks to express approximation, uncertainty, or qualification. Use the start and end fields to make the dates searchable. Do not use any qualifiers or typographical symbols to express uncertainty. Acceptable date formats: YYYYMMDD, YYYY-MM-DD, YYYY-MM, YYYY.'
  ) }}
</div>
