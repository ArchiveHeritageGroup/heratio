<h4 class="h5 mb-2">{{ __('Results') }}</h4>
<ul class="list-unstyled">
  <li>{{ $results }}</li>
</ul>

@if(\AhgCore\Constants\QubitTerm::ROOT_ID != $resource->parent->id)
  <h4 class="h5 mb-2">{{ __('Broader term') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('term.show', ['slug' => $resource->parent->slug]) }}">{{ $resource->parent->authorized_form_of_name ?? $resource->parent->title ?? '' }}</a></li>
  </ul>
@endif

<h4 class="h5 mb-2">{{ __('No. narrower terms') }}</h4>
<ul class="list-unstyled">
  <li>{{ count($resource->getChildren()) }}</li>
</ul>

@php $relatedTerms = \AhgCore\Models\Relation::getBySubjectOrObjectId($resource->id, ['typeId' => \AhgCore\Constants\QubitTerm::TERM_RELATION_ASSOCIATIVE_ID]); @endphp
@if(count($relatedTerms) > 0)
  <h4 class="h5 mb-2">{{ __('Related terms') }}</h4>
  <ul class="list-unstyled">
    @foreach($relatedTerms as $item)
      @php $opposed = $item->getOpposedObject($resource->id); @endphp
      <li><a href="{{ route('term.show', ['slug' => $opposed->slug]) }}">{{ $opposed->authorized_form_of_name ?? $opposed->title ?? '' }}</a></li>
    @endforeach
  </ul>
@endif
