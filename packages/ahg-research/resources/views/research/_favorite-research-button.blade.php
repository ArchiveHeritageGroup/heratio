{{-- Research Favorite Button - Migrated from AtoM: _favoriteResearchButton.php --}}
@auth
@php
  $objectId = $objectId ?? 0;
  $objectType = $objectType ?? '';
  $title = $title ?? '';
  $url = $url ?? '';
  if (!$objectId) return;
  $isFav = false;
  try {
      $isFav = \Illuminate\Support\Facades\DB::table('favorites')
          ->where('user_id', Auth::id())
          ->where('archival_description_id', $objectId)
          ->where('object_type', $objectType)
          ->exists();
  } catch (\Exception $e) {}
  $uid = 'rfav-' . uniqid();
@endphp
<div class="btn-group" id="{{ $uid }}">
    <button type="button"
            class="btn btn-sm btn-outline-danger favorite-toggle"
            id="{{ $uid }}-btn"
            data-object-id="{{ (int) $objectId }}"
            data-object-type="{{ e($objectType) }}"
            data-title="{{ e($title) }}"
            data-url="{{ e($url) }}"
            data-favorited="{{ $isFav ? '1' : '0' }}"
            title="{{ $isFav ? 'Remove from Favorites' : 'Add to Favorites' }}">
        <i class="fa{{ $isFav ? 's' : 'r' }} fa-heart"></i>
        <span class="d-none d-md-inline ms-1">{{ $isFav ? 'Favorited' : 'Favorite' }}</span>
    </button>
    @if(!$isFav)
    <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle dropdown-toggle-split"
            id="{{ $uid }}-dd" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Choose folder') }}">
        <span class="visually-hidden">{{ __('Choose folder') }}</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" id="{{ $uid }}-menu" style="min-width:200px;">
        <li><h6 class="dropdown-header"><i class="fas fa-folder me-1"></i>{{ __('Add to folder') }}</h6></li>
        <li><hr class="dropdown-divider"></li>
        <li class="px-3 py-1 text-muted small">Loading folders...</li>
    </ul>
    @endif
</div>

<script>
(function() {
    var wrap = document.getElementById('{{ $uid }}');
    var btn = document.getElementById('{{ $uid }}-btn');
    var ddBtn = document.getElementById('{{ $uid }}-dd');
    var menu = document.getElementById('{{ $uid }}-menu');
    if (!btn) return;
    var foldersLoaded = false;
    function toggleFav(folderId) {
        var body = 'object_id=' + encodeURIComponent(btn.dataset.objectId) +
                   '&object_type=' + encodeURIComponent(btn.dataset.objectType) +
                   '&title=' + encodeURIComponent(btn.dataset.title) +
                   '&url=' + encodeURIComponent(btn.dataset.url);
        if (folderId) body += '&folder_id=' + encodeURIComponent(folderId);
        fetch('/favorites/ajax/toggle-custom', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var icon = btn.querySelector('i');
            var label = btn.querySelector('span');
            if (data.favorited) {
                icon.className = 'fas fa-heart';
                btn.dataset.favorited = '1';
                btn.title = 'Remove from Favorites';
                if (label) label.textContent = 'Favorited';
                if (ddBtn) ddBtn.style.display = 'none';
            } else {
                icon.className = 'far fa-heart';
                btn.dataset.favorited = '0';
                btn.title = 'Add to Favorites';
                if (label) label.textContent = 'Favorite';
                if (ddBtn) ddBtn.style.display = '';
            }
        });
    }
    btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); toggleFav(null); });
    if (ddBtn && menu) {
        ddBtn.addEventListener('click', function() {
            if (foldersLoaded) return;
            foldersLoaded = true;
            fetch('/favorites/ajax/folders', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                menu.innerHTML = '<li><h6 class="dropdown-header"><i class="fas fa-folder me-1"></i>Add to folder</h6></li><li><hr class="dropdown-divider"></li>';
                var rootLi = document.createElement('li');
                var rootA = document.createElement('a');
                rootA.className = 'dropdown-item'; rootA.href = '#';
                rootA.innerHTML = '<i class="fas fa-inbox me-2 text-muted"></i>Unfiled';
                rootA.addEventListener('click', function(ev) { ev.preventDefault(); toggleFav(null); });
                rootLi.appendChild(rootA); menu.appendChild(rootLi);
                if (data.folders && data.folders.length > 0) {
                    data.folders.forEach(function(f) {
                        var li = document.createElement('li');
                        var a = document.createElement('a');
                        a.className = 'dropdown-item'; a.href = '#';
                        a.innerHTML = '<i class="fas ' + (f.icon || 'fa-folder') + ' me-2" style="color:' + (f.color || '#6c757d') + ';"></i>' + (f.name || '') + (f.item_count ? ' <span class="badge bg-secondary ms-1">' + f.item_count + '</span>' : '');
                        (function(fId) { a.addEventListener('click', function(ev) { ev.preventDefault(); toggleFav(fId); }); })(f.id);
                        li.appendChild(a); menu.appendChild(li);
                    });
                }
                var newDiv = document.createElement('li'); newDiv.innerHTML = '<hr class="dropdown-divider">'; menu.appendChild(newDiv);
                var newLi = document.createElement('li'); var newA = document.createElement('a');
                newA.className = 'dropdown-item text-primary'; newA.href = '/favorites';
                newA.innerHTML = '<i class="fas fa-plus me-2"></i>Manage folders...';
                newLi.appendChild(newA); menu.appendChild(newLi);
            }).catch(function() { menu.innerHTML = '<li class="px-3 py-1 text-danger small">Could not load folders</li>'; });
        });
    }
})();
</script>
@endauth
