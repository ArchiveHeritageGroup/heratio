@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Search error encountered') }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp

  <div class="messages error">
    <div>
      <strong>@php echo $reason; @endphp</strong>
      @if(!empty($error))
        <pre>@php echo $error; @endphp</pre>
      @endforeach
    </div>
  </div>

  <p><a href="javascript:history.go(-1)">{{ __('Back to previous page.') }}</a></p>

@php end_slot(); @endphp
