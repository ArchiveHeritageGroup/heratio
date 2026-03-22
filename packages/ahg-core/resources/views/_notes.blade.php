<h3 class="fs-6 mb-2">
  @php echo $tableName; @endphp
</h3>

<div class="table-responsive mb-2">
  <table class="table table-bordered mb-0 multi-row">
    <thead class="table-light">
      <tr>
	@if($hiddenType)
          <th id="@php echo $arrayName; @endphp-content-head" class="w-100">
            {{ __('Content') }}
          </th>
	@php } else { @endphp
          <th id="@php echo $arrayName; @endphp-content-head" class="w-70">
            {{ __('Content') }}
          </th>
          <th id="@php echo $arrayName; @endphp-type-head" class="w-30">
            {{ __('Type') }}
          </th>
        @endforeach
        <th>
          <span class="visually-hidden">{{ __('Delete') }}</span>
        </th>
      </tr>
    </thead>
    <tbody>
      @php $i = 0;
      foreach ($notes as $item) { @endphp
        @php $form->getWidgetSchema()->setNameFormat($arrayName."[{$i}][%s]");
        ++$i; @endphp

        <tr class="related_obj_@php echo $item->id; @endphp">
          <td>
            <input
              type="hidden"
              name="@php echo $form->getWidgetSchema()->generateName('id'); @endphp"
              value="@php echo $item->id; @endphp">
            @if($hiddenType)
              <input
                type="hidden"
                name="@php echo $form->getWidgetSchema()->generateName('type'); @endphp"
                value="@php echo $hiddenTypeId; @endphp">
            @endforeach
            @php $form->setDefault('content', $item->getContent()); @endphp
            @php echo render_field($form->content, $item, [
                'aria-labelledby' => $arrayName.'-content-head',
                'aria-describedby' => $arrayName.'-table-help',
                'onlyInputs' => true,
            ]); @endphp
          </td>
          @if(!$hiddenType)
            <td>
              @php $form->setDefault('type', $item->typeId); @endphp
              @php echo render_field($form->type, null, [
                  'aria-labelledby' => $arrayName.'-type-head',
                  'aria-describedby' => $arrayName.'-table-help',
                  'onlyInputs' => true,
              ]); @endphp
            </td>
          @endforeach
          <td>
            <button type="button" class="multi-row-delete btn atom-btn-white">
              <i class="fas fa-times" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Delete row') }}</span>
            </button>
          </td>
        </tr>
      @endforeach

      @php $form->getWidgetSchema()->setNameFormat($arrayName."[{$i}][%s]"); @endphp

      <tr>
        <td>
          @if($hiddenType)
            <input
              type="hidden"
              name="@php echo $form->getWidgetSchema()->generateName('type'); @endphp"
              value="@php echo $hiddenTypeId; @endphp">
          @endforeach
          @php $form->setDefault('content', ''); @endphp
          @php echo render_field($form->content, null, [
              'aria-labelledby' => $arrayName.'-content-head',
              'aria-describedby' => $arrayName.'-table-help',
              'onlyInputs' => true,
          ]); @endphp
        </td>
        @if(!$hiddenType)
          <td>
            @php $form->setDefault('type', ''); @endphp
            @php echo render_field($form->type, null, [
                'aria-labelledby' => $arrayName.'-type-head',
                'aria-describedby' => $arrayName.'-table-help',
                'onlyInputs' => true,
            ]); @endphp
          </td>
        @endforeach
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
        <td colspan="@php echo $hiddenType ? '2' : '3'; @endphp">
          <button type="button" class="multi-row-add btn atom-btn-white">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __('Add new') }}
          </button>
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<div class="form-text mb-3" id="@php echo $arrayName; @endphp-table-help">
  @php echo $help; @endphp
</div>
