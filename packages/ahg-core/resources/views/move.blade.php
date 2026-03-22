@php decorate_with('layout_1col'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Move %1%', ['%1%' => render_title($resource)]) }}</h1>
@php end_slot(); @endphp

@php slot('before-content'); @endphp
  <div class="d-inline-block mb-3">
    @php echo get_component('search', 'inlineSearch', [
        'label' => __('Search title or identifier'),
        'landmarkLabel' => sfConfig::get('app_ui_label_informationobject'),
        'route' => url_for([$resource, 'module' => 'default', 'action' => 'move']),
    ]); @endphp
  </div>

  @if(0 < count($parent->ancestors))
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        @foreach($parent->ancestors as $item)
          @if(isset($item->parent))
            <li class="breadcrumb-item">@php echo link_to(render_title($item), [$resource, 'module' => 'default', 'action' => 'move', 'parent' => $item->slug]); @endphp</li>
          @endforeach
        @endforeach
        @if(isset($parent->parent))
          <li class="breadcrumb-item active" aria-current="page">@php echo render_title($parent); @endphp</li>
        @endforeach
      </ol>
    </nav>
  @endforeach
@php end_slot(); @endphp

@php slot('content'); @endphp
  @if(count($results))
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Identifier') }}</th>
            <th>{{ __('Title') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($results as $item)
            <tr>
              <td width="15%">
                @php echo render_value_inline($item->identifier); @endphp
              </td>
              <td width="85%">
                @php echo link_to_if($resource->lft > $item->lft || $resource->rgt < $item->rgt, render_title($item), [$resource, 'module' => 'default', 'action' => 'move', 'parent' => $item->slug]); @endphp
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endforeach
@php end_slot(); @endphp

@php slot('after-content'); @endphp
  @php echo get_partial('default/pager', ['pager' => $pager]); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'default', 'action' => 'move'])); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <ul class="actions mb-3 nav gap-2">
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Move here') }}"></li>
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
    </ul>

  </form>
@php end_slot(); @endphp
