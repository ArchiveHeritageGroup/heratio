{{--
  Donor autocomplete result table.

  Consumed by the YUI form-autocomplete widget in the accession "Related donor"
  modal (ahgThemeB5Plugin bundle, parseHTMLTableData / TYPE_HTMLTABLE). The
  widget reads each <tbody><tr>'s first <td><a>: the link TEXT becomes the
  visible textbox value (display name) and the link HREF becomes the value
  written into the hidden donor input (e[1] -> l.val(e[1])). Do NOT change the
  single-anchor-per-row structure or the widget stops resolving selections.
--}}
<table class="sticky-enabled">
  <thead>
    <tr>
      <th>{{ __('Name') }}</th>
    </tr>
  </thead>
  <tbody>
    @foreach($donors as $donor)
      <tr>
        <td>
          <a href="{{ $donor['slug'] ? url('/'.$donor['slug']) : '#' }}">{{ $donor['label'] }}</a>
        </td>
      </tr>
    @endforeach
  </tbody>
</table>
