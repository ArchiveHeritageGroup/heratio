<h1>{{ __('List users') }}</h1>

<div class="d-inline-block mb-3">
  @php echo get_component('search', 'inlineSearch', [
      'label' => __('Search users'),
      'landmarkLabel' => __('User'),
      'route' => route('user.list'),
  ]); @endphp
</div>

<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if('onlyInactive' != $sf_request->filter)
      @php $options['class'] .= ' active'; @endphp
      @php $options['aria-current'] = 'page'; @endphp
    @endif
    <li class="nav-item">
      @php echo link_to(
          __('Show active only'),
          ['filter' => 'onlyActive']
          + $sf_data->getRaw('sf_request')->getParameterHolder()->getAll(),
          $options
      ); @endphp
    </li>
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if('onlyInactive' == $sf_request->filter)
      @php $options['class'] .= ' active'; @endphp
      @php $options['aria-current'] = 'page'; @endphp
    @endif
    <li class="nav-item">
      @php echo link_to(
          __('Show inactive only'),
          ['filter' => 'onlyInactive']
          + $sf_data->getRaw('sf_request')->getParameterHolder()->getAll(),
          $options
      ); @endphp
    </li>
  </ul>
</nav>

<div class="table-responsive mb-3">
  <table class="table table-bordered mb-0">
    <thead>
      <tr>
        <th>
          {{ __('User name') }}
        </th><th>
          {{ __('Email') }}
        </th><th>
          {{ __('Classification') ?>
        </th><th>
           @php echo __('User groups') }}
        </th>
      </tr>
    </thead>
    <tbody>
      @foreach($users as $item)
        <tr>
          <td>
            <?php echo link_to($item->username, [$item, 'module' => 'user']); @endphp
            @if(!$item->active)
              ({{ __('inactive') }})
            @endif
            @if($sf_user->user === $item)
              ({{ __('you') }})
            @endif
          </td><td>
            @php echo $item->email; @endphp
 		  </td><td>

				 <ul>
              @foreach($item->getAclGroups() as $group)
                <li>@php echo render_title($group); @endphp</li>
              @endforeach
            </ul>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@php echo get_partial('default/pager', ['pager' => $pager]); @endphp

<section class="actions mb-3">
  @php echo link_to(__('Add new'), ['module' => 'user', 'action' => 'add'], ['class' => 'btn atom-btn-outline-light']); @endphp
</section>
