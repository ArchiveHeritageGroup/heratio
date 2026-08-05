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

        @if($__attached->isNotEmpty())
          <div class="row g-3">
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
              <div class="col-6 col-md-4 col-lg-3">
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
                      <form method="post" action="{{ route('io.attachments.delete', $att->link_id) }}" class="mt-1"
                            onsubmit="return confirm('{{ __('Remove this attached object?') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash me-1"></i>{{ __('Remove') }}</button>
                      </form>
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
  @endif
@endif
