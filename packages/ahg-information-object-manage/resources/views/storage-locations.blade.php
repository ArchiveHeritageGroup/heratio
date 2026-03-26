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
  <h1 class="do-print">{{ __('Physical storage locations') }}</h1>

  <h1 class="label">
    {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
  </h1>

  <table class="sticky-enabled">
    <thead>
      <tr>
        <th>
          {{ __('#') }}
        </th><th>
          {{ __('Name') }}
        </th><th>
          {{ __('Location') }}
        </th><th>
          {{ __('Type') }}
        </th>
      </tr>
    </thead><tbody>
      @php $row = 1; @endphp
      @foreach($results as $item)
        <tr>
          <td>
            {{ $row++ }}
          </td><td>
            <a href="{{ url('/' . ($item->slug ?? '')) }}">{{ $item->name ?? '' }}</a>
          </td><td>
            {{ $item->location ?? '' }}
          </td><td>
            {{ $item->type ?? '' }}
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div id="result-count">
    {{ __('Showing %1% results', ['%1%' => count($results)]) }}
  </div>
</body>
</html>
