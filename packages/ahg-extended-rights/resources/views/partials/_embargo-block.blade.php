{{-- Embargo block overlay for digital objects --}}
{{-- Usage: @include('ahg-extended-rights::partials._embargo-block', ['embargo' => $embargo, 'objectTitle' => $title]) --}}
@if(!empty($embargo))
<div class="embargo-block text-center p-4 bg-light border rounded">
  <i class="fas fa-lock fa-3x text-danger mb-3"></i>
  <h5>Content Under Embargo</h5>
  <p class="text-muted">
    @if($embargo->embargo_type === 'full')
      This record and all associated content is embargoed.
    @elseif($embargo->embargo_type === 'digital_only')
      The digital object for this record is embargoed. Metadata is available.
    @elseif($embargo->embargo_type === 'metadata_only')
      Only the digital object is available. Metadata is restricted.
    @else
      Some content for this record is restricted.
    @endif
  </p>
  @if(!empty($embargo->end_date))
    <p><small>Available from: <strong>{{ $embargo->end_date }}</strong></small></p>
  @endif
  @if(!empty($embargo->public_message))
    <p class="text-muted"><small>{{ e($embargo->public_message) }}</small></p>
  @endif
</div>
@endif
