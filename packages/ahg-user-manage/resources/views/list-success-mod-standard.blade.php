<h1>{{ __('List users') }}</h1>

<div class="d-inline-block mb-3">
  @include('ahg-search::_inline-search', [
      'label' => __('Search users'),
      'landmarkLabel' => __('User'),
      'route' => route('user.list'),
  ])
</div>

<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if('onlyInactive' != request('filter'))
      @php $options['class'] .= ' active'; @endphp
    @endif
    <li class="nav-item">
      <a class="{{ $options['class'] }}" href="{{ request()->fullUrlWithQuery(['filter' => 'onlyActive']) }}"
         {{ ('onlyInactive' != request('filter')) ? 'aria-current=page' : '' }}>
        {{ __('Show active only') }}
      </a>
    </li>
    @php $options = ['class' => 'btn atom-btn-white active-primary text-wrap']; @endphp
    @if('onlyInactive' == request('filter'))
      @php $options['class'] .= ' active'; @endphp
    @endif
    <li class="nav-item">
      <a class="{{ $options['class'] }}" href="{{ request()->fullUrlWithQuery(['filter' => 'onlyInactive']) }}"
         {{ ('onlyInactive' == request('filter')) ? 'aria-current=page' : '' }}>
        {{ __('Show inactive only') }}
      </a>
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
          {{ __('Classification') }}
        </th><th>
          {{ __('User groups') }}
        </th>
      </tr>
    </thead>
    <tbody>
      @foreach($users as $item)
        <tr>
          <td>
            <a href="{{ route('user.show', ['slug' => $item->slug]) }}">{{ $item->username }}</a>
            @if(!$item->active)
              ({{ __('inactive') }})
            @endif
            @if(Auth::check() && Auth::id() === $item->id)
              ({{ __('you') }})
            @endif
          </td><td>
            {{ $item->email }}
          </td><td>

            <ul>
              @foreach($item->getAclGroups() as $group)
                <li>{{ $group->authorized_form_of_name ?? $group->title ?? '' }}</li>
              @endforeach
            </ul>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@include('ahg-core::_pager', ['pager' => $pager])

<section class="actions mb-3">
  <a href="{{ route('user.add') }}" class="btn atom-btn-outline-light">{{ __('Add new') }}</a>
</section>
