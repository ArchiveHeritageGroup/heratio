{{--
  Tree-view page (#742): full-width jstree of the archival hierarchy.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  AGPL v3 - see LICENSE.

  Lock note: standalone page, NEVER reached from show.blade.php's render
  path. Loads jstree from CDN (Bootstrap 5 theme via the shared theme's
  master layout already adds bootstrap-icons, so the bi-* icons we use
  below render without an extra include).
--}}
@extends('theme::layout_1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      <i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>
      {{ __('Tree view') }}
    </h1>
    <span class="small text-muted">
      @if(!empty($resource->title))
        <a href="{{ url('/' . $resource->slug) }}">{{ $resource->title }}</a>
      @elseif(!empty($resource->identifier))
        <a href="{{ url('/' . $resource->slug) }}">{{ $resource->identifier }}</a>
      @else
        <a href="{{ url('/' . $resource->slug) }}">{{ $resource->slug }}</a>
      @endif
    </span>
  </div>
@endsection

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Tree actions') }}">
      <button type="button" class="btn btn-outline-secondary" id="tree-expand-all">
        <i class="bi bi-arrows-expand" aria-hidden="true"></i>
        {{ __('Expand all') }}
      </button>
      <button type="button" class="btn btn-outline-secondary" id="tree-collapse-all">
        <i class="bi bi-arrows-collapse" aria-hidden="true"></i>
        {{ __('Collapse all') }}
      </button>
      <button type="button" class="btn btn-outline-secondary" id="tree-sync">
        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
        {{ __('Sync') }}
      </button>
    </div>
    <a href="{{ url('/' . $resource->slug) }}" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-arrow-return-left" aria-hidden="true"></i>
      {{ __('Back to record') }}
    </a>
  </div>

  <div id="io-tree" class="border rounded p-2" style="min-height: 60vh;"></div>

  <p class="text-muted small mt-2">
    @if($canEdit)
      <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
      {{ __('Drag-and-drop a node onto another to re-parent it. Moves are recorded in the audit log.') }}
    @else
      <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
      {{ __('Sign in to enable drag-and-drop re-parenting.') }}
    @endif
  </p>
@endsection

@push('css')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/themes/default/style.min.css">
@endpush

@push('js')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/jstree.min.js"></script>
  <script>
  (function () {
    var $tree = jQuery('#io-tree');
    var expandIds = @json($expandIds);
    var currentId = {{ (int) $resource->id }};
    var canEdit = @json($canEdit);
    var csrfToken = @json(csrf_token());

    var pluginList = ['types', 'state', 'wholerow'];
    if (canEdit) {
      pluginList.push('dnd');
    }

    $tree.jstree({
      core: {
        check_callback: true,
        data: function (node, cb) {
          var rootId = (node.id === '#') ? '' : (node.data && node.data.real_id ? node.data.real_id : '');
          jQuery.getJSON('{{ url('/informationobject/browse/hierarchyData') }}', { root_id: rootId })
            .done(function (data) { cb.call(this, data); })
            .fail(function () { cb.call(this, []); });
        }
      },
      types: {
        default:        { icon: 'bi bi-folder text-warning' },
        'has-children': { icon: 'bi bi-folder text-warning' },
        leaf:           { icon: 'bi bi-file-earmark-text text-muted' }
      },
      plugins: pluginList
    });

    $tree.on('loaded.jstree', function () {
      // Open the ancestor chain so the current node is visible.
      expandIds.forEach(function (id) {
        var domId = 'io-' + id;
        var node = $tree.jstree(true).get_node(domId);
        if (node) {
          $tree.jstree(true).open_node(domId);
        }
      });
    });

    jQuery('#tree-expand-all').on('click', function () {
      $tree.jstree('open_all');
    });
    jQuery('#tree-collapse-all').on('click', function () {
      $tree.jstree('close_all');
    });
    jQuery('#tree-sync').on('click', function () {
      $tree.jstree(true).refresh();
    });

    if (canEdit) {
      $tree.on('move_node.jstree', function (e, data) {
        var movedRealId = data.node.data && data.node.data.real_id ? data.node.data.real_id : null;
        var newParent = $tree.jstree(true).get_node(data.parent);
        var newParentRealId = (newParent && newParent.data && newParent.data.real_id) ? newParent.data.real_id : null;

        if (!movedRealId || !newParentRealId) {
          return;
        }

        jQuery.ajax({
          url: '{{ url('/informationobject/tree-move') }}',
          method: 'POST',
          dataType: 'json',
          data: {
            _token: csrfToken,
            id: movedRealId,
            new_parent_id: newParentRealId,
            position: data.position
          }
        }).fail(function (xhr) {
          var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Move failed.';
          alert(msg);
          $tree.jstree(true).refresh();
        });
      });
    }
  })();
  </script>
@endpush
