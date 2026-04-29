{{--
  Tenant switcher dropdown for the navbar.

  Vars: none — reads everything from session + DB.

  Behaviour:
    - hidden when user is not authenticated
    - hidden when user has access to ≤ 1 tenant and is not admin
    - links to a switch endpoint (POST /tenant/switch/{id}) when wired,
      else falls back to a non-functional dropdown showing the
      currently-active tenant
    - admins see a "Manage Tenants" item linking to tenant.index

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Route as RouteFacade;
    use Illuminate\Support\Facades\Schema;

    if (! auth()->check()) return;
    if (! Schema::hasTable('ahg_tenant')) return;

    $userId = auth()->id();
    $isAdmin = (bool) (auth()->user()->is_admin ?? false);

    $tenants = Schema::hasTable('ahg_tenant_user')
        ? DB::table('ahg_tenant as t')
            ->leftJoin('ahg_tenant_user as tu', function ($j) use ($userId) {
                $j->on('tu.tenant_id', '=', 't.id')->where('tu.user_id', '=', $userId);
            })
            ->when(! $isAdmin, fn ($q) => $q->whereNotNull('tu.id'))
            ->select('t.id', 't.name', 't.is_default', 'tu.is_super_user')
            ->orderBy('t.name')->get()
        : collect();

    if ($tenants->count() <= 1 && ! $isAdmin) return;

    $currentId = (int) session('current_tenant_id', 0);
    $viewAll = (bool) session('tenant_view_all', false);
    $current = $tenants->firstWhere('id', $currentId);
    $currentName = $viewAll ? __('All Repositories') : ($current->name ?? __('Select Repository'));

    $switchHref = fn (int $id) => RouteFacade::has('tenant.switch')
        ? route('tenant.switch', ['id' => $id])
        : url('/tenant/switch/' . $id);
    $switchAllHref = RouteFacade::has('tenant.switchAll')
        ? route('tenant.switchAll')
        : url('/tenant/switch/all');
    $manageHref = RouteFacade::has('tenant.index') ? route('tenant.index') : url('/tenant');
    $usersHref = $current ? (RouteFacade::has('tenant.users') ? route('tenant.users', ['tenantId' => $current->id]) : null) : null;
    $brandHref = $current ? (RouteFacade::has('tenant.branding') ? route('tenant.branding', ['tenantId' => $current->id]) : null) : null;

    $nonce = csp_nonce() ?? '';
@endphp
<li class="nav-item dropdown tenant-switcher">
    <a class="nav-link dropdown-toggle" href="#" id="tenantSwitcherDropdown" role="button"
       data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-building me-1"></i>
        <span class="tenant-name">{{ $currentName }}</span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="tenantSwitcherDropdown">
        @if ($isAdmin)
            <li>
                <a class="dropdown-item @if ($viewAll) active @endif" href="{{ $switchAllHref }}">
                    <i class="fas fa-globe me-2"></i> {{ __('All Repositories') }}
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
        @endif

        @foreach ($tenants as $t)
            @php $active = ! $viewAll && $currentId === (int) $t->id; @endphp
            <li>
                <a class="dropdown-item @if ($active) active @endif" href="{{ $switchHref($t->id) }}">
                    @if (! empty($t->is_super_user) && ! $isAdmin)
                        <i class="fas fa-star text-warning me-2" title="{{ __('Super User') }}"></i>
                    @else
                        <i class="fas fa-archive me-2"></i>
                    @endif
                    {{ $t->name ?: __('Repository :id', ['id' => $t->id]) }}
                </a>
            </li>
        @endforeach

        @if ($isAdmin || $usersHref || $brandHref)
            <li><hr class="dropdown-divider"></li>
            @if ($isAdmin)
                <li><a class="dropdown-item" href="{{ $manageHref }}"><i class="fas fa-cog me-2"></i> {{ __('Manage Tenants') }}</a></li>
            @endif
            @if ($current && ! empty($current->is_super_user) && ! $isAdmin)
                @if ($usersHref)
                    <li><a class="dropdown-item" href="{{ $usersHref }}"><i class="fas fa-users me-2"></i> {{ __('Manage Users') }}</a></li>
                @endif
                @if ($brandHref)
                    <li><a class="dropdown-item" href="{{ $brandHref }}"><i class="fas fa-palette me-2"></i> {{ __('Branding') }}</a></li>
                @endif
            @endif
        @endif
    </ul>
</li>

<style @if ($nonce) nonce="{{ $nonce }}" @endif>
.tenant-switcher .dropdown-menu { max-height: 400px; overflow-y: auto; }
.tenant-switcher .dropdown-item.active { background-color: var(--bs-primary, #0d6efd); color: white; }
.tenant-switcher .tenant-name {
    max-width: 150px; overflow: hidden; text-overflow: ellipsis;
    white-space: nowrap; display: inline-block; vertical-align: middle;
}
</style>
