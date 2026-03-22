@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-quote-right me-2"></i>Citation Generator</h1>@endsection
@section('content')
<div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Item Details</h5></div>
    <div class="card-body">
        @php
            $firstCitation = collect($citations)->first();
            $itemTitle = $firstCitation['title'] ?? 'Unknown Item';
            $itemIdentifier = $firstCitation['identifier'] ?? '';
            $itemRepository = $firstCitation['repository'] ?? '';
        @endphp
        <dl class="row mb-0">
            <dt class="col-sm-3">Title</dt>
            <dd class="col-sm-9">{{ e($itemTitle) }}</dd>
            @if($itemIdentifier)
            <dt class="col-sm-3">Identifier</dt>
            <dd class="col-sm-9">{{ e($itemIdentifier) }}</dd>
            @endif
            @if($itemRepository)
            <dt class="col-sm-3">Repository</dt>
            <dd class="col-sm-9">{{ e($itemRepository) }}</dd>
            @endif
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Citations</h5></div>
    <div class="card-body">
        @php
            $styleLabels = [
                'chicago' => 'Chicago',
                'mla' => 'MLA',
                'apa' => 'APA',
                'harvard' => 'Harvard',
                'turabian' => 'Turabian',
                'unisa' => 'UNISA',
            ];
        @endphp

        @foreach($styles as $style)
        <div class="mb-4">
            <label class="form-label fw-bold">
                <i class="fas fa-bookmark me-1"></i>{{ $styleLabels[$style] ?? ucfirst($style) }} <span class="badge bg-secondary ms-1">Optional</span></label>
            @if(isset($citations[$style]) && !isset($citations[$style]['error']))
            <div class="input-group">
                <textarea class="form-control" rows="3" readonly id="citation-{{ $style }}">{{ $citations[$style]['citation'] ?? '' }}</textarea>
                <button class="btn atom-btn-white" type="button" onclick="copyCitation('{{ $style }}')" title="Copy to clipboard">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            @else
            <div class="alert alert-warning py-2 mb-0">
                <i class="fas fa-exclamation-triangle me-1"></i>{{ $citations[$style]['error'] ?? 'Citation not available for this style.' }}
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function copyCitation(style) {
    var textarea = document.getElementById('citation-' + style);
    if (textarea) {
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(textarea.value).then(function() {
            var btn = textarea.nextElementSibling;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(function() { btn.innerHTML = origHtml; }, 2000);
        });
    }
}
</script>
@endpush
@endsection
