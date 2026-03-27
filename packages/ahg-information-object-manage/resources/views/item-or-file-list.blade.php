<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <style>
    table, thead {
      border-collapse: collapse;
      border: 1px solid black;
    }

    th, td {
      border: 1px solid #ddd;
      padding: 2px;
    }
  </style>
</head>

<body>
  <h1 class="label">{{ $reportTypeLabel . __(' report') }}</h1><hr>

  @php $row = 1; @endphp
  @foreach($results as $parent => $items)
    <h2 class="element-invisible">{{ __('%1% hierarchy', ['%1%' => config('atom.ui_label_informationobject', 'Archival description')]) }}</h2>
    <div class="resource-hierarchy">
      <ul>
      @foreach($items[0]['resource']->getAncestors()->orderBy('lft') as $ancestor)
        @if(QubitInformationObject::ROOT_ID != intval($ancestor->id))
        <li>{{ QubitInformationObject::getStandardsBasedInstance($ancestor)->__toString() }}</li>
        @endif
      @endforeach
      </ul>
    </div>

    <table>
      <thead>
        <tr>
          <th>{{ __('#') }}</th>
          @if($includeThumbnails)
            <th>{{ __('Thumbnail') }}</th>
          @endif
          <th>{{ __('Reference code') }}</th>
          <th>{{ __('Title') }}</th>
          <th>{{ __('Dates') }}</th>
          <th>{{ __('Access restrictions') }}</th>
          @if(0 == config('atom.generate_reports_as_pub_user', true))
            <th>{{ __('Retrieval information') }}</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach($items as $item)
          <tr>
            <td class="row-number">{{ $row++ }}</td>
            @if($includeThumbnails)
              <td>
                @if((null !== $do = $item['resource']->getDigitalObject()) && (null != $do->thumbnail))
                  <img src="{{ config('app.url') . $do->thumbnail->getFullPath() }}" alt="">
                @else
                  {{ __('N/A') }}
                @endif
              </td>
            @endif
            <td>{{ render_value_inline($item['referenceCode']) }}</td>
            <td>{{ render_value_inline($item['title']) }}</td>
            <td>{{ render_value_inline($item['dates']) }}</td>
            <td>{{ isset($item['accessConditions']) ? $item['accessConditions'] : __('None') }}</td>
            @if(0 == config('atom.generate_reports_as_pub_user', true))
              <td>{{ render_value_inline($item['locations']) }}</td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
</body>
</html>
