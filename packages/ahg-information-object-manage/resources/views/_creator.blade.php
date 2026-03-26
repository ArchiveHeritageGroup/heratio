<div class="field">
  <h3>{{ __('Creator(s)') }}</h3>
  <div>
    <ul>
      @foreach($ancestor->getCreators() as $item)
        <li>
          @if(0 < count($resource->getCreators()))
            @php echo link_to(render_title($item), [$item]); @endphp
          @else
            @php echo link_to(render_title($item), [$item], ['title' => __('Inherited from %1%', ['%1%' => $ancestor])]); @endphp
          @endif
        </li>
      @endforeach
    </ul>
  </div>
</div>
