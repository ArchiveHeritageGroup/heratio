@extends('theme::layouts.1col')
@section('title', __('SharePoint integration'))
@section('content')
<h1>{{ __('SharePoint integration') }}</h1>
<p class="lead text-muted">{{ __('Connect Microsoft 365 / SharePoint document libraries and ingest their content into Heratio.') }}</p>

<div class="row g-3 mb-4">
    @php $cards = [
        ['label' => __('Tenants'),       'value' => $stats['tenants'],       'route' => 'sharepoint.tenants',       'icon' => 'fa-building'],
        ['label' => __('Drives'),        'value' => $stats['drives'],        'route' => 'sharepoint.drives',        'icon' => 'fa-hard-drive'],
        ['label' => __('Ingest-enabled'),'value' => $stats['drivesEnabled'], 'route' => 'sharepoint.drives',        'icon' => 'fa-toggle-on'],
        ['label' => __('Subscriptions'), 'value' => $stats['subscriptions'], 'route' => 'sharepoint.subscriptions', 'icon' => 'fa-bell'],
        ['label' => __('Events'),        'value' => $stats['events'],        'route' => 'sharepoint.events',        'icon' => 'fa-list'],
    ]; @endphp
    @foreach ($cards as $c)
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route($c['route']) }}" class="text-decoration-none">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas {{ $c['icon'] }} fa-lg text-primary mb-2"></i>
                        <div class="h3 mb-0">{{ number_format($c['value']) }}</div>
                        <div class="small text-muted">{{ $c['label'] }}</div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="d-flex gap-2 flex-wrap">
    <a href="{{ route('sharepoint.tenants') }}" class="btn btn-outline-primary"><i class="fas fa-building me-1"></i>{{ __('Tenants') }}</a>
    <a href="{{ route('sharepoint.drives') }}" class="btn btn-outline-primary"><i class="fas fa-hard-drive me-1"></i>{{ __('Drives') }}</a>
    <a href="{{ route('sharepoint.rules') }}" class="btn btn-outline-primary"><i class="fas fa-sliders me-1"></i>{{ __('Ingest rules') }}</a>
    <a href="{{ route('sharepoint.subscriptions') }}" class="btn btn-outline-primary"><i class="fas fa-bell me-1"></i>{{ __('Subscriptions') }}</a>
    <a href="{{ route('sharepoint.events') }}" class="btn btn-outline-primary"><i class="fas fa-list me-1"></i>{{ __('Events') }}</a>
    <a href="{{ route('sharepoint.user-mappings') }}" class="btn btn-outline-primary"><i class="fas fa-users me-1"></i>{{ __('User mappings') }}</a>
</div>
@endsection
