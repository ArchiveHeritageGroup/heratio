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
  <h1 class="do-print">@php echo $this->i18n->__('Box labels'); @endphp</h1>

  <h1 class="label">
    @php echo $resource->getTitle(['cultureFallback' => true]); @endphp
  </h1>

  <table class="sticky-enabled">
    <thead>
      <tr>
        <th>
          @php echo $this->i18n->__('#'); @endphp
        </th><th>
          @php echo $this->i18n->__('Reference code'); @endphp
        </th><th>
          @php echo $this->i18n->__('Physical object name'); @endphp
        </th><th>
          @php echo $this->i18n->__('Title'); @endphp
        </th><th>
          @php echo $this->i18n->__('Creation date(s)'); @endphp
        </th>
      </tr>
    </thead><tbody>
      @php $row = 1;
      foreach ($results as $item) { @endphp
        <tr>
          <td>
            @php echo $row++; @endphp
          </td><td>
            @php echo render_value_inline($item['referenceCode']); @endphp
          </td><td>
            @php echo render_value_inline($item['physicalObjectName']); @endphp
          </td><td>
            @php echo render_value_inline($item['title']); @endphp
          </td><td>
            @if($item['creationDates'])
              <ul>
                @foreach(explode('|', $item['creationDates']) as $creationDate)
                  <li>@php echo render_value_inline($creationDate); @endphp</li>
                @endforeach
              </ul>
            @endforeach
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div id="result-count">
    @php echo $this->i18n->__('Showing %1% results', ['%1%' => count($results)]); @endphp
  </div>
</body>
</html>
