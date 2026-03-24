{{-- External auth user list variant - ported from AtoM ahgThemeB5Plugin/modules/user/templates/listSuccess.mod_ext_auth.php --}}
{{-- No "Add new" button since external auth manages user creation externally --}}

<h1>{{ __('List users') }}</h1>

<div class="d-inline-block mb-3">
  <form action="{{ route('user.browse') }}" method="GET" class="d-flex gap-2">
    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search users') }}" aria-label="{{ __('Search users') }}">
    <button type="submit" class="btn btn-sm atom-btn-white">
      <i class="fas fa-search"></i>
    </button>
  </form>
</div>

<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    <li class="nav-item">
      <a href="{{ route('user.browse', array_merge(request()->except('filter'), ['filter' => 'onlyActive'])) }}"
         class="btn atom-btn-white active-primary text-wrap {{ request('filter', 'onlyActive') !== 'onlyInactive' ? 'active' : '' }}"
         @if(request('filter', 'onlyActive') !== 'onlyInactive') aria-current="page" @endif>
        {{ __('Show active only') }}
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('user.browse', array_merge(request()->except('filter'), ['filter' => 'onlyInactive'])) }}"
         class="btn atom-btn-white active-primary text-wrap {{ request('filter') === 'onlyInactive' ? 'active' : '' }}"
         @if(request('filter') === 'onlyInactive') aria-current="page" @endif>
        {{ __('Show inactive only') }}
      </a>
    </li>
  </ul>
</nav>

<div class="table-responsive mb-3">
  <table class="table table-bordered mb-0">
    <thead>
      <tr>
        <th>{{ __('User name') }}</th>
        <th>{{ __('Email') }}</th>
        <th>{{ __('User groups') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($users as $item)
        <tr>
          <td>
            <a href="{{ route('user.show', ['slug' => $item->slug]) }}">{{ $item->username }}</a>
            @if(!$item->active)
              ({{ __('inactive') }})
            @endif
            @if(auth()->check() && auth()->user()->id === $item->id)
              ({{ __('you') }})
            @endif
          </td>
          <td>{{ $item->email }}</td>
          <td>
            @if(!empty($item->groups))
              <ul class="mb-0">
                @foreach($item->groups as $group)
                  <li>{{ $group->name }}</li>
                @endforeach
              </ul>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="3" class="text-muted text-center">{{ __('No users found.') }}</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if(is_object($users) && method_exists($users, 'links'))
  {{ $users->links() }}
@endif
