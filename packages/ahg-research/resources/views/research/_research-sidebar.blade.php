{{-- Research Sidebar (alias) - redirects to _sidebar partial --}}
@include('research::research._sidebar', ['sidebarActive' => $sidebarActive ?? '', 'unreadNotifications' => $unreadNotifications ?? 0])
