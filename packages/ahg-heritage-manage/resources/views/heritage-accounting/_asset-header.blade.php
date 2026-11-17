{{-- Asset caption for the sub-ledger forms: identifier - title and standard. --}}
<p class="text-muted mb-3">
  <a href="{{ route('heritage.accounting.view', $asset->id) }}">{{ $asset->object_identifier ?: __('Asset #:id', ['id' => $asset->id]) }}</a>
  - {{ $asset->object_title ?: __('Untitled') }}
  @if($asset->standard_code)<span class="badge bg-light text-dark ms-2">{{ $asset->standard_code }}</span>@endif
</p>
