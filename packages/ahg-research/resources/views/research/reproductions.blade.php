@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reproductions'])@endsection
@section('title', 'Reproduction Requests')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Reproduction Requests</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-copy text-primary me-2"></i>{{ __('Reproduction Requests') }}</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequestModal"><i class="fas fa-plus me-1"></i>{{ __('New Request') }}</button>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <form method="get">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach(['draft', 'submitted', 'quoted', 'approved', 'processing', 'in_production', 'completed', 'cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

@if(count($requests) > 0)
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('Purpose') }}</th>
                    <th>{{ __('Items') }}</th>
                    <th>{{ __('Total Cost') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($requests as $req)
                <tr>
                    <td><code>{{ $req->reference_number ?: 'DRAFT-' . $req->id }}</code></td>
                    <td>{{ e(\Illuminate\Support\Str::limit($req->purpose ?? '', 50)) }}</td>
                    <td><span class="badge bg-secondary">{{ $req->item_count ?? 0 }}</span></td>
                    <td>{{ $req->final_cost ? 'R' . number_format($req->final_cost, 2) : ($req->estimated_cost ? 'R' . number_format($req->estimated_cost, 2) . ' (est)' : '-') }}</td>
                    <td>
                        @php $sc = ['completed'=>'success','processing'=>'info','in_production'=>'info','cancelled'=>'danger','draft'=>'secondary']; @endphp
                        <span class="badge bg-{{ $sc[$req->status ?? 'draft'] ?? 'warning' }}">{{ ucfirst(str_replace('_', ' ', $req->status ?? 'draft')) }}</span>
                    </td>
                    <td>{{ date('M j, Y', strtotime($req->created_at)) }}</td>
                    <td>
                        <a href="{{ route('research.viewReproduction', $req->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-copy fa-3x text-muted mb-3"></i>
        <h5>{{ __('No Reproduction Requests') }}</h5>
        <p class="text-muted">Request copies or scans of archival materials.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequestModal"><i class="fas fa-plus me-1"></i>{{ __('New Request') }}</button>
    </div>
</div>
@endif

{{-- New Request Modal (matching AtoM new reproduction form) --}}
<div class="modal fade" id="newRequestModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
    <form method="POST">
        @csrf
        <input type="hidden" name="form_action" value="create">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-copy me-2"></i>{{ __('New Reproduction Request') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">{{ __('Purpose of Reproduction *') }}</label>
                <select name="purpose" class="form-select" required>
                    <option value="">{{ __('Select purpose...') }}</option>
                    <option value="Academic Research">{{ __('Academic Research') }}</option>
                    <option value="Publication">{{ __('Publication') }}</option>
                    <option value="Exhibition">{{ __('Exhibition') }}</option>
                    <option value="Documentary/Film">{{ __('Documentary/Film') }}</option>
                    <option value="Personal Use">{{ __('Personal Use') }}</option>
                    <option value="Commercial Use">{{ __('Commercial Use') }}</option>
                    <option value="Other">{{ __('Other') }}</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Intended Use') }}</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('Describe how you plan to use the reproductions...') }}"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Publication Details') }}</label>
                <textarea name="publication_details" class="form-control" rows="2" placeholder="{{ __('If for publication, provide title, publisher, expected date...') }}"></textarea>
                <small class="text-muted">{{ __('Required for publication or commercial use') }}</small>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Delivery Method') }}</label>
                    <select name="delivery_method" class="form-select">
                        <option value="digital">{{ __('Digital Download') }}</option>
                        <option value="email">{{ __('Email') }}</option>
                        <option value="physical">{{ __('Physical Copy (Post)') }}</option>
                        <option value="collect">{{ __('Collect in Person') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Urgency') }}</label>
                    <select name="urgency" class="form-select">
                        <option value="normal">{{ __('Normal (10-15 working days)') }}</option>
                        <option value="high">{{ __('High Priority (5-7 working days)') }}</option>
                        <option value="rush">{{ __('Rush (2-3 working days) - additional fee') }}</option>
                    </select>
                </div>
            </div>
            <hr>
            <h6><span class="badge bg-primary me-1">{{ __('Optional') }}</span> Add First Item</h6>
            <input type="hidden" name="object_id" id="createReproObjectId">
            <div class="mb-3">
                <label class="form-label">{{ __('Archive Item') }}</label>
                <select id="createReproItemSearch" placeholder="{{ __('Search by title...') }}"></select>
                <small class="text-muted">{{ __('You can add more items after creating the request.') }}</small>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Type') }}</label>
                    <select name="reproduction_type" class="form-select">
                        <option value="scan">{{ __('Scan') }}</option>
                        <option value="photocopy">{{ __('Photocopy') }}</option>
                        <option value="photograph">{{ __('Photograph') }}</option>
                        <option value="digital">{{ __('Digital Copy') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Format') }}</label>
                    <select name="format" class="form-select">
                        <option value="PDF">PDF</option>
                        <option value="TIFF">TIFF</option>
                        <option value="JPEG">JPEG</option>
                        <option value="PNG">PNG</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Specifications') }}</label>
                    <input type="text" name="specifications" class="form-control" placeholder="{{ __('e.g. 300dpi colour') }}">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Create Request') }}</button>
        </div>
    </form>
</div>
</div>
</div>

@push('css')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('createReproItemSearch');
    if (el) {
        new TomSelect(el, {
            valueField: 'id', labelField: 'name', searchField: ['name'],
            loadThrottle: 300,
            load: function(q, cb) { if (q.length<2) return cb(); fetch('/informationobject/autocomplete?query='+encodeURIComponent(q)+'&limit=20').then(function(r){return r.json()}).then(cb).catch(function(){cb()}); },
            onChange: function(v) { document.getElementById('createReproObjectId').value = v; },
            render: {
                option: function(i) { return '<div><strong>'+(i.name||'[Untitled]')+'</strong> <small class="text-muted">#'+i.id+'</small></div>'; },
                item: function(i) { return '<div>'+(i.name||'[Untitled]')+'</div>'; }
            }
        });
    }
});
</script>
@endpush
@endsection
