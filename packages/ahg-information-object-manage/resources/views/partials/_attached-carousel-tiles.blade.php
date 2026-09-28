{{--
  #1447 (c) - attached digital objects rendered as imageflow tiles, to be
  dropped INSIDE the child-thumbnail strip's flex container so a description's
  extra images sit in the same carousel as its child thumbnails. Display-only
  (each tile links to the image); editor attach/remove lives in _attached-objects.
  Expects $tiles = AttachedDigitalObjectService::listFor(...) (may be empty).
--}}
@php
  // heratio#1503: an attachment can carry redaction regions, and then a viewer
  // who cannot bypass redaction must open the burnt-in derivative, never the
  // reference copy. Thumbnails stay on the original, as for the primary image.
  $__tileRedactor = app(\AhgInformationObjectManage\Services\RedactionRenderService::class);
  $__tileBypass = auth()->check() && auth()->user()
      && (method_exists(auth()->user(), 'isAdministrator')
          ? auth()->user()->isAdministrator()
          : (bool) (auth()->user()->is_admin ?? false));
  $__tileIoId = null;
  $__tileSlug = null;
  $__tileRedacted = [];
  $__tileFirst = ($tiles ?? collect())->first();
  if (! $__tileBypass && $__tileFirst && \Illuminate\Support\Facades\Schema::hasTable('privacy_visual_redaction')) {
      $__tileIoId = app(\AhgCore\Services\AttachedDigitalObjectService::class)->ownerIoId((int) $__tileFirst->link_id);
      $__tileSlug = $__tileIoId ? \Illuminate\Support\Facades\DB::table('slug')->where('object_id', $__tileIoId)->value('slug') : null;
      $__tileRedacted = $__tileIoId ? $__tileRedactor->redactedMasterIds((int) $__tileIoId) : [];
  }
@endphp
@foreach(($tiles ?? collect()) as $att)
  @php
    $__disp = $att->thumbnail ?: $att->reference ?: $att->master;
    // Skip an attachment whose file is missing on disk (orphan row) - it would
    // render as a broken thumbnail.
    $__present = $__disp && \AhgCore\Services\DigitalObjectService::resolveDiskPath($__disp) !== null;
  @endphp
  @continue(! $__present)
  @php
    $isImg = $att->master && str_starts_with((string) ($att->master->mime_type ?? ''), 'image/');
    $thumb = \AhgCore\Services\DigitalObjectService::getUrl($att->thumbnail ?: $att->reference ?: $att->master);
    $open  = \AhgCore\Services\DigitalObjectService::getUrl($att->reference ?: $att->thumbnail ?: $att->master);
    if ($att->master && in_array((int) $att->master->id, $__tileRedacted, true)) {
        // No slug means no way to name the redacted file: open nothing rather
        // than the unredacted reference.
        $open = $__tileSlug ? route('io.privacy.redacted-asset', [$__tileSlug, (int) $att->master->id]) : '#';
    }
    $label = $att->caption ?: ($att->role ?: ($att->master->name ?? 'file'));
  @endphp
  <a href="{{ $open }}" target="_blank" rel="noopener" class="flex-shrink-0 text-center text-decoration-none" style="width:100px;" title="{{ $label }}">
    @if($isImg && $thumb)
      <img src="{{ $thumb }}" alt="{{ $label }}" class="img-thumbnail" style="width:90px;height:68px;object-fit:cover;">
    @else
      <span class="img-thumbnail d-inline-flex align-items-center justify-content-center text-muted" style="width:90px;height:68px;"><i class="fas fa-file"></i></span>
    @endif
    <small class="d-block text-truncate text-muted mt-1" style="font-size:.7rem;">{{ \Illuminate\Support\Str::limit($label, 18) }}</small>
  </a>
@endforeach
