{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_filterSidebar.php --}}
{{--
  $filters: array of ['label' => 'Type', 'param' => 'type' OR 'name' => 'type', 'options' => [...], 'current' => '']
  $action:  the browse action name (registry.<action>)
  $country (optional): show country text field
  $country_current (optional): current country value
--}}
@php
    $routeName = 'registry.' . ($action ?? 'index');
    $formAction = \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : url('/registry');
@endphp
<form method="get" action="{{ $formAction }}">

  @if (request('q', ''))
    <input type="hidden" name="q" value="{{ request('q', '') }}">
  @endif

  @if (!empty($filters))
    @foreach ($filters as $filter)
      @php
        $paramName = $filter['param'] ?? ($filter['name'] ?? '');
        $currentVal = $filter['current'] ?? request($paramName, '');
        $options = $filter['options'] ?? [];
      @endphp
      <div class="card mb-3">
        <div class="card-header py-2 fw-semibold small">{!! $filter['label'] ?? '' !!}</div>
        <div class="card-body py-2">
          @if (is_array($options) && isset($options[0]) && is_array($options[0]))
            {{-- Checkbox/radio style options: [['value' => ..., 'label' => ..., 'count' => ...], ...] --}}
            @foreach ($options as $opt)
              @php
                $curArr = is_array($currentVal) ? $currentVal : [$currentVal];
                $isChecked = in_array($opt['value'] ?? '', $curArr);
              @endphp
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="{{ $paramName }}[]" value="{{ $opt['value'] ?? '' }}" id="filter_{{ $paramName . '_' . ($opt['value'] ?? '') }}"@if ($isChecked) checked @endif>
                <label class="form-check-label small" for="filter_{{ $paramName . '_' . ($opt['value'] ?? '') }}">
                  {{ $opt['label'] ?? '' }}
                  @if (isset($opt['count']))
                    <span class="text-muted">({{ (int) $opt['count'] }})</span>
                  @endif
                </label>
              </div>
            @endforeach
          @else
            {{-- Select style options: ['value' => 'Label', ...] --}}
            <select name="{{ $paramName }}" class="form-select form-select-sm">
              @foreach ($options as $val => $label)
                <option value="{{ $val }}"@if ((string) $currentVal === (string) $val) selected @endif>{!! $label !!}</option>
              @endforeach
            </select>
          @endif
        </div>
      </div>
    @endforeach
  @endif

  @if (!empty($country))
  <div class="card mb-3">
    <div class="card-header py-2 fw-semibold small">{{ __('Country') }}</div>
    <div class="card-body py-2">
      <input type="text" class="form-control form-control-sm" name="country" value="{{ $country_current ?? '' }}" placeholder="{{ __('e.g. South Africa') }}">
    </div>
  </div>
  @endif

  <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">{{ __('Apply Filters') }}</button>
  <a href="{{ $formAction }}" class="btn btn-outline-secondary btn-sm w-100">{{ __('Clear Filters') }}</a>
</form>
