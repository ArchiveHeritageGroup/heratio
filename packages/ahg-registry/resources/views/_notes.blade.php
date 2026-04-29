{{--
  Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_notes.php

  Universal notes partial.
  Vars:
    $entityType (string)  e.g. 'standard'|'vendor'|'erd'|'software'|'institution'|'group'
    $entityId   (int)
    $returnUrl  (string, optional)
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $_entityType = $entityType ?? '';
    $_entityId = (int) ($entityId ?? 0);
    $_returnUrl = $returnUrl ?? '/registry';

    $_isLoggedIn = auth()->check();
    $_isAdmin = $_isLoggedIn && (bool) (auth()->user()->is_admin ?? false);

    // Current registry user id (atom_user_id → registry_user.id) for ownership checks.
    $_regUserId = null;
    if ($_isLoggedIn && Schema::hasTable('registry_user')) {
        try {
            $_regUserId = DB::table('registry_user')
                ->where('atom_user_id', auth()->id())
                ->value('id');
        } catch (\Throwable $e) {}
    }

    $_notes = collect();
    if (Schema::hasTable('registry_note')) {
        try {
            $_notes = DB::table('registry_note')
                ->where('entity_type', $_entityType)
                ->where('entity_id', $_entityId)
                ->where('is_active', 1)
                ->orderByDesc('is_pinned')
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable $e) {}
    }
    $_noteCount = $_notes->count();

    $saveHref = \Illuminate\Support\Facades\Route::has('registry.noteSave')
        ? route('registry.noteSave')
        : url('/registry/note/save');
@endphp

<div class="card mb-4" id="notes">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>{{ __('Notes') }}
      @if ($_noteCount > 0)
        <span class="badge bg-secondary ms-1">{{ $_noteCount }}</span>
      @endif
    </h5>
  </div>

  @if ($_notes->isNotEmpty())
  <div class="list-group list-group-flush">
    @foreach ($_notes as $_note)
    @php
      $pinHref = \Illuminate\Support\Facades\Route::has('registry.notePin')
        ? route('registry.notePin', ['id' => $_note->id]) : url('/registry/note/' . $_note->id . '/pin');
      $delHref = \Illuminate\Support\Facades\Route::has('registry.noteDelete')
        ? route('registry.noteDelete', ['id' => $_note->id]) : url('/registry/note/' . $_note->id . '/delete');
    @endphp
    <div class="list-group-item @if ($_note->is_pinned) bg-light @endif">
      <div class="d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
          @if ($_note->is_pinned)
            <span class="badge bg-warning text-dark me-1"><i class="fas fa-thumbtack"></i></span>
          @endif
          <strong class="small">{{ $_note->user_name }}</strong>
          <span class="text-muted small ms-2">{{ date('M j, Y H:i', strtotime($_note->created_at)) }}</span>
        </div>
        @if ($_isAdmin || ($_regUserId && (int) $_regUserId === (int) $_note->user_id))
        <div class="d-flex gap-1">
          @if ($_isAdmin)
          <form method="post" action="{{ $pinHref }}" class="d-inline">
            @csrf
            <input type="hidden" name="return_url" value="{{ $_returnUrl }}#notes">
            <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-1" title="{{ $_note->is_pinned ? __('Unpin') : __('Pin') }}">
              <i class="fas fa-thumbtack"></i>
            </button>
          </form>
          @endif
          <form method="post" action="{{ $delHref }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this note?') }}');">
            @csrf
            <input type="hidden" name="return_url" value="{{ $_returnUrl }}#notes">
            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="{{ __('Delete') }}">
              <i class="fas fa-trash-alt"></i>
            </button>
          </form>
        </div>
        @endif
      </div>
      <div class="mt-1 small">{!! nl2br(e($_note->content)) !!}</div>
    </div>
    @endforeach
  </div>
  @endif

  @if ($_isLoggedIn)
  <div class="card-body border-top">
    <form method="post" action="{{ $saveHref }}">
      @csrf
      <input type="hidden" name="entity_type" value="{{ $_entityType }}">
      <input type="hidden" name="entity_id" value="{{ $_entityId }}">
      <input type="hidden" name="return_url" value="{{ $_returnUrl }}#notes">
      <div class="mb-2">
        <textarea class="form-control form-control-sm" name="note_content" rows="2" placeholder="{{ __('Add a note...') }}" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-paper-plane me-1"></i>{{ __('Add Note') }}
      </button>
    </form>
  </div>
  @else
  <div class="card-body border-top">
    <p class="text-muted small mb-0">
      <a href="/registry/login">{{ __('Log in') }}</a> {{ __('to add notes.') }}
    </p>
  </div>
  @endif
</div>
