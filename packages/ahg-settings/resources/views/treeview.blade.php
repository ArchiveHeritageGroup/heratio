@extends('theme::layouts.2col')
@section('title', 'Treeview Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Treeview settings') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.treeview') }}">
      @csrf
      <div class="accordion" id="settingsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral">
              {{ __('General') }}
            </button>
          </h2>
          <div id="collapseGeneral" class="accordion-collapse collapse show" data-bs-parent="#settingsAccordion">
            <div class="accordion-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <select name="settings[treeview_type]" class="form-select">
                    <option value="sidebar" {{ ($settings['treeview_type'] ?? '') == 'sidebar' ? 'selected' : '' }}>{{ __('Sidebar') }}</option>
                    <option value="full" {{ ($settings['treeview_type'] ?? '') == 'full' ? 'selected' : '' }}>{{ __('Full width') }}</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Show browse hierarchy page <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <select name="settings[show_browse_hierarchy_page]" class="form-select">
                    <option value="1" {{ ($settings['show_browse_hierarchy_page'] ?? '') == '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                    <option value="0" {{ ($settings['show_browse_hierarchy_page'] ?? '') == '0' ? 'selected' : '' }}>{{ __('No') }}</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Make full width treeview collapsed on description pages <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <select name="settings[allow_full_width_treeview_collapse]" class="form-select">
                    <option value="1" {{ ($settings['allow_full_width_treeview_collapse'] ?? '') == '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                    <option value="0" {{ ($settings['allow_full_width_treeview_collapse'] ?? '') == '0' ? 'selected' : '' }}>{{ __('No') }}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSidebar">
              {{ __('Sidebar') }}
            </button>
          </h2>
          <div id="collapseSidebar" class="accordion-collapse collapse" data-bs-parent="#settingsAccordion">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Sort (information object) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[sort]" class="form-select">
                  <option value="" {{ ($settings['sort'] ?? '') == '' ? 'selected' : '' }}>{{ __('Manual') }}</option>
                  <option value="title" {{ ($settings['sort'] ?? '') == 'title' ? 'selected' : '' }}>{{ __('Title') }}</option>
                  <option value="identifier" {{ ($settings['sort'] ?? '') == 'identifier' ? 'selected' : '' }}>{{ __('Identifier') }}</option>
                </select>
                <small class="text-muted">{{ __('Sort siblings in the treeview') }}</small>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFullWidth">
              {{ __('Full width') }}
            </button>
          </h2>
          <div id="collapseFullWidth" class="accordion-collapse collapse" data-bs-parent="#settingsAccordion">
            <div class="accordion-body">
              <div class="row">
                <div class="col-md-4 mb-3">
                  <div class="form-check">
                    <input type="hidden" name="settings[treeview_show_identifier]" value="0">
                    <input class="form-check-input" type="checkbox" name="settings[treeview_show_identifier]" value="1" id="tv_id" {{ ($settings['treeview_show_identifier'] ?? '') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="tv_id">Show identifier <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
                <div class="col-md-4 mb-3">
                  <div class="form-check">
                    <input type="hidden" name="settings[treeview_show_level_of_description]" value="0">
                    <input class="form-check-input" type="checkbox" name="settings[treeview_show_level_of_description]" value="1" id="tv_level" {{ ($settings['treeview_show_level_of_description'] ?? '') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="tv_level">Show level of description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
                <div class="col-md-4 mb-3">
                  <div class="form-check">
                    <input type="hidden" name="settings[treeview_show_dates]" value="0">
                    <input class="form-check-input" type="checkbox" name="settings[treeview_show_dates]" value="1" id="tv_dates" {{ ($settings['treeview_show_dates'] ?? '') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="tv_dates">Show dates <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
                <div class="col-md-12 mb-3">
                  <label class="form-label">Items per page <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <input type="number" name="settings[treeview_items_per_page]" class="form-control" value="{{ $settings['treeview_items_per_page'] ?? '50' }}" min="10" max="10000">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
@endsection
