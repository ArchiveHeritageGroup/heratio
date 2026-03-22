@if('QubitRepository' !== $class)
  @php include_component('repository', 'logo'); @endphp
@php } else { @endphp

  @if(sfConfig::get('app_enable_institutional_scoping'))
    @php include_component('repository', 'holdingsInstitution', ['resource' => $resource]); @endphp
    @php include_component('repository', 'holdingsList', ['resource' => $resource]); @endphp
    @php include_component('repository', 'uploadLimit', ['resource' => $resource]); @endphp
  @php } else { @endphp
    @php include_component('repository', 'logo'); @endphp
    @php include_component('repository', 'uploadLimit', ['resource' => $resource]); @endphp
    @php include_component('repository', 'holdings', ['resource' => $resource]); @endphp
    @php include_component('repository', 'holdingsList', ['resource' => $resource]); @endphp
  @endforeach

@endforeach
