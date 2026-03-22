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
  <h1 class="label">@php echo $reportTypeLabel.$this->i18n->__(' report'); @endphp</h1><hr>

  @php $row = 1;
  foreach ($results as $parent => $items) { @endphp
    <h2 class="element-invisible">@php echo $this->i18n->__('%1% hierarchy', ['%1%' => sfConfig::get('app_ui_label_informationobject')]); @endphp</h2>
    <div class="resource-hierarchy">
      <ul>
      @foreach($items[0]['resource']->getAncestors()->orderBy('lft') as $ancestor)
        @if(QubitInformationObject::ROOT_ID != intval($ancestor->id))
        <li>@php echo QubitInformationObject::getStandardsBasedInstance($ancestor)->__toString(); @endphp</li>
        @endforeach
      @endforeach
      </ul>
    </div>

    <table>
      <thead>
        <tr>
          <th>@php echo $this->i18n->__('#'); @endphp</th>
          @if($includeThumbnails)
            <th>@php echo $this->i18n->__('Thumbnail'); @endphp</th>
          @endforeach
          <th>@php echo $this->i18n->__('Reference code'); @endphp</th>
          <th>@php echo $this->i18n->__('Title'); @endphp</th>
          <th>@php echo $this->i18n->__('Dates'); @endphp</th>
          <th>@php echo $this->i18n->__('Access restrictions'); @endphp</th>
          @if(0 == sfConfig::get('app_generate_reports_as_pub_user', 1))
            <th>@php echo $this->i18n->__('Retrieval information'); @endphp</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($items as $item)
          <tr>
            <td class="row-number">@php echo $row++; @endphp</td>
            @if($includeThumbnails)
              <td>
                @if((null !== $do = $item['resource']->getDigitalObject()) && (null != $do->thumbnail))
                  @php echo image_tag(sfConfig::get('app_siteBaseUrl').$do->thumbnail->getFullPath()); @endphp
                @php } else { @endphp
                  @php echo $this->i18n->__('N/A'); @endphp
                @endforeach
              </td>
            @endforeach
            <td>@php echo render_value_inline($item['referenceCode']); @endphp</td>
            <td>@php echo render_value_inline($item['title']); @endphp</td>
            <td>@php echo render_value_inline($item['dates']); @endphp</td>
            <td>@php echo isset($item['accessConditions']) ? $item['accessConditions'] : $this->i18n->__('None'); @endphp</td>
            @if(0 == sfConfig::get('app_generate_reports_as_pub_user', 1))
              <td>@php echo render_value_inline($item['locations']); @endphp</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
</body>
</html>
