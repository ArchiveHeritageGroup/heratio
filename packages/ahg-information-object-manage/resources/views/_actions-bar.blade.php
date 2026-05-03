{{--
  IO show-page action bar.

  Extracted so other surfaces (currently the SBS translate modal) can mirror
  the same actions without forcing the user to close the modal first.

  Renders nothing for guest users.

  Required in scope (auto-inherited by @include from show.blade.php):
    $io               object   information_object row (id, slug, ...)

  Optional in scope (rendered conditionally):
    $digitalObjects   array    ['master' => $do]    (drives Edit vs Link DO row)
    $hasChildren      bool                          (drives Manage rights inheritance row)
    $auditLogEnabled  bool                          (drives Modification history row)

  ACL gating: read directly via AclService::check($io, ...). Caller does not
  need to precompute $canUpdate / $canDelete / $canCreate.
--}}
@auth
@php
  $_canUpdate = \AhgCore\Services\AclService::check($io, 'update');
  $_canDelete = \AhgCore\Services\AclService::check($io, 'delete');
  $_canCreate = \AhgCore\Services\AclService::check($io, 'create');
  $_digitalObjects = $digitalObjects ?? null;
  $_hasChildren = $hasChildren ?? false;
  $_auditLogEnabled = $auditLogEnabled ?? false;
@endphp
<ul class="actions mb-3 nav gap-2">
  @if($_canUpdate)
  <li>
    <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn atom-btn-outline-light">{{ __('Edit') }}</a>
  </li>
  @endif
  @if($_canDelete)
  <li>
    <form action="{{ route('informationobject.destroy', $io->slug) }}" method="POST"
          onsubmit="return confirm('{{ __('Are you sure you want to delete this archival description?') }}');">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn atom-btn-outline-danger">{{ __('Delete') }}</button>
    </form>
  </li>
  @endif
  @if($_canCreate)
  <li>
    <a href="{{ route('informationobject.create', ['parent_id' => $io->id]) }}" class="btn atom-btn-outline-light">{{ __('Add new') }}</a>
  </li>
  <li>
    <a href="{{ route('informationobject.create', ['parent_id' => $io->id, 'copy_from' => $io->id]) }}" class="btn atom-btn-outline-light">{{ __('Duplicate') }}</a>
  </li>
  @endif
  @if($_canUpdate)
  <li>
    <a href="{{ url('/' . $io->slug . '/default/move') }}" class="btn atom-btn-outline-light">{{ __('Move') }}</a>
  </li>
  @endif
  {{-- Clipboard add/remove toggle. Theme bundle JS swaps title/icon state. --}}
  <li>
    <button type="button"
            class="btn atom-btn-outline-light clipboard"
            data-clipboard-slug="{{ $io->slug }}"
            data-clipboard-type="informationObject"
            data-title="{{ __('Add to clipboard') }}"
            data-alt-title="{{ __('Remove from clipboard') }}"
            title="{{ __('Add to clipboard') }}">
      <i class="fas fa-paperclip me-1"></i>{{ __('Add to clipboard') }}
    </button>
  </li>
  @if($_canUpdate)
  <li>
    <div class="dropup">
      <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        {{ __('More') }}
      </button>
      <ul class="dropdown-menu mb-2">
        <li>
          <a class="dropdown-item" href="{{ route('informationobject.rename', $io->slug) }}">
            <i class="fas fa-i-cursor me-2"></i>{{ __('Rename') }}
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item" href="{{ route('informationobject.edit', ['slug' => $io->slug, 'storage' => 1]) }}">
            <i class="fas fa-box me-2"></i>{{ __('Link physical storage') }}
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        @if($_digitalObjects && ($_digitalObjects['master'] ?? null))
          <li>
            <a class="dropdown-item" href="{{ route('io.digitalobject.show', $_digitalObjects['master']->id) }}">
              <i class="fas fa-photo-video me-2"></i>{{ __('Edit digital object') }}
            </a>
          </li>
        @else
          <li>
            <a class="dropdown-item" href="{{ route('io.digitalobject.add', $io->slug) }}">
              <i class="fas fa-link me-2"></i>{{ __('Link digital object') }}
            </a>
          </li>
        @endif
        <li>
          <a class="dropdown-item" href="{{ route('io.multiFileUpload', $io->slug) }}">
            <i class="fas fa-file-import me-2"></i>{{ __('Import digital objects') }}
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="{{ url('/' . $io->slug . '/right/edit') }}">
            <i class="fas fa-balance-scale me-2"></i>{{ __('Create new rights') }}
          </a>
        </li>
        @if($_hasChildren)
          <li>
            <a class="dropdown-item" href="{{ url('/' . $io->slug . '/right/manage') }}">
              <i class="fas fa-sitemap me-2"></i>{{ __('Manage rights inheritance') }}
            </a>
          </li>
        @endif
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item" href="{{ route('io.showUpdateStatus', $io->slug ?? '') }}">
            <i class="fas fa-eye me-2"></i>{{ __('Update publication status') }}
          </a>
        </li>
        @if($_auditLogEnabled)
        <li>
          <a class="dropdown-item" href="{{ route('audit.browse', ['type' => 'QubitInformationObject', 'id' => $io->id ?? '']) }}">
            <i class="fas fa-history me-2"></i>{{ __('Modification history') }}
          </a>
        </li>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('ahgtranslation.translate')
            && \AhgCore\Services\AclService::check($io, 'translate'))
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateSbsModal-{{ $io->id }}">
              <i class="fas fa-language me-2"></i>{{ __('Translate (labels — side-by-side)') }}
            </a>
          </li>
          @if(\Illuminate\Support\Facades\Schema::hasTable('museum_metadata') && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $io->id)->exists())
            <li>
              <a class="dropdown-item text-warning" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateCcoValuesModal-{{ $io->id }}">
                <i class="fas fa-landmark me-2"></i>{{ __('Translate field data values (CCO)') }}
              </a>
            </li>
          @endif
        @endif
      </ul>
    </div>
  </li>
  @endif {{-- end $_canUpdate More --}}
  <li>
    <a href="{{ route('informationobject.print', $io->slug) }}" class="btn atom-btn-outline-light" target="_blank">
      <i class="fas fa-print me-1"></i>{{ __('Print') }}
    </a>
  </li>
</ul>
@endauth
