<h4 class="h5 mb-2">{{ __('Results') }}</h4>
<ul class="list-unstyled">
  <li>@php echo $results; @endphp</li>
</ul>

@if(QubitTerm::ROOT_ID != $resource->parent->id)
  <h4 class="h5 mb-2">{{ __('Broader term') }}</h4>
  <ul class="list-unstyled">
    <li>@php echo link_to(render_title($resource->parent), [$resource->parent, 'module' => 'term']); @endphp</li>
  </ul>
@endforeach

<h4 class="h5 mb-2">{{ __('No. narrower terms') }}</h4>
<ul class="list-unstyled">
  <li>@php echo count($resource->getChildren()); @endphp</li>
</ul>

@if(count(QubitRelation::getBySubjectOrObjectId($resource->id, ['typeId' => QubitTerm::TERM_RELATION_ASSOCIATIVE_ID])) > 0)
  <h4 class="h5 mb-2">{{ __('Related terms') }}</h4>
  <ul class="list-unstyled">
    @foreach(QubitRelation::getBySubjectOrObjectId($resource->id, ['typeId' => QubitTerm::TERM_RELATION_ASSOCIATIVE_ID]) as $item)
      <li>@php echo link_to(render_title($item->getOpposedObject($resource->id)), [$item->getOpposedObject($resource->id), 'module' => 'term']); @endphp</li>
    @endforeach
  </ul>
@endforeach
