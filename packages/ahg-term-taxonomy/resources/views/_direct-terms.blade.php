@if(
    !request()->has('onlyDirect')
    && isset($aggs['direct'])
    && 0 < $aggs['direct']['doc_count']
)
  <div class="d-grid d-sm-flex gap-2 align-items-center p-3 border-bottom">
    {{ __(
        '%1% results directly related',
        ['%1%' => $aggs['direct']['doc_count']]
    ) }}
    @php $params = request()->query();
      unset($params['page']); @endphp
    <a
      class="btn btn-sm atom-btn-white ms-auto text-wrap"
      href="{{ route('term.' . request()->route()->getName(), array_merge(['slug' => $resource->slug], $params, ['onlyDirect' => true])) }}">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Exclude narrower terms') }}
    </a>
  </div>
@endif
