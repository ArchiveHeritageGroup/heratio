{{--
  "Cite these results" button for the GLAM browse toolbar (heratio#1585).
  Posts the current view's parameters; the citation page follows.
--}}
<form method="POST" action="{{ route('results.cite.browse') }}" class="d-inline">
  @csrf
  @foreach(request()->except(['page', '_token']) as $k => $v)
    @if(is_array($v))
      @foreach($v as $item)
        @if(is_scalar($item))<input type="hidden" name="params[{{ $k }}][]" value="{{ $item }}">@endif
      @endforeach
    @elseif(is_scalar($v))
      <input type="hidden" name="params[{{ $k }}]" value="{{ $v }}">
    @endif
  @endforeach
  <button type="submit" class="btn atom-btn-outline-success btn-sm" title="{{ __('A stable link and a citation for these results') }}">
    <i class="fas fa-quote-right" aria-hidden="true"></i> {{ __('Cite') }}
  </button>
</form>
