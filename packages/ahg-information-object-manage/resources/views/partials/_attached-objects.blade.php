{{--
  #1447 - additional digital objects attached directly to this description
  (recto/verso, page scans, multiple views) without child records. Self-contained:
  reads AttachedDigitalObjectService for $io. Renders a thumbnail gallery; editors
  (update permission) also get a multi-file attach form and per-item delete.
--}}
@php
    $__io = $io ?? ($resource ?? null);
@endphp
@if($__io && isset($__io->id) && \AhgCore\Services\AttachedDigitalObjectService::available())
  @php
    $__svc = app(\AhgCore\Services\AttachedDigitalObjectService::class);
    $__attached = $__svc->listFor((int) $__io->id);
    $__canEditAttachments = auth()->check()
        && \AhgCore\Services\AclService::hasPermission(auth()->id(), 'update', (int) $__io->id);
    $__doUrl = fn ($do) => $do ? \AhgCore\Services\DigitalObjectService::getUrl($do) : null;

    // #1489: the primary is digital_object.object_id, never a flag in the link
    // table, so it is fetched separately and is NOT one of $__attached.
    $__primary = $__canEditAttachments ? $__svc->primaryMaster((int) $__io->id) : null;

    // Move up / move down post the WHOLE resulting order, so the endpoint stays
    // one operation and a stale page cannot half-apply a swap.
    $__ids = $__attached->pluck('link_id')->values()->all();
    $__swap = function (int $i, int $j) use ($__ids) {
        if (! isset($__ids[$i], $__ids[$j])) { return null; }
        $o = $__ids; [$o[$i], $o[$j]] = [$o[$j], $o[$i]];
        return implode(',', $o);
    };
  @endphp

  {{-- #1447 (c): attached objects DISPLAY now lives in the imageflow carousel
       (merged with child thumbnails). This card is the editor management panel -
       attach new files + remove existing - so it renders only for editors. --}}
  @if($__canEditAttachments)
    <div class="attached-objects card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:#10373E;color:#fff">
        <span><i class="fas fa-images me-2"></i>{{ __('Manage attached images / files') }}
          @if($__attached->isNotEmpty())<span class="badge bg-light text-dark ms-2">{{ $__attached->count() }}</span>@endif
        </span>
      </div>
      <div class="card-body">

        {{-- What "primary" currently is, shown before the controls that change it. --}}
        <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
          <span class="badge" style="background:#10373E">{{ __('Primary') }}</span>
          @if($__primary)
            @php $__pThumb = $__doUrl($__primary); @endphp
            @if($__pThumb && str_starts_with((string) ($__primary->mime_type ?? ''), 'image/'))
              <img src="{{ $__pThumb }}" alt="" style="height:44px;width:44px;object-fit:cover;border-radius:3px;">
            @endif
            <span class="small text-muted">{{ \Illuminate\Support\Str::limit($__primary->name ?? '', 48) }}</span>
          @else
            <span class="small text-muted">{{ __('This description has no primary object. Promote one below.') }}</span>
          @endif
        </div>

        @if($__attached->isNotEmpty())
          <div class="row g-3" data-attach-sortable data-reorder-url="{{ route('io.attachments.reorder', $__io->slug) }}">
            @foreach($__attached as $att)
              @php
                // Public-facing viewers get a derivative (reference, else thumbnail);
                // the master is never linked directly here. Editors may open the master.
                $thumb = $__doUrl($att->thumbnail ?: $att->reference ?: ($__canEditAttachments ? $att->master : null));
                $open  = $__canEditAttachments
                    ? $__doUrl($att->master ?: $att->reference ?: $att->thumbnail)
                    : $__doUrl($att->reference ?: $att->thumbnail);
                $isImage = $att->master && str_starts_with((string) ($att->master->mime_type ?? ''), 'image/');
              @endphp
              <div class="col-6 col-md-4 col-lg-3" data-link-id="{{ $att->link_id }}" @if($__canEditAttachments) draggable="true" @endif>
                <div class="border rounded h-100 d-flex flex-column">
                  <a href="{{ $open ?: '#' }}" @if($open) target="_blank" rel="noopener" @endif class="text-center p-2 flex-grow-1 d-flex align-items-center justify-content-center" style="min-height:120px;background:#f6f8f8;">
                    @if($isImage && $thumb)
                      <img src="{{ $thumb }}" alt="{{ e($att->caption ?: ($att->master->name ?? '')) }}" style="max-width:100%;max-height:150px;object-fit:contain;">
                    @else
                      <span class="text-muted"><i class="fas fa-file fa-2x d-block mb-1"></i>{{ \Illuminate\Support\Str::limit($att->master->name ?? 'file', 24) }}</span>
                    @endif
                  </a>
                  <div class="px-2 pb-2 small">
                    @if($att->role)<span class="badge bg-secondary">{{ $att->role }}</span>@endif
                    @if($att->caption)<div class="text-muted mt-1">{{ \Illuminate\Support\Str::limit($att->caption, 60) }}</div>@endif
                    @if($__canEditAttachments)
                      @php
                        $__up = $__swap($loop->index, $loop->index - 1);
                        $__down = $__swap($loop->index, $loop->index + 1);
                      @endphp
                      <div class="d-flex flex-wrap gap-1 mt-1">
                        {{-- Promoting MOVES object_id; the outgoing primary drops
                             into this list at the slot this object vacates. --}}
                        <form method="post" action="{{ route('io.attachments.primary', $att->link_id) }}">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-success py-0" title="{{ __('Use this as the record image') }}">
                            <i class="fas fa-star me-1"></i>{{ __('Make primary') }}
                          </button>
                        </form>
                        {{-- Plain form posts, so reordering works with no JS at
                             all; the drag handler below is an enhancement. --}}
                        <form method="post" action="{{ route('io.attachments.reorder', $__io->slug) }}">
                          @csrf
                          <input type="hidden" name="order" value="{{ $__up }}">
                          <button type="submit" class="btn btn-sm btn-outline-secondary py-0" @disabled(!$__up) title="{{ __('Move earlier') }}"><i class="fas fa-arrow-left"></i></button>
                        </form>
                        <form method="post" action="{{ route('io.attachments.reorder', $__io->slug) }}">
                          @csrf
                          <input type="hidden" name="order" value="{{ $__down }}">
                          <button type="submit" class="btn btn-sm btn-outline-secondary py-0" @disabled(!$__down) title="{{ __('Move later') }}"><i class="fas fa-arrow-right"></i></button>
                        </form>
                        <form method="post" action="{{ route('io.attachments.delete', $att->link_id) }}"
                              onsubmit="return confirm('{{ __('Remove this attached object?') }}');">
                          @csrf @method('DELETE')
                          <button type="submit" class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                        </form>
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

        @if($__canEditAttachments)
          <form method="post" action="{{ route('io.attachments.store', $__io->slug) }}" enctype="multipart/form-data" class="mt-3 pt-3 border-top">
            @csrf
            <div class="row g-2 align-items-end">
              <div class="col-md-5">
                <label class="form-label small mb-1">{{ __('Attach more images / files') }}</label>
                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple required>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('Role (optional)') }}</label>
                <input type="text" name="role" class="form-control form-control-sm" placeholder="{{ __('recto / verso / page...') }}" maxlength="64">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">{{ __('Caption (optional)') }}</label>
                <input type="text" name="caption" class="form-control form-control-sm" maxlength="255">
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-plus"></i></button>
              </div>
            </div>
            <div class="form-text">{{ __('Attaches to this description directly - no child records are created. Each file keeps its own thumbnail/reference.') }}</div>
          </form>
        @endif

      </div>
    </div>

    {{-- #1489 drag-reorder. Strictly an ENHANCEMENT: the arrow buttons above are
         ordinary form posts and remain the working path if this never runs. Uses
         plain DOM + fetch with no library, because the theme bundle does not
         reliably expose a global for anything heavier. --}}
    @once
    <script>
    (function () {
      document.querySelectorAll('[data-attach-sortable]').forEach(function (grid) {
        if (grid.dataset.attachBound) { return; }
        grid.dataset.attachBound = '1';
        var dragged = null;

        grid.addEventListener('dragstart', function (e) {
          dragged = e.target.closest('[data-link-id]');
          if (dragged) { e.dataTransfer.effectAllowed = 'move'; dragged.style.opacity = '0.4'; }
        });
        grid.addEventListener('dragend', function () {
          if (dragged) { dragged.style.opacity = ''; }
          dragged = null;
        });
        grid.addEventListener('dragover', function (e) {
          if (!dragged) { return; }
          e.preventDefault();
          var over = e.target.closest('[data-link-id]');
          if (!over || over === dragged) { return; }
          var rect = over.getBoundingClientRect();
          var after = (e.clientX - rect.left) > rect.width / 2;
          grid.insertBefore(dragged, after ? over.nextSibling : over);
        });
        grid.addEventListener('drop', function (e) {
          e.preventDefault();
          if (!dragged) { return; }
          var order = Array.prototype.map.call(
            grid.querySelectorAll('[data-link-id]'), function (n) { return n.dataset.linkId; }
          );
          var token = document.querySelector('meta[name="csrf-token"]');
          fetch(grid.dataset.reorderUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ order: order })
          }).then(function (r) {
            // A failure must not leave the screen showing an order the server
            // did not accept, so reload rather than keep the optimistic DOM.
            if (!r.ok) { window.location.reload(); }
          }).catch(function () { window.location.reload(); });
        });
      });
    })();
    </script>
    @endonce
  @endif
@endif
