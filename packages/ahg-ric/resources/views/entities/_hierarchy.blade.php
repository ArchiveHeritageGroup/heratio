{{-- RiC Hierarchy Section (isPartOf / hasPart) --}}
@php
    $hierarchy = $hierarchy ?? ['parent' => null, 'children' => collect(), 'siblings' => collect()];
    $parent = $hierarchy['parent'];
    $children = $hierarchy['children'];
    $siblings = $hierarchy['siblings'];
    $entityType = $entityType ?? 'entity';
    $typeSlugMap = ['Place' => 'places', 'Rule' => 'rules', 'Activity' => 'activities', 'Instantiation' => 'instantiations', 'Record' => 'informationobject', 'Agent' => 'actor'];
@endphp

<section class="mb-3">
    <h2 class="h6 text-muted"><i class="fas fa-sitemap me-1"></i> Hierarchy (isPartOf / hasPart)</h2>

    <div class="border rounded p-3 bg-light">
        {{-- Parent --}}
        <div class="mb-2">
            <small class="text-muted fw-bold d-block mb-1">Parent (isPartOf)</small>
            @if($parent)
                <span class="badge bg-secondary me-1">{{ $parent->type }}</span>
                @php $parentSlug = $typeSlugMap[$parent->type] ?? null; @endphp
                @if($parentSlug && $parent->slug)
                    <a href="{{ route('ric.entities.show', [$parentSlug, $parent->slug]) }}">{{ $parent->name }}</a>
                @else
                    {{ $parent->name }}
                @endif
            @else
                <span class="text-muted fst-italic">No parent</span>
                <button type="button" class="btn btn-outline-primary btn-sm ms-2 ric-hierarchy-add" data-direction="parent" data-relation="has_part">
                    <i class="fas fa-level-up-alt"></i> Set Parent
                </button>
            @endif
        </div>

        {{-- Current entity indicator --}}
        <div class="mb-2 ps-3 border-start border-3 border-primary">
            <span class="badge bg-primary me-1">{{ ucfirst($entityType) }}</span>
            <strong>{{ $entity->name ?? $entity->title ?? 'Current' }}</strong>
            <small class="text-muted">(this {{ $entityType }})</small>
        </div>

        {{-- Children --}}
        <div class="mb-2">
            <small class="text-muted fw-bold d-block mb-1">Children (hasPart) <span class="badge bg-secondary">{{ $children->count() }}</span></small>
            @if($children->count())
                <ul class="list-unstyled ps-3 mb-0">
                    @foreach($children as $child)
                    <li class="mb-1">
                        <i class="fas fa-angle-right text-muted me-1"></i>
                        <span class="badge bg-secondary me-1">{{ $child->type }}</span>
                        @php $childSlug = $typeSlugMap[$child->type] ?? null; @endphp
                        @if($childSlug && $child->slug)
                            <a href="{{ route('ric.entities.show', [$childSlug, $child->slug]) }}">{{ $child->name }}</a>
                        @else
                            {{ $child->name }}
                        @endif
                    </li>
                    @endforeach
                </ul>
            @else
                <span class="text-muted fst-italic ps-3">No children</span>
            @endif
            <button type="button" class="btn btn-outline-success btn-sm mt-1 ric-hierarchy-add" data-direction="child" data-relation="has_part">
                <i class="fas fa-plus"></i> Add Child
            </button>
        </div>

        {{-- Siblings --}}
        @if($siblings->count())
        <div>
            <small class="text-muted fw-bold d-block mb-1">Siblings <span class="badge bg-secondary">{{ $siblings->count() }}</span></small>
            <ul class="list-unstyled ps-3 mb-0">
                @foreach($siblings as $sib)
                <li class="mb-1">
                    <i class="fas fa-arrows-alt-h text-muted me-1"></i>
                    <span class="badge bg-secondary me-1">{{ $sib->type }}</span>
                    @php $sibSlug = $typeSlugMap[$sib->type] ?? null; @endphp
                    @if($sibSlug && $sib->slug)
                        <a href="{{ route('ric.entities.show', [$sibSlug, $sib->slug]) }}">{{ $sib->name }}</a>
                    @else
                        {{ $sib->name }}
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</section>

{{-- Hierarchy add modal --}}
<div class="modal fade" id="ricHierarchyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ricHierarchyModalTitle"><i class="fas fa-sitemap"></i> Set Hierarchy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Search for entity</label>
                <input type="text" id="ric-hier-search" class="form-control" placeholder="Type to search..." autocomplete="off">
                <input type="hidden" id="ric-hier-target-id">
                <input type="hidden" id="ric-hier-direction">
                <div id="ric-hier-results" class="list-group mt-1" style="max-height:200px; overflow-y:auto; display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ricHierarchySaveBtn"><i class="fas fa-link"></i> Link</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const entityId = {{ $entity->id }};

    // Open hierarchy modal
    document.querySelectorAll('.ric-hierarchy-add').forEach(btn => {
        btn.addEventListener('click', function() {
            const direction = this.dataset.direction;
            document.getElementById('ric-hier-direction').value = direction;
            document.getElementById('ricHierarchyModalTitle').textContent = direction === 'parent' ? 'Set Parent Entity' : 'Add Child Entity';
            document.getElementById('ric-hier-search').value = '';
            document.getElementById('ric-hier-target-id').value = '';
            document.getElementById('ric-hier-results').style.display = 'none';
            new bootstrap.Modal(document.getElementById('ricHierarchyModal')).show();
        });
    });

    // Autocomplete search
    let debounce;
    const searchInput = document.getElementById('ric-hier-search');
    const resultsList = document.getElementById('ric-hier-results');
    const targetInput = document.getElementById('ric-hier-target-id');

    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { resultsList.style.display = 'none'; return; }
        debounce = setTimeout(() => {
            fetch(`/admin/ric/entity-api/autocomplete?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(items => {
                    if (!items.length) { resultsList.innerHTML = '<div class="list-group-item text-muted">No results</div>'; resultsList.style.display = ''; return; }
                    resultsList.innerHTML = items.filter(i => i.id !== entityId).map(i =>
                        `<a href="#" class="list-group-item list-group-item-action py-1" data-id="${i.id}">
                            <span class="badge bg-secondary me-1">${i.type}</span> ${i.label}
                        </a>`
                    ).join('');
                    resultsList.style.display = '';
                    resultsList.querySelectorAll('a').forEach(a => {
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            targetInput.value = this.dataset.id;
                            searchInput.value = this.textContent.trim();
                            resultsList.style.display = 'none';
                        });
                    });
                });
        }, 300);
    });

    // Save hierarchy link
    document.getElementById('ricHierarchySaveBtn').addEventListener('click', function() {
        const targetId = parseInt(targetInput.value);
        const direction = document.getElementById('ric-hier-direction').value;
        if (!targetId) { alert('Select an entity first'); return; }

        // has_part: subject=parent, object=child
        let subjectId, objectId;
        if (direction === 'parent') {
            // target is the parent, this entity is the child
            subjectId = targetId;
            objectId = entityId;
        } else {
            // this entity is the parent, target is the child
            subjectId = entityId;
            objectId = targetId;
        }

        fetch('/admin/ric/entity-api/relation-store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                subject_id: subjectId,
                object_id: objectId,
                relation_type: 'has_part'
            })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
});
</script>
