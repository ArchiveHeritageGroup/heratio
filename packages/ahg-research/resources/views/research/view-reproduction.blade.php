@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reproductions'])@endsection
@section('title', 'Reproduction Request')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.reproductions') }}">Reproductions</a></li>
        <li class="breadcrumb-item active">{{ $reproRequest->reference_number ?: 'DRAFT-' . $reproRequest->id }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-copy me-2"></i>{{ $reproRequest->reference_number ?: 'DRAFT-' . $reproRequest->id }}</h5>
                @php $sc = ['completed'=>'success','processing'=>'info','in_production'=>'info','cancelled'=>'danger','draft'=>'secondary','submitted'=>'warning','quoted'=>'primary','approved'=>'info']; @endphp
                <span class="badge bg-{{ $sc[$reproRequest->status ?? 'draft'] ?? 'warning' }}">{{ ucfirst(str_replace('_', ' ', $reproRequest->status ?? 'draft')) }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Purpose:</strong> {{ e($reproRequest->purpose ?? '-') }}</p>
                        <p><strong>Delivery:</strong> {{ ucfirst(str_replace('_', ' ', $reproRequest->delivery_method ?? 'email')) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created:</strong> {{ date('M j, Y', strtotime($reproRequest->created_at)) }}</p>
                        @if($reproRequest->estimated_cost)<p><strong>Estimated:</strong> R{{ number_format($reproRequest->estimated_cost, 2) }}</p>@endif
                        @if($reproRequest->final_cost)<p><strong>Final Cost:</strong> R{{ number_format($reproRequest->final_cost, 2) }}</p>@endif
                    </div>
                </div>
                @if($reproRequest->notes)<p><strong>Notes:</strong> {{ e($reproRequest->notes) }}</p>@endif
                @if($reproRequest->publication_details)<p><strong>Publication:</strong> {{ e($reproRequest->publication_details) }}</p>@endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Items ({{ count($items) }})</h5>
                @if(in_array($reproRequest->status, ['draft', 'submitted']))
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="fas fa-plus me-1"></i>Add Item</button>
                @endif
            </div>
            @if(!empty($items))
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Item</th><th>Type</th><th>Format</th><th>Qty</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>@if($item->object_slug)<a href="{{ url('/' . $item->object_slug) }}">{{ e($item->object_title ?: '#' . $item->object_id) }}</a>@else{{ e($item->object_title ?: '#' . $item->object_id) }}@endif</td>
                            <td>{{ ucfirst($item->reproduction_type ?? 'scan') }}</td>
                            <td>{{ $item->format ?? '-' }}</td>
                            <td>{{ $item->quantity ?? 1 }}</td>
                            <td><span class="badge bg-{{ ($item->status ?? 'pending') === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($item->status ?? 'pending') }}</span></td>
                            <td>@if(in_array($reproRequest->status, ['draft', 'submitted']))<form method="POST" class="d-inline" onsubmit="return confirm('Remove?')">@csrf<input type="hidden" name="form_action" value="remove_item"><input type="hidden" name="item_id" value="{{ $item->id }}"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button></form>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 opacity-50"></i><p>No items yet. Add items from the archive.</p></div>
            @endif
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h6></div>
            <div class="card-body d-grid gap-2">
                @if($reproRequest->status === 'draft' && count($items) > 0)
                <form method="POST">@csrf<input type="hidden" name="form_action" value="submit"><button class="btn btn-success w-100"><i class="fas fa-paper-plane me-1"></i>Submit Request</button></form>
                @endif
                @if(in_array($reproRequest->status, ['draft', 'submitted']))
                <form method="POST" onsubmit="return confirm('Cancel this request?')">@csrf<input type="hidden" name="form_action" value="cancel"><button class="btn btn-outline-danger w-100"><i class="fas fa-times me-1"></i>Cancel Request</button></form>
                @endif
                <a href="{{ route('research.reproductions') }}" class="btn btn-outline-secondary w-100"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Pricing</h6></div>
            <div class="card-body small text-muted">
                <p>Fees vary by type, size, resolution, quantity, urgency, and intended use.</p>
                <p class="mb-0">A quote will be provided before processing.</p>
            </div>
        </div>
    </div>
</div>

{{-- Add Item Modal --}}
<div class="modal fade" id="addItemModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="add_item"><input type="hidden" name="object_id" id="addItemObjectId">
    <div class="modal-header"><h5 class="modal-title">Add Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Search Archive Item *</label><select id="addItemSearch" placeholder="Search by title..."></select></div>
        <div class="row mb-3">
            <div class="col-md-6"><label class="form-label">Type</label><select name="reproduction_type" class="form-select"><option value="scan">Scan</option><option value="photocopy">Photocopy</option><option value="photograph">Photograph</option><option value="digital">Digital Copy</option></select></div>
            <div class="col-md-3"><label class="form-label">Format</label><select name="format" class="form-select"><option value="PDF">PDF</option><option value="TIFF">TIFF</option><option value="JPEG">JPEG</option><option value="PNG">PNG</option></select></div>
            <div class="col-md-3"><label class="form-label">Qty</label><input type="number" name="quantity" class="form-control" value="1" min="1"></div>
        </div>
        <div class="mb-3"><label class="form-label">Instructions</label><textarea name="special_instructions" class="form-control" rows="2" placeholder="e.g. 300dpi colour, A3..."></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add</button></div>
    </form>
</div></div></div>

@push('css')<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('addItemSearch');
    if (el) {
        new TomSelect(el, {
            valueField: 'id', labelField: 'name', searchField: ['name'],
            loadThrottle: 300,
            load: function(q, cb) { if (q.length<2) return cb(); fetch('/informationobject/autocomplete?query='+encodeURIComponent(q)+'&limit=20').then(function(r){return r.json()}).then(cb).catch(function(){cb()}); },
            onChange: function(v) { document.getElementById('addItemObjectId').value = v; },
            render: { option: function(i) { return '<div><strong>'+(i.name||'[Untitled]')+'</strong> <small>#'+i.id+'</small></div>'; }, item: function(i) { return '<div>'+(i.name||'[Untitled]')+'</div>'; } }
        });
    }
});
</script>
@endpush
@endsection
