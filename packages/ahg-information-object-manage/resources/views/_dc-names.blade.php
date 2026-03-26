<h3 class="fs-6 mb-2">
  {{ __('Name(s)') }}
</h3>

<div class="table-responsive mb-2">
  <table class="table table-bordered mb-0 multi-row">
    <thead class="table-light">
      <tr>
        <th id="dc-names-actor-head" class="w-60">
          {{ __('Actor name') }}
        </th>
        <th id="dc-names-type-head" class="w-40">
          {{ __('Type') }}
        </th>
        <th>
          <span class="visually-hidden">{{ __('Delete') }}</span>
        </th>
      </tr>
    </thead>
    <tbody>
      @php $i = 0; @endphp
      @foreach($resource->getActorEvents() as $item)
        @if(isset($item->actor))
          <tr class="related_obj_{{ $item->id }}">
            <td>
              <input
                type="hidden"
                name="editNames[{{ $i }}][id]"
                value="{{ $item->id }}">
              {{ $item->actor->authorized_form_of_name ?? $item->actor->title ?? '' }}
            </td>
            <td>
              {{ $item->type ?? '' }}
            </td>
            <td>
              <button type="button" class="multi-row-delete btn atom-btn-white">
                <i class="fas fa-times" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('Delete row') }}</span>
              </button>
            </td>
          </tr>
          @php ++$i; @endphp
        @endif
      @endforeach

      <tr>
        <td>
          <div>
            <input type="text" class="form-control form-autocomplete" name="editNames[{{ $i }}][actor]"
              value="{{ old("editNames.{$i}.actor") }}"
              aria-labelledby="dc-names-actor-head"
              aria-describedby="dc-names-table-help"
              data-autocomplete-url="{{ route('actor.autocomplete') ?? '' }}">
            <input class="list" type="hidden" value="{{ route('actor.autocomplete') ?? '' }}">
          </div>
        </td>
        <td>
          <select class="form-select" name="editNames[{{ $i }}][type]"
            aria-labelledby="dc-names-type-head"
            aria-describedby="dc-names-table-help">
            <option value="">{{ __('- Select type -') }}</option>
            @foreach($nameTypes ?? [] as $id => $name)
              <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
          </select>
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
        <td colspan="3">
          <button type="button" class="multi-row-add btn atom-btn-white">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __('Add new') }}
          </button>
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="form-text mb-3" id="dc-names-table-help">
  {{ __('Identify and record the name(s) and type(s) of the unit of description.') }}
</div>
