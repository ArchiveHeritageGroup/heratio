{{-- heratio#1514 - a table of locations (children or descendants) --}}
<div class="table-responsive">
  <table class="table table-bordered align-middle mb-0">
    <thead>
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Type') }}</th>
        <th class="text-end">{{ __('Level') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          <td @if($indent ?? false) style="padding-left: {{ 0.5 + 1.25 * max(0, (int) $row->level - (int) $baseLevel) }}rem;" @endif>
            <a href="{{ route('storagelocation.show', ['slug' => $row->slug]) }}">{{ $row->name }}</a>
          </td>
          <td>{{ $types[$row->location_type] ?? $row->location_type }}</td>
          <td class="text-end">{{ (int) $row->level }}</td>
          <td class="text-end text-nowrap">
            <a href="{{ route('storagelocation.edit', ['slug' => $row->slug]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
