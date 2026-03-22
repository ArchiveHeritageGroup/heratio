{{-- Request Item Button - Migrated from AtoM: _requestButton.php --}}
@php $objectId = $objectId ?? 0; @endphp
@if($objectId)
<div class="ahg-request-item-container mt-3 mb-3">
    <button type="button" class="btn btn-outline-primary btn-sm" id="requestItemBtn-{{ $objectId }}"
            aria-label="Request this item for reading room access" data-object-id="{{ $objectId }}">
        <i class="fas fa-hand-holding me-1" aria-hidden="true"></i>Request this Item
    </button>
    <div id="requestItemResult-{{ $objectId }}" class="mt-2" role="status" aria-live="polite"></div>
</div>
<script>
(function() {
    var btn = document.getElementById('requestItemBtn-{{ $objectId }}');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var objectId = this.getAttribute('data-object-id');
        var resultDiv = document.getElementById('requestItemResult-' + objectId);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Requesting...';
        fetch('{{ route("research.addToCollection") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: 'object_id=' + encodeURIComponent(objectId) + '&request_type=reading_room'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success py-1 px-2 small"><i class="fas fa-check-circle me-1"></i>' + (data.message || 'Item requested.') + '</div>';
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Requested';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-warning py-1 px-2 small"><i class="fas fa-exclamation-triangle me-1"></i>' + (data.error || 'Could not request item.') + '</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-hand-holding me-1"></i> Request this Item';
            }
        })
        .catch(function() {
            resultDiv.innerHTML = '<div class="alert alert-danger py-1 px-2 small">Network error. Please try again.</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-hand-holding me-1"></i> Request this Item';
        });
    });
})();
</script>
@endif
