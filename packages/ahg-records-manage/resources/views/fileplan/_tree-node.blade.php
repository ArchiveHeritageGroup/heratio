<li class="py-1" style="padding-left: {{ $level * 20 }}px;">
    <div class="d-flex align-items-center">
        @if(!empty($node['children']))
            <a href="#" class="fp-toggle text-decoration-none me-1" data-target="fp-children-{{ $node['id'] }}">
                <i class="fas fa-caret-down"></i>
            </a>
        @else
            <span class="me-1" style="width:14px; display:inline-block;"></span>
        @endif

        <a href="{{ route('records.fileplan.show', $node['id']) }}" class="text-decoration-none d-flex align-items-center flex-grow-1">
            <span class="badge bg-secondary me-2">{{ $node['code'] }}</span>
            <span class="fw-medium">{{ $node['title'] }}</span>

            @php
                $typeBadges = [
                    'plan' => 'bg-primary',
                    'series' => 'bg-info',
                    'sub_series' => 'bg-warning text-dark',
                    'file_group' => 'bg-success',
                    'volume' => 'bg-dark',
                ];
                $badgeClass = $typeBadges[$node['node_type']] ?? 'bg-secondary';
            @endphp
            <span class="badge {{ $badgeClass }} ms-2">{{ str_replace('_', ' ', $node['node_type']) }}</span>

            @if(($node['record_count'] ?? 0) > 0)
                <span class="badge bg-outline-secondary border ms-2">{{ $node['record_count'] }} record(s)</span>
            @endif
        </a>
    </div>

    @if(!empty($node['children']))
        <ul class="list-unstyled mb-0" id="fp-children-{{ $node['id'] }}">
            @foreach($node['children'] as $child)
                @include('ahg-records::fileplan._tree-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
