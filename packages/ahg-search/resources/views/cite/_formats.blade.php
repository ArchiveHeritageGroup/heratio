{{--
  The four citation formats with copy and download (heratio#1585).
  $formats: format => text; $code: citation code. Also rendered client-side by
  _button.blade.php from the same JSON.
--}}
@php $labels = ['chicago' => 'Chicago', 'apa' => 'APA', 'bibtex' => 'BibTeX', 'ris' => 'RIS']; @endphp
@foreach($formats as $f => $text)
  <div class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-1">
      <strong>{{ $labels[$f] ?? $f }}</strong>
      <button type="button" class="btn btn-sm atom-btn-white" data-cite-copy="cite-{{ $f }}-{{ $code }}">{{ __('Copy') }}</button>
      @if(in_array($f, ['bibtex', 'ris'], true))
        <a class="btn btn-sm atom-btn-white" href="{{ route('results.cite.format', [$code, $f]) }}">{{ __('Download') }}</a>
      @endif
    </div>
    <pre id="cite-{{ $f }}-{{ $code }}" class="border rounded p-2 bg-light small mb-0" style="white-space:pre-wrap">{{ $text }}</pre>
  </div>
@endforeach
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-cite-copy]');
  if (!b) return;
  var el = document.getElementById(b.getAttribute('data-cite-copy'));
  if (el && navigator.clipboard) navigator.clipboard.writeText(el.textContent).then(function () { b.textContent = @json(__('Copied')); });
});
</script>
