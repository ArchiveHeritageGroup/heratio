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
            <th>Basis</th>
            <th>Start date</th>
            <th>End date</th>
            <th>Rights note</th>
            <th>Copyright status</th>
            <th>Copyright jurisdiction</th>
            <th>Copyright note</th>
            <th>License terms</th>
            <th>License note</th>
            <th>Statute jurisdiction</th>
            <th>Statute note</th>
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
