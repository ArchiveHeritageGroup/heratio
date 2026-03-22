@if(isset($errorSchema))
  <div class="alert alert-danger" role="alert">
    <ul class="@php echo render_b5_show_list_css_classes(); @endphp">
      @foreach($errorSchema as $error)
        @php $error = sfOutputEscaper::unescape($error); @endphp
        <li>@php echo $error->getMessage(); @endphp</li>
      @endforeach
    </ul>
  </div>
@endforeach
