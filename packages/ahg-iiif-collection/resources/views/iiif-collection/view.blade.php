@extends('theme::layouts.2col')

@php
// Usage IDs
$USAGE_REFERENCE = 141;
$USAGE_THUMBNAIL = 142;

// Media type IDs
$MEDIA_IMAGE = 136;
$MEDIA_AUDIO = 135;
$MEDIA_VIDEO = 138;
$MEDIA_TEXT = 137;

$iiifSupportedFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/jp2'];

// Get all object IDs from collection items
$objectIds = [];
foreach ($collection->items as $item) {
    if ($item->object_id) {
        $objectIds[] = $item->object_id;
    }
}

// Get digital objects and their derivatives for all items
$imageStatus = [];
if (!empty($objectIds)) {
    $masters = \Illuminate\Support\Facades\DB::table('digital_object')
        ->whereIn('object_id', $objectIds)
        ->whereNull('parent_id')
        ->select('id', 'object_id', 'name', 'path', 'mime_type', 'media_type_id')
        ->get()
        ->keyBy('object_id');

    $masterIds = $masters->pluck('id')->toArray();
    $derivatives = collect();
    if (!empty($masterIds)) {
        $derivatives = \Illuminate\Support\Facades\DB::table('digital_object')
            ->whereIn('parent_id', $masterIds)
            ->whereIn('usage_id', [$USAGE_REFERENCE, $USAGE_THUMBNAIL])
            ->select('id', 'parent_id', 'name', 'path', 'usage_id')
            ->get()
            ->groupBy('parent_id');
    }

    foreach ($objectIds as $objId) {
        $master = $masters->get($objId);
        $status = [
            'has_master' => false,
            'has_reference' => false,
            'has_thumbnail' => false,
            'iiif_compatible' => false,
            'displayable' => false,
            'media_type' => null,
            'is_image' => false,
            'is_audio' => false,
            'is_video' => false,
            'warning' => null,
        ];

        if ($master) {
            $status['has_master'] = true;
            $status['media_type'] = $master->media_type_id;
            $status['is_image'] = ($master->media_type_id == $MEDIA_IMAGE);
            $status['is_audio'] = ($master->media_type_id == $MEDIA_AUDIO);
            $status['is_video'] = ($master->media_type_id == $MEDIA_VIDEO);

            $masterDerivs = $derivatives->get($master->id, collect());
            $status['has_reference'] = $masterDerivs->contains('usage_id', $USAGE_REFERENCE);
            $status['has_thumbnail'] = $masterDerivs->contains('usage_id', $USAGE_THUMBNAIL);

            if ($status['is_image']) {
                $status['iiif_compatible'] = in_array($master->mime_type, $iiifSupportedFormats);
                $status['displayable'] = $status['has_reference'] || $status['has_thumbnail'] || $status['iiif_compatible'];
                if (!$status['displayable']) {
                    $status['warning'] = 'Unsupported image format: ' . $master->mime_type;
                }
            } elseif ($status['is_video']) {
                $status['displayable'] = $status['has_reference'] || $status['has_thumbnail'];
                if (!$status['displayable']) {
                    $status['warning'] = 'Video without preview image - generate thumbnail';
                }
            } elseif ($status['is_audio']) {
                $status['displayable'] = $status['has_reference'] || $status['has_thumbnail'];
                if (!$status['displayable']) {
                    $status['warning'] = 'Audio without cover art - not displayable in carousel';
                }
            } else {
                $status['displayable'] = false;
                $status['warning'] = 'Non-visual media type - not displayable in carousel';
            }
        } else {
            $status['warning'] = 'No digital object attached';
        }

        $imageStatus[$objId] = $status;
    }
}

// Count displayable items
$displayableCount = 0;
$warningCount = 0;
foreach ($collection->items as $item) {
    if ($item->object_id && isset($imageStatus[$item->object_id])) {
        if ($imageStatus[$item->object_id]['displayable']) {
            $displayableCount++;
        } else {
            $warningCount++;
        }
    } elseif ($item->manifest_uri) {
        $displayableCount++;
    } else {
        $warningCount++;
    }
}
@endphp

@section('sidebar')
<div class="sidebar-content">
    <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h5>
        </div>
        <div class="card-body">
            <a href="{{ route('iiif-collection.manifest', $collection->slug) }}" class="btn atom-btn-white w-100 mb-2" target="_blank">
                <i class="fas fa-code me-2"></i>View IIIF JSON
            </a>
            @auth
            <a href="{{ route('iiif-collection.add-items', $collection->id) }}" class="btn atom-btn-outline-success w-100 mb-2">
                <i class="fas fa-plus me-2"></i>Add Items
            </a>
            <a href="{{ route('iiif-collection.edit', $collection->id) }}" class="btn atom-btn-white w-100 mb-2">
                <i class="fas fa-edit me-2"></i>Edit Collection
            </a>
            <a href="{{ route('iiif-collection.create', ['parent_id' => $collection->id]) }}" class="btn atom-btn-outline-success w-100 mb-2">
                <i class="fas fa-folder-plus me-2"></i>Create Subcollection
            </a>
            <hr>
            <form method="POST" action="{{ route('iiif-collection.destroy', $collection->id) }}" onsubmit="return confirm('Are you sure you want to delete this collection?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn atom-btn-outline-danger w-100">
                    <i class="fas fa-trash me-2"></i>Delete Collection
                </button>
            </form>
            @endauth
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Details</h5>
        </div>
        <div class="card-body">
            <dl class="mb-0">
                <dt>Items</dt>
                <dd>{{ count($collection->items) }}</dd>

                <dt>Displayable</dt>
                <dd>
                    <span class="badge bg-success">{{ $displayableCount }}</span>
                    @if($warningCount > 0)
                    <span class="badge bg-warning text-dark">{{ $warningCount }} with issues</span>
                    @endif
                </dd>

                <dt>Subcollections</dt>
                <dd>{{ count($collection->subcollections) }}</dd>

                <dt>Visibility</dt>
                <dd>
                    @if($collection->is_public)
                    <span class="badge bg-success">Public</span>
                    @else
                    <span class="badge bg-warning">Private</span>
                    @endif
                </dd>

                @if($collection->viewing_hint)
                <dt>Viewing Hint</dt>
                <dd><code>{{ e($collection->viewing_hint) }}</code></dd>
                @endif

                <dt>IIIF URI</dt>
                <dd><small><code>{{ route('iiif-collection.manifest', $collection->slug) }}</code></small></dd>
            </dl>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2">
        <li class="breadcrumb-item"><a href="{{ route('iiif-collection.index') }}">Collections</a></li>
        @foreach($breadcrumbs as $bc)
            @if($bc->id === $collection->id)
            <li class="breadcrumb-item active">{{ e($bc->display_name) }}</li>
            @else
            <li class="breadcrumb-item"><a href="{{ route('iiif-collection.view', $bc->id) }}">{{ e($bc->display_name) }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
<h1><i class="fas fa-layer-group me-2"></i>{{ e($collection->display_name) }}</h1>
@endsection

@section('content')
<div class="iiif-collection-view">
    @if($collection->display_description)
    <div class="lead mb-4">{{ e($collection->display_description) }}</div>
    @endif

    @if($collection->attribution)
    <p class="text-muted"><strong>Attribution:</strong> {{ e($collection->attribution) }}</p>
    @endif

    @if($warningCount > 0)
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Display Warning:</strong>
        {{ $warningCount }} item(s) will not display in carousels/galleries.
        <small class="d-block mt-1">Audio/video files need thumbnails, and only image files can be displayed directly.</small>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(!empty($collection->subcollections))
    <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Subcollections</h5>
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-3 g-3">
                @foreach($collection->subcollections as $sub)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="{{ route('iiif-collection.view', $sub->id) }}">
                                    <i class="fas fa-folder me-1"></i>{{ e($sub->display_name) }}
                                </a>
                            </h6>
                            <span class="badge bg-secondary">{{ $sub->item_count }} items</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-images me-2"></i>Items ({{ count($collection->items) }})</h5>
            @auth
            @if($warningCount > 0)
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="hideProblematic" onchange="toggleProblematicItems(this.checked)">
                <label class="form-check-label" for="hideProblematic">Hide non-displayable</label>
            </div>
            @endif
            @endauth
        </div>
        <div class="card-body">
            @if(empty($collection->items))
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                No items in this collection yet.
                @auth
                <a href="{{ route('iiif-collection.add-items', $collection->id) }}">Add items</a>
                @endauth
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Title</th>
                            <th>Identifier</th>
                            <th>Type</th>
                            <th style="width: 80px;">Media</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="sortable-items">
                        @foreach($collection->items as $item)
                        @php
                            $status = $imageStatus[$item->object_id] ?? null;
                            $isDisplayable = $item->manifest_uri || ($status && $status['displayable']);
                            $rowClass = $isDisplayable ? '' : 'table-warning item-problematic';
                        @endphp
                        <tr data-item-id="{{ $item->id }}" class="{{ $rowClass }}">
                            <td class="drag-handle text-center text-muted"><i class="fas fa-grip-vertical"></i></td>
                            <td>
                                @if($item->slug)
                                <a href="{{ url('/' . $item->slug) }}">
                                    {{ e($item->label ?: $item->object_title ?: 'Untitled') }}
                                </a>
                                @elseif($item->manifest_uri)
                                <a href="{{ e($item->manifest_uri) }}" target="_blank">
                                    {{ e($item->label ?: 'External Manifest') }}
                                    <i class="fas fa-external-link-alt ms-1 small"></i>
                                </a>
                                @else
                                {{ e($item->label ?: 'Untitled') }}
                                @endif
                            </td>
                            <td><code>{{ e($item->identifier ?: '-') }}</code></td>
                            <td>
                                @if($item->item_type === 'collection')
                                <span class="badge bg-info">Collection</span>
                                @else
                                <span class="badge bg-primary">Manifest</span>
                                @endif
                            </td>
                            <td>
                                @if($status)
                                    @if($status['is_image'])
                                        <span class="badge bg-success"><i class="fas fa-image"></i></span>
                                    @elseif($status['is_video'])
                                        <span class="badge bg-info"><i class="fas fa-film"></i></span>
                                    @elseif($status['is_audio'])
                                        <span class="badge bg-secondary"><i class="fas fa-music"></i></span>
                                    @else
                                        <span class="badge bg-dark"><i class="fas fa-file"></i></span>
                                    @endif
                                @elseif($item->manifest_uri)
                                    <span class="badge bg-info"><i class="fas fa-external-link-alt"></i></span>
                                @else
                                    <span class="badge bg-light text-dark">-</span>
                                @endif
                            </td>
                            <td>
                                @if($item->manifest_uri)
                                    <span class="badge bg-info" title="External manifest">
                                        <i class="fas fa-check me-1"></i>External
                                    </span>
                                @elseif($status)
                                    @if($status['displayable'])
                                        <span class="badge bg-success" title="{{ $status['has_reference'] ? 'Reference image' : ($status['has_thumbnail'] ? 'Thumbnail' : 'IIIF compatible') }}">
                                            <i class="fas fa-check me-1"></i>OK
                                        </span>
                                    @else
                                        <span class="badge bg-warning text-dark" title="{{ e($status['warning']) }}">
                                            <i class="fas fa-exclamation-triangle me-1"></i>No preview
                                        </span>
                                    @endif
                                @else
                                    <span class="badge bg-danger" title="No digital object">
                                        <i class="fas fa-times me-1"></i>Missing
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($item->slug)
                                <a href="{{ route('iiif-collection.object-manifest', $item->slug) }}" class="btn btn-sm atom-btn-white" target="_blank" title="View IIIF Manifest">
                                    <i class="fas fa-code"></i>
                                </a>
                                @endif
                                @auth
                                <a href="{{ route('iiif-collection.remove-item', ['item_id' => $item->id, 'collection_id' => $collection->id]) }}"
                                   class="btn btn-sm atom-btn-outline-danger"
                                   onclick="return confirm('Remove this item from the collection?')"
                                   title="Remove">
                                    <i class="fas fa-times"></i>
                                </a>
                                @endauth
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

@auth
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.querySelector('.sortable-items');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function(evt) {
                var itemIds = Array.from(tbody.querySelectorAll('tr')).map(function(row) {
                    return row.dataset.itemId;
                });

                fetch('{{ route('iiif-collection.reorder') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: 'collection_id={{ $collection->id }}&item_ids[]=' + itemIds.join('&item_ids[]=')
                });
            }
        });
    }
});

function toggleProblematicItems(hide) {
    document.querySelectorAll('.item-problematic').forEach(function(row) {
        row.style.display = hide ? 'none' : '';
    });
}
</script>
@endauth

<style>
.drag-handle { cursor: grab; }
.drag-handle:active { cursor: grabbing; }
.sortable-ghost { opacity: 0.4; background: #f0f0f0; }
</style>
@endsection
