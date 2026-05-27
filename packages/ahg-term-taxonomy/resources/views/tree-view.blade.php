{{--
  Heratio - Per-taxonomy tree-view page.

  Renders the top-level terms for a taxonomy as a click-to-expand list.
  Children load asynchronously via the JSON sibling of this route.

  Migrated from PSIS TermTreeViewAction (#743).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  Licensed under AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', ($taxonomyName ?? 'Taxonomy').' - Tree view')
@section('body-class', 'view term tree-view')

@section('content')
  <nav aria-label="breadcrumb" class="small mb-2">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item">
        <a href="{{ route('taxonomy.index') }}">{{ __('Taxonomies') }}</a>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('term.browse', ['taxonomy' => $taxonomyId]) }}">{{ $taxonomyName }}</a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Tree view') }}</li>
    </ol>
  </nav>

  <h1>{{ $taxonomyName }} <small class="text-muted">{{ __('Tree view') }}</small></h1>

  <p class="text-muted">
    {{ __('Click a term to expand its children. The Show page link opens the term in the standard view.') }}
  </p>

  <ul class="list-group" id="term-tree-root"
      data-tree-json-url="{{ route('term.treeView', ['taxonomyId' => $taxonomyId, 'format' => 'json']) }}">
    @foreach($nodes as $n)
      <li class="list-group-item tree-node" data-term-id="{{ $n['id'] }}">
        @if($n['children'])
          <button class="btn btn-sm atom-btn-white tree-expand me-2" type="button"
                  data-loaded="0"
                  aria-expanded="false"
                  aria-label="{{ __('Expand') }}">
            <i class="fas fa-caret-right" aria-hidden="true"></i>
          </button>
        @else
          <i class="fas fa-circle text-muted me-2" style="font-size:.4em;vertical-align:middle;" aria-hidden="true"></i>
        @endif
        <a href="{{ route('term.show', $n['slug']) }}">{{ $n['text'] }}</a>
        <ul class="list-group mt-2 ms-4 tree-children d-none"></ul>
      </li>
    @endforeach
  </ul>

  <script>
    (function () {
      var rootUrl = document.getElementById('term-tree-root').dataset.treeJsonUrl;

      function fetchChildren(termId, container, btn) {
        var url = rootUrl + '&parent=' + encodeURIComponent(termId);
        fetch(url, { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var nodes = (data && data.nodes) || [];
            container.innerHTML = '';
            nodes.forEach(function (n) {
              var li = document.createElement('li');
              li.className = 'list-group-item tree-node';
              li.dataset.termId = n.id;
              var inner = '';
              if (n.children) {
                inner += '<button class="btn btn-sm atom-btn-white tree-expand me-2" type="button" data-loaded="0" aria-expanded="false">'
                       + '<i class="fas fa-caret-right" aria-hidden="true"></i></button>';
              } else {
                inner += '<i class="fas fa-circle text-muted me-2" style="font-size:.4em;vertical-align:middle;" aria-hidden="true"></i>';
              }
              inner += '<a href="/term/' + encodeURIComponent(n.slug) + '">' + (n.text || '') + '</a>';
              inner += '<ul class="list-group mt-2 ms-4 tree-children d-none"></ul>';
              li.innerHTML = inner;
              container.appendChild(li);
            });
            container.classList.remove('d-none');
            btn.setAttribute('aria-expanded', 'true');
            btn.dataset.loaded = '1';
            btn.querySelector('i').classList.remove('fa-caret-right');
            btn.querySelector('i').classList.add('fa-caret-down');
          })
          .catch(function () { /* swallow - leave the row clickable */ });
      }

      document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tree-expand');
        if (!btn) { return; }
        e.preventDefault();
        var li = btn.closest('.tree-node');
        var children = li.querySelector('.tree-children');
        if (btn.dataset.loaded === '0') {
          fetchChildren(li.dataset.termId, children, btn);
          return;
        }
        // Toggle.
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        if (expanded) {
          children.classList.add('d-none');
          btn.setAttribute('aria-expanded', 'false');
          btn.querySelector('i').classList.remove('fa-caret-down');
          btn.querySelector('i').classList.add('fa-caret-right');
        } else {
          children.classList.remove('d-none');
          btn.setAttribute('aria-expanded', 'true');
          btn.querySelector('i').classList.remove('fa-caret-right');
          btn.querySelector('i').classList.add('fa-caret-down');
        }
      });
    })();
  </script>
@endsection
