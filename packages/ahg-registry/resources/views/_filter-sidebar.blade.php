{{--
  Filter sidebar for registry browse pages.
  Vars:
    $action (string)         — route name fragment, e.g. 'institutionBrowse'
    $filters (array)         — [['label'=>..., 'param'=>..., 'options'=>[...], 'current'=>...], ...]
                                options can be assoc (select) or list-of-arrays with 'value'/'label'/'count' (checkboxes)
    $country (bool optional) — show free-text country box
    $country_current (string optional)

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $routeName = 'registry.' . ($action ?? 'index');
    $formAction = \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : url('/registry');
    $q = request('q', '');
@endphp
<form method="get" action="{{ $formAction }}">
    @if ($q !== '')
        <input type="hidden" name="q" value="{{ $q }}">
    @endif

    @foreach ($filters ?? [] as $filter)
        @php
            $param = $filter['param'] ?? ($filter['name'] ?? '');
            $current = $filter['current'] ?? request($param, '');
            $options = $filter['options'] ?? [];
            $isCheckbox = is_array($options) && isset($options[0]) && is_array($options[0]);
        @endphp
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold small">{{ $filter['label'] ?? '' }}</div>
            <div class="card-body py-2">
                @if ($isCheckbox)
                    @foreach ($options as $opt)
                        @php
                            $val = $opt['value'] ?? '';
                            $id = 'filter_' . $param . '_' . $val;
                            $cur = is_array($current) ? $current : [$current];
                            $checked = in_array($val, $cur, false);
                        @endphp
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="{{ $param }}[]" value="{{ $val }}" id="{{ $id }}"
                                   @checked($checked)>
                            <label class="form-check-label small" for="{{ $id }}">
                                {{ $opt['label'] ?? '' }}
                                @isset($opt['count'])<span class="text-muted">({{ (int) $opt['count'] }})</span>@endisset
                            </label>
                        </div>
                    @endforeach
                @else
                    <select name="{{ $param }}" class="form-select form-select-sm">
                        @foreach ($options as $val => $label)
                            <option value="{{ $val }}" @selected((string) $current === (string) $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>
    @endforeach

    @if (! empty($country))
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold small">{{ __('Country') }}</div>
            <div class="card-body py-2">
                <input type="text" class="form-control form-control-sm" name="country"
                       value="{{ $country_current ?? '' }}"
                       placeholder="{{ __('e.g. South Africa') }}">
            </div>
        </div>
    @endif

    <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">{{ __('Apply Filters') }}</button>
    <a href="{{ $formAction }}" class="btn btn-outline-secondary btn-sm w-100">{{ __('Clear Filters') }}</a>
</form>
