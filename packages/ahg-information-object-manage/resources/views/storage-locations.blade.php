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
  <h1 class="do-print">@php echo $this->i18n->__('Physical storage locations'); @endphp</h1>

  <h1 class="label">
    @php echo render_title($resource); @endphp
  </h1>

  <table class="sticky-enabled">
    <thead>
      <tr>
        <th>
          @php echo $this->i18n->__('#'); @endphp
        </th><th>
          @php echo $this->i18n->__('Name'); @endphp
        </th><th>
          @php echo $this->i18n->__('Location'); @endphp
        </th><th>
          @php echo $this->i18n->__('Type'); @endphp
        </th>
      </tr>
    </thead><tbody>
      @php $row = 1;
      foreach ($results as $item) { @endphp
        <tr>
          <td>
            @php echo $row++; @endphp
          </td><td>
            @php echo link_to(render_title($item->getName(['cultureFallback' => true])), sfConfig::get('app_siteBaseUrl').'/'.$item->slug); @endphp
          </td><td>
            @php echo render_value_inline($item->getLocation(['cultureFallback' => true])); @endphp
          </td><td>
            @php echo render_value_inline($item->getType(['cultureFallback' => true])); @endphp
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
