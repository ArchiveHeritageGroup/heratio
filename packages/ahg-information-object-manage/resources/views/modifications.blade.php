@php decorate_with('layout_1col'); @endphp
@php use_helper('Date'); @endphp

@php slot('title'); @endphp
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Modifications') }}
    </h1>
    <span class="small" id="heading-label">
      @php echo render_title($resource); @endphp
    </span>
  </div>
@php end_slot(); @endphp

@php slot('content'); @endphp
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>
            {{ __('Date') }}
          </th>
          <th>
            {{ __('Type') }}
          </th>
          <th>
            {{ __('User') }}
          </th>
        </tr>
      </thead>
      <tbody>
        @foreach($modifications as $modification)
          <tr>
            <td>
              @php echo format_date($modification->createdAt, 'f'); @endphp
            </td>
            <td>
              @php echo QubitTerm::getById($modification->actionTypeId)->getName(['cultureFallback' => true]); @endphp
            </td>
            <td>
              @php echo link_to_if($sf_user->isAdministrator() && $modification->userId, $modification->userName, [QubitUser::getById($modification->userId), 'module' => 'user']); @endphp
            </td>
          </tr>
        @endforeach
      <tbody>
    </table>
  </div>
@php end_slot(); @endphp

@php slot('after-content'); @endphp
  @php echo get_partial('default/pager', ['pager' => $pager]); @endphp
@php end_slot(); @endphp
