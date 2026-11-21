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
      @if($canEdit)
        {{-- heratio#1582: the non-drag way to move (WCAG 2.2 SC 2.5.7). --}}
        <button type="button" class="btn btn-outline-secondary" id="tree-move-selected" aria-haspopup="dialog">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
          {{ __('Move selected...') }}
        </button>
      @endif
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
      {{ __('Drag-and-drop a node onto another to re-parent it, or select a node and use Move selected. Moves are recorded in the audit log.') }}
    @endif
  </p>
@endsection

@push('css')
  <link rel="stylesheet" href="{{ asset('vendor/jstree/3.3.16/themes/default/style.min.css') }}">
@endpush

@push('js')
  <script src="{{ asset('vendor/jquery/3.7.1/jquery.min.js') }}"></script>
  <script src="{{ asset('vendor/jstree/3.3.16/jstree.min.js') }}"></script>
  @if($canEdit)
    @include('ahg-io-manage::partials._tree-move-script')
  @endif
  <script>
  (function () {
    var $tree = jQuery('#io-tree');
    var expandIds = @json($expandIds);
    var currentId = {{ (int) $resource->id }};
    var canEdit = @json($canEdit);

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

    if (canEdit && window.AhgTreeMove) {
      // heratio#1582: the shared move (permission on both parents, confirm
      // before leaving the fonds, undo).
      AhgTreeMove.bindJstree($tree);
      jQuery('#tree-move-selected').on('click', function () {
        var inst = $tree.jstree(true);
        var node = inst.get_node(inst.get_selected()[0]);
        if (!node || !node.data || !node.data.real_id) {
          alert(@json(__('Select a node in the tree first.')));
          return;
        }
        AhgTreeMove.open({ id: node.data.real_id, title: node.text }, function (res) {
          if (res && res.ok !== false) { inst.refresh(); }
        });
      });
    }
  })();
  </script>
@endpush
