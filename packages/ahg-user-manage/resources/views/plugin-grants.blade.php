@extends('theme::layouts.1col')

@section('title', __('Plugin Grants') . ' — ' . $target->username)
@section('body-class', 'view admin-plugin-grants')

@section('content')
  <h1>
    {{ __('Plugin grants') }}
    <small class="text-muted">— {{ $target->username }} ({{ $target->email }})</small>
  </h1>
  <p class="text-muted">
    {{ __('Per-user CAPABILITY layer. This grants or denies plugin access for this user. The user themselves can additionally HIDE plugins from their own nav at') }}
    <code>/user/profile/plugins</code>{{ __(' — that is a separate visibility layer.') }}
  </p>
  <ul class="text-muted small">
    <li><strong>Inherit</strong> — follow the global enable/disable on Settings → Plugins (default).</li>
    <li><strong>Allow</strong>  — user has access even if globally disabled (use for beta-testers).</li>
    <li><strong>Deny</strong>   — user is blocked even if globally enabled. Plugin URLs return 403.</li>
  </ul>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('user.plugin-grants.save', $target->username) }}">
    @csrf
    @php
      $byCategory = collect($plugins)->groupBy(fn($p) => $p->category ?: 'general');
    @endphp

    @foreach($byCategory as $category => $items)
      <fieldset class="mb-4 border rounded p-3">
        <legend class="float-none w-auto fs-5 px-2">{{ ucfirst($category) }}</legend>

        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:40%">{{ __('Plugin') }}</th>
              <th style="width:15%">{{ __('Globally') }}</th>
              <th style="width:45%">{{ __('Grant for this user') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $plugin)
              @php $mode = $grants[$plugin->name] ?? 'inherit'; @endphp
              <tr>
                <td>
                  <strong>{{ $plugin->name }}</strong>
                  @if($plugin->description)
                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($plugin->description, 100) }}</div>
                  @endif
                </td>
                <td>
                  @if($plugin->is_enabled)
                    <span class="badge bg-success">{{ __('enabled') }}</span>
                  @else
                    <span class="badge bg-secondary">{{ __('disabled') }}</span>
                  @endif
                </td>
                <td>
                  <div class="btn-group btn-group-sm" role="group">
                    @foreach(['inherit'=>'secondary','allow'=>'success','deny'=>'danger'] as $value=>$style)
                      <input type="radio" class="btn-check"
                             name="grants[{{ $plugin->name }}]"
                             id="grant-{{ $plugin->name }}-{{ $value }}"
                             value="{{ $value }}"
                             {{ $mode === $value ? 'checked' : '' }}>
                      <label class="btn btn-outline-{{ $style }}"
                             for="grant-{{ $plugin->name }}-{{ $value }}">
                        {{ ucfirst($value) }}
                      </label>
                    @endforeach
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </fieldset>
    @endforeach

    <button type="submit" class="btn btn-primary">{{ __('Save plugin grants') }}</button>
    <a href="{{ route('user.show', $target->username) }}" class="btn btn-link">{{ __('Cancel') }}</a>
  </form>
@endsection
