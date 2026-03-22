@if(
    !isset($sf_request->onlyDirect)
    && isset($aggs['direct'])
    && 0 < $aggs['direct']['doc_count']
)
  <div class="d-grid d-sm-flex gap-2 align-items-center p-3 border-bottom">
    {{ __(
        '%1% results directly related',
        ['%1%' => $aggs['direct']['doc_count']]
    ) }}
    @php $params = $sf_data->getRaw('sf_request')->getGetParameters(); @endphp
    @php unset($params['page']); @endphp
    <a
      class="btn btn-sm atom-btn-white ms-auto text-wrap"
      href="@php echo url_for(
          [$resource, 'module' => 'term', 'action' => $sf_request->getParameter('action')]
          + $params
          + ['onlyDirect' => true]
      ); @endphp">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Exclude narrower terms') }}
    </a>
  </div>
@endforeach
