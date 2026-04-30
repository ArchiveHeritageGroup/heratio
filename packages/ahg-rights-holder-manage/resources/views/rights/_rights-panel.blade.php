{{-- Rights Panel Partial - displays PREMIS rights for an information object --}}
@if(isset($rights) && $rights->isNotEmpty())
<section class="section border-bottom" id="rightsPanel">
  <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    Rights area
  </h2>
  <div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm mb-0">
        <thead>
          <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <th>{{ __('Basis') }}</th>
            <th>{{ __('Start date') }}</th>
            <th>{{ __('End date') }}</th>
            <th>{{ __('Rights note') }}</th>
            <th>{{ __('Copyright status') }}</th>
            <th>{{ __('Copyright jurisdiction') }}</th>
            <th>{{ __('Copyright note') }}</th>
            <th>{{ __('License terms') }}</th>
            <th>{{ __('License note') }}</th>
            <th>{{ __('Statute jurisdiction') }}</th>
            <th>{{ __('Statute note') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rights as $right)
            <tr>
              <td>{{ $basisNames[$right->basis_id] ?? '' }}</td>
              <td>{{ $right->start_date }}</td>
              <td>{{ $right->end_date }}</td>
              <td>{{ $right->rights_note }}</td>
              <td>{{ $right->copyright_status_id ? ($basisNames[$right->copyright_status_id] ?? $right->copyright_status_id) : '' }}</td>
              <td>{{ $right->copyright_jurisdiction }}</td>
              <td>{{ $right->copyright_note }}</td>
              <td>{{ $right->license_terms }}</td>
              <td>{{ $right->license_note }}</td>
              <td>{{ $right->statute_jurisdiction }}</td>
              <td>{{ $right->statute_note }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</section>
@endif
