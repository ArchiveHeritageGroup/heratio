@if('print' == $sf_request->getParameter('media'))
  <div id="preview-message">
    {{ __('Print preview') }}
    @php echo link_to(__('Close'), array_diff($sf_data->getRaw('sf_request')->getParameterHolder()->getAll(), ['media' => 'print'])); @endphp
  </div>
@endforeach
