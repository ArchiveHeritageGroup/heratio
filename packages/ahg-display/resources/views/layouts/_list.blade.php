{{-- List layout - tabular view for libraries/search --}}
<tr class="list-item" data-id="{{ $object->id }}">
    @if($digitalObject && ($data['thumbnail_size'] ?? '') !== 'none')
    <td width="60">
        <img src="{{ $digitalObject->path }}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;" alt="">
    </td>
    @endif

    @foreach($fields['identity'] as $field)
    <td>
        @if($field['code'] === 'title')
        <a href="{{ route('informationobject.show', ['slug' => $object->slug]) }}">
            <strong>{{ $field['value'] }}</strong>
        </a>
        {{-- #763 FRBR cluster expander on the title cell of clustered rows. --}}
        @if (!empty($object->work_key) && !empty($object->cluster_count) && $object->cluster_count > 1)
            <a href="{{ url('/library/work-cluster/' . $object->work_key) }}"
               class="d-block small text-decoration-none mt-1">
                <i class="fas fa-layer-group"></i>
                {{ __('View all :count editions', ['count' => $object->cluster_count]) }}
            </a>
        @endif
        @else
        {!! format_field_value($field) !!}
        @endif
    </td>
    @endforeach

    <td class="text-end">
        @foreach($data['actions'] ?? [] as $action)
            @if($action === 'view')
            <a href="{{ route('informationobject.show', ['slug' => $object->slug]) }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-eye"></i>
            </a>
            @endif
        @endforeach
    </td>
</tr>
