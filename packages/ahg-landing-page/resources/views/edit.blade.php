{{-- Landing Page Editor - migrated from ahgLandingPagePlugin/templates/editSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Edit Landing Page')

@section('content')
<div class="landing-page-builder">
  {{-- Header Toolbar --}}
  <div class="builder-toolbar bg-dark text-white py-2 px-3 d-flex align-items-center justify-content-between sticky-top">
    <div class="d-flex align-items-center gap-3">
      <a href="{{ route('landing-page.list') }}" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <h5 class="mb-0">{{ e($page->name) }}</h5>
      @if ($page->is_default)
        <span class="badge bg-primary">Default</span>
      @endif
      @if (!$page->is_active)
        <span class="badge bg-warning text-dark">Inactive</span>
      @endif
    </div>

    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-outline-light btn-sm" id="btn-preview"
              data-url="{{ route('landing-page.show', $page->slug) }}">
        Preview
      </button>
      <button type="button" class="btn btn-outline-light btn-sm" id="btn-settings"
              data-bs-toggle="offcanvas" data-bs-target="#pageSettingsPanel">
        Settings
      </button>
      <div class="dropdown">
        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button"
                data-bs-toggle="dropdown">
          Versions
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          @if (count($versions) > 0)
            @foreach ($versions as $version)
              <li>
                <a class="dropdown-item version-restore" href="#" data-version-id="{{ $version->id }}">
                  <small class="text-muted">v{{ $version->version_number }}</small>
                  {{ $version->status }}
                  <br>
                  <small>{{ \Carbon\Carbon::parse($version->created_at)->format('M j, Y g:i A') }}</small>
                </a>
              </li>
            @endforeach
          @else
            <li><span class="dropdown-item text-muted">No versions yet</span></li>
          @endif
        </ul>
      </div>
      <button type="button" class="btn btn-info btn-sm" id="btn-save-draft">
        Save Draft
      </button>
      <button type="button" class="btn btn-success btn-sm" id="btn-publish">
        Publish
      </button>
    </div>
  </div>

  <div class="builder-main d-flex">
    {{-- Block Palette (Left Sidebar) --}}
    <div class="builder-palette bg-light border-end" style="width: 280px; min-height: calc(100vh - 56px);">
      <div class="p-3">
        <h6 class="text-uppercase text-muted small mb-3">+ Add Block</h6>

        <div class="block-types" id="block-palette">
          @php
          $categories = [
              'Layout' => ['header_section', 'footer_section', 'row_1_col', 'row_2_col', 'row_3_col', 'divider', 'spacer'],
              'Content' => ['hero_banner', 'text_content', 'image_carousel'],
              'Data' => ['search_box', 'browse_panels', 'recent_items', 'featured_items', 'statistics', 'holdings_list'],
              'Other' => ['quick_links', 'repository_spotlight', 'map_block'],
          ];
          @endphp

          @foreach ($categories as $catName => $catBlocks)
            <div class="block-category mb-2">
              <button class="btn btn-sm btn-outline-secondary w-100 text-start collapsed"
                      type="button" data-bs-toggle="collapse"
                      data-bs-target="#cat-{{ strtolower($catName) }}">
                {{ $catName }} <i class="bi bi-chevron-down float-end"></i>
              </button>
              <div class="collapse {{ $catName === 'Layout' ? 'show' : '' }}"
                   id="cat-{{ strtolower($catName) }}">
                @foreach ($blockTypes as $type)
                  @if (in_array($type->machine_name, $catBlocks))
                    <div class="block-type-item card mt-1 d-flex flex-row align-items-center"
                         draggable="true"
                         data-type-id="{{ $type->id }}"
                         data-machine-name="{{ $type->machine_name }}">
                      <div class="drag-handle bg-secondary bg-opacity-25 px-2 py-2 rounded-start"
                           style="cursor: grab;" title="Drag to canvas">
                        <i class="bi bi-grip-vertical"></i>
                      </div>
                      <div class="card-body py-2 px-2 flex-grow-1" style="cursor: pointer;" title="Click to add">
                        <div class="small fw-medium">{{ $type->label }}</div>
                      </div>
                    </div>
                  @endif
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Canvas (Center) --}}
    <div class="builder-canvas flex-grow-1 bg-white" style="min-height: calc(100vh - 56px); overflow-y: auto;">
      <div class="canvas-header bg-light border-bottom p-2 d-flex align-items-center justify-content-between">
        <span class="small text-muted">
          <i class="bi bi-grid-3x3"></i> Canvas
          <span id="block-count">({{ count($blocks) }} blocks)</span>
        </span>
        <div>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-collapse-all">
            <i class="bi bi-dash"></i>
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-expand-all">
            <i class="bi bi-plus"></i>
          </button>
        </div>
      </div>

      <div class="canvas-body p-4">
        <div id="blocks-container" class="blocks-drop-zone" data-page-id="{{ $page->id }}">
          @if (count($blocks) === 0)
            <div class="empty-canvas text-center py-5" id="empty-message">
              <i class="bi bi-inbox display-1 text-muted"></i>
              <p class="text-muted mt-3">Drag blocks here to start building your page</p>
            </div>
          @else
            @foreach ($blocks as $block)
              @include('ahg-landing-page::_block-card', ['block' => $block])
            @endforeach
          @endif
        </div>
      </div>
    </div>

    {{-- Block Config Panel (Right Sidebar) --}}
    <div class="builder-config bg-light border-start" id="config-panel" style="width: 350px; display: none;">
      <div class="config-header border-bottom p-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0">
          <i class="bi bi-sliders"></i> <span id="config-title">Block Settings</span>
        </h6>
        <button type="button" class="btn-close" id="close-config"></button>
      </div>
      <div class="config-body p-3" id="config-form-container">
        {{-- Dynamic form loaded here --}}
      </div>
    </div>
  </div>

  {{-- Page Settings Offcanvas --}}
  <div class="offcanvas offcanvas-end" tabindex="-1" id="pageSettingsPanel">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title">Page Settings</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
      <form id="page-settings-form">
        @csrf
        <input type="hidden" name="id" value="{{ $page->id }}">

        <div class="mb-3">
          <label class="form-label">Page Name</label>
          <input type="text" name="name" class="form-control"
                 value="{{ e($page->name) }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">URL Slug</label>
          <div class="input-group">
            <span class="input-group-text">/</span>
            <input type="text" name="slug" class="form-control"
                   value="{{ e($page->slug) }}" required>
          </div>
          <div class="form-text">URL: {{ config('app.url') }}/landing/<span id="slug-preview">{{ $page->slug }}</span></div>
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3">{{ e($page->description ?? '') }}</textarea>
        </div>

        <div class="mb-3">
          <div class="form-check form-switch">
            <input type="checkbox" name="is_default" class="form-check-input"
                   id="is_default" {{ $page->is_default ? 'checked' : '' }}>
            <label class="form-check-label" for="is_default">Set as default home page</label>
          </div>
        </div>

        <div class="mb-3">
          <div class="form-check form-switch">
            <input type="checkbox" name="is_active" class="form-check-input"
                   id="is_active" {{ $page->is_active ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active (visible to public)</label>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
      </form>

      <hr class="my-4">

      <div class="text-danger">
        <h6>Danger Zone</h6>
        @if (!$page->is_default)
          <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-page">
            <i class="bi bi-trash"></i> Delete Page
          </button>
        @else
          <p class="small text-muted">Default page cannot be deleted</p>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Block Card Template (for JavaScript) --}}
<template id="block-card-template">
  <div class="block-card card mb-3" data-block-id="">
    <div class="card-header d-flex align-items-center py-2 cursor-grab block-handle">
      <i class="bi bi-grip-vertical text-muted me-2"></i>
      <i class="bi block-icon me-2"></i>
      <span class="block-label flex-grow-1"></span>
      <div class="block-actions">
        <button type="button" class="btn btn-sm btn-link text-muted btn-visibility" title="Toggle visibility">
          <i class="bi bi-eye"></i>
        </button>
        <button type="button" class="btn btn-sm btn-link text-primary btn-edit" title="Edit">
          <i class="bi bi-pencil"></i>
        </button>
        <button type="button" class="btn btn-sm btn-link text-secondary btn-duplicate" title="Duplicate">
          <i class="bi bi-copy"></i>
        </button>
        <button type="button" class="btn btn-sm btn-link text-danger btn-delete" title="Delete">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>
    <div class="card-body block-preview p-3">
      {{-- Block preview content --}}
    </div>
  </div>
</template>

<script>
window.LandingPageBuilder = {
    pageId: {{ $page->id }},
    blocks: @json($blocks->toArray()),
    blockTypes: @json($blockTypes->toArray()),
    csrfToken: '{{ csrf_token() }}',
    urls: {
        addBlock: '{{ route('landing-page.block.add') }}',
        updateBlock: '{{ route('landing-page.block.update', ['blockId' => '__BLOCK_ID__']) }}',
        deleteBlock: '{{ route('landing-page.block.delete', ['blockId' => '__BLOCK_ID__']) }}',
        duplicateBlock: '{{ route('landing-page.block.duplicate', ['blockId' => '__BLOCK_ID__']) }}',
        reorderBlocks: '{{ route('landing-page.blocks.reorder') }}',
        toggleVisibility: '{{ route('landing-page.block.toggleVisibility', ['blockId' => '__BLOCK_ID__']) }}',
        updateSettings: '{{ route('landing-page.updateSettings', $page->id) }}',
        deletePage: '{{ route('landing-page.delete', $page->id) }}',
        listPage: '{{ route('landing-page.list') }}'
    }
};
</script>
@endsection
