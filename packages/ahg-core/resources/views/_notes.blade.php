<h3 class="fs-6 mb-2">
  {{ $tableName }}
</h3>

<div class="table-responsive mb-2">
  <table class="table table-bordered mb-0 multi-row">
    <thead class="table-light">
      <tr>
	@if($hiddenType ?? false)
          <th id="{{ $arrayName }}-content-head" class="w-100">
            {{ __('Content') }}
          </th>
	@else
          <th id="{{ $arrayName }}-content-head" class="w-70">
            {{ __('Content') }}
          </th>
          <th id="{{ $arrayName }}-type-head" class="w-30">
            {{ __('Type') }}
          </th>
        @endif
        <th>
          <span class="visually-hidden">{{ __('Delete') }}</span>
        </th>
      </tr>
    </thead>
    <tbody>
      @php $i = 0; @endphp
      @foreach($notes ?? [] as $item)
        <tr class="related_obj_{{ $item->id ?? '' }}">
          <td>
            <input
              type="hidden"
              name="{{ $arrayName }}[{{ $i }}][id]"
              value="{{ $item->id ?? '' }}">
            @if($hiddenType ?? false)
              <input
                type="hidden"
                name="{{ $arrayName }}[{{ $i }}][type]"
                value="{{ $hiddenTypeId ?? '' }}">
            @endif
            <textarea
              class="form-control"
              name="{{ $arrayName }}[{{ $i }}][content]"
              aria-labelledby="{{ $arrayName }}-content-head"
              aria-describedby="{{ $arrayName }}-table-help"
              rows="2">{{ $item->content ?? '' }}</textarea>
          </td>
          @if(!($hiddenType ?? false))
            <td>
              <select
                class="form-select"
                name="{{ $arrayName }}[{{ $i }}][type]"
                aria-labelledby="{{ $arrayName }}-type-head"
                aria-describedby="{{ $arrayName }}-table-help">
                @foreach($noteTypes ?? [] as $typeId => $typeName)
                  <option value="{{ $typeId }}"{{ ($item->typeId ?? $item->type_id ?? '') == $typeId ? ' selected' : '' }}>{{ $typeName }}</option>
                @endforeach
              </select>
            </td>
          @endif
          <td>
            <button type="button" class="multi-row-delete btn atom-btn-white">
              <i class="fas fa-times" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Delete row') }}</span>
            </button>
          </td>
        </tr>
        @php $i++; @endphp
      @endforeach

      {{-- Empty row for adding new --}}
      <tr>
        <td>
          @if($hiddenType ?? false)
            <input
              type="hidden"
              name="{{ $arrayName }}[{{ $i }}][type]"
              value="{{ $hiddenTypeId ?? '' }}">
          @endif
          <textarea
            class="form-control"
            name="{{ $arrayName }}[{{ $i }}][content]"
            aria-labelledby="{{ $arrayName }}-content-head"
            aria-describedby="{{ $arrayName }}-table-help"
            rows="2"></textarea>
        </td>
        @if(!($hiddenType ?? false))
          <td>
            <select
              class="form-select"
              name="{{ $arrayName }}[{{ $i }}][type]"
              aria-labelledby="{{ $arrayName }}-type-head"
              aria-describedby="{{ $arrayName }}-table-help">
              @foreach($noteTypes ?? [] as $typeId => $typeName)
                <option value="{{ $typeId }}">{{ $typeName }}</option>
              @endforeach
            </select>
          </td>
        @endif
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
        <td colspan="{{ ($hiddenType ?? false) ? '2' : '3' }}">
          <button type="button" class="multi-row-add btn atom-btn-white">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __('Add new') }}
          </button>
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="form-text mb-3" id="{{ $arrayName }}-table-help">
  {{ $help ?? '' }}
</div>
