<div class="field">
  <h3>{{ __('Creator(s)') }}</h3>
  <div>
    <ul>
      @foreach($ancestor->getCreators() as $item)
        <li>
          @if(0 < count($resource->getCreators()))
            @php echo link_to(render_title($item), [$item]); @endphp
          @php } else { @endphp
            @php echo link_to(render_title($item), [$item], ['title' => __('Inherited from %1%', ['%1%' => $ancestor])]); @endphp
          @endforeach
        </li>
      @endforeach
    </ul>
  </div>
</div>
