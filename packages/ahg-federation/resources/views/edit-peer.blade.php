@extends('theme::layout')

@section('title', $isNew ? 'Add Federation Peer' : 'Edit Peer')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('federation.peers') }}">Peers</a></li>
                    <li class="breadcrumb-item active">{{ $isNew ? 'Add Peer' : 'Edit Peer' }}</li>
                </ol>
            </nav>
            <h4 class="mb-0">
                <i class="bi bi-{{ $isNew ? 'plus-circle' : 'pencil' }} me-2"></i>
                {{ $isNew ? 'Add Federation Peer' : 'Edit: ' . ($peer->name ?? '') }}
            </h4>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('federation.savePeer') }}" autocomplete="off">
        @csrf
        @if(!$isNew)
            <input type="hidden" name="id" value="{{ $peer->id }}">
        @endif

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>{{ __('Basic Information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <input type="text" class="form-control" id="name" name="name" autocomplete="off"
                                   value="{{ $peer->name ?? old('name', '') }}" required>
                            <div class="form-text">A descriptive name for this peer repository</div>
                        </div>

                        @php
                            $peerType = $peer->peer_type ?? old('peer_type', 'oai_pmh');
                            $peerConfig = [];
                            if (!empty($peer->config)) {
                                $decoded = is_string($peer->config) ? json_decode($peer->config, true) : $peer->config;
                                if (is_array($decoded)) { $peerConfig = $decoded; }
                            }
                        @endphp

                        <div class="mb-3">
                            <label for="peer_type" class="form-label">Peer type <span class="text-danger">*</span></label>
                            <select class="form-select" id="peer_type" name="peer_type" required onchange="ahgFederationTogglePeerType()">
                                <option value="oai_pmh" {{ $peerType === 'oai_pmh' ? 'selected' : '' }}>OAI-PMH repository</option>
                                <option value="sharepoint_graph_search" {{ $peerType === 'sharepoint_graph_search' ? 'selected' : '' }}>SharePoint (Microsoft Graph search)</option>
                            </select>
                            <div class="form-text">{{ __('Determines which connector is used at search time. OAI peers also support background harvest.') }}</div>
                        </div>

                        <div class="mb-3" id="peer-type-block-oai_pmh"
                             style="{{ $peerType !== 'oai_pmh' ? 'display:none' : '' }}">
                            <label for="base_url" class="form-label">OAI-PMH Base URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="base_url" name="base_url"
                                   value="{{ $peer->base_url ?? old('base_url', '') }}"
                                   placeholder="{{ __('https://example.com/oai') }}">
                            <div class="form-text">The OAI-PMH endpoint URL of the peer repository</div>
                        </div>

                        <div id="peer-type-block-sharepoint_graph_search"
                             style="{{ $peerType !== 'sharepoint_graph_search' ? 'display:none' : '' }}">
                            <div class="alert alert-info py-2 small">
                                <i class="bi bi-info-circle me-1"></i>
                                {{ __('SharePoint peers do not harvest; they only contribute hits to federated search via the Microsoft Graph search API. Credentials live in the existing SharePoint tenant pool.') }}
                            </div>
                            <div class="mb-3">
                                <label for="sp_tenant_id" class="form-label">SharePoint tenant <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" id="sp_tenant_id" name="sp_tenant_id"
                                       value="{{ $peerConfig['tenant_id'] ?? '' }}"
                                       placeholder="{{ __('sharepoint_tenant.id (e.g. 1)') }}">
                                <div class="form-text">{{ __('FK to sharepoint_tenant.id — not the AAD tenant GUID. Configure tenants under SharePoint admin.') }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="sp_default_site_ids" class="form-label">{{ __('Default site IDs (optional)') }}</label>
                                <textarea class="form-control font-monospace" id="sp_default_site_ids" name="sp_default_site_ids" rows="2"
                                          placeholder='["contoso.sharepoint.com,abc,def"]'>{{ isset($peerConfig['default_site_ids']) ? json_encode($peerConfig['default_site_ids']) : '' }}</textarea>
                                <div class="form-text">{{ __('JSON array of Graph siteId values. Constrains KQL to these sites.') }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="sp_default_drive_ids" class="form-label">{{ __('Default drive IDs (optional)') }}</label>
                                <textarea class="form-control font-monospace" id="sp_default_drive_ids" name="sp_default_drive_ids" rows="2"
                                          placeholder='["b!abc123…"]'>{{ isset($peerConfig['default_drive_ids']) ? json_encode($peerConfig['default_drive_ids']) : '' }}</textarea>
                                <div class="form-text">{{ __('JSON array of Graph driveId values. Constrains KQL to these drives.') }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="sp_max_results_per_query" class="form-label">{{ __('Max results per query') }}</label>
                                <input type="number" min="1" max="50" class="form-control" id="sp_max_results_per_query" name="sp_max_results_per_query"
                                       value="{{ $peerConfig['max_results_per_query'] ?? 50 }}">
                                <div class="form-text">{{ __('Hard cap (Graph allows up to 50 per request).') }}</div>
                            </div>
                        </div>

                        <script>
                            function ahgFederationTogglePeerType() {
                                var sel = document.getElementById('peer_type');
                                var t = sel ? sel.value : 'oai_pmh';
                                var oaiBlock     = document.getElementById('peer-type-block-oai_pmh');
                                var spBlock      = document.getElementById('peer-type-block-sharepoint_graph_search');
                                var prefixRow    = document.getElementById('oai-metadata-prefix-row');
                                var baseUrl      = document.getElementById('base_url');
                                var spTenantId   = document.getElementById('sp_tenant_id');
                                var showOai = (t === 'oai_pmh');
                                var showSp  = (t === 'sharepoint_graph_search');
                                if (oaiBlock)   oaiBlock.style.display   = showOai ? '' : 'none';
                                if (spBlock)    spBlock.style.display    = showSp  ? '' : 'none';
                                if (prefixRow)  prefixRow.style.display  = showOai ? '' : 'none';
                                if (baseUrl)    baseUrl.required         = showOai;
                                if (spTenantId) spTenantId.required      = showSp;
                            }
                            document.addEventListener('DOMContentLoaded', ahgFederationTogglePeerType);
                        </script>

                        {{-- The legacy stand-alone base_url block has been merged into peer-type-block-oai_pmh above. --}}

                        <div class="mb-3" id="oai-metadata-prefix-row"
                             style="{{ $peerType !== 'oai_pmh' ? 'display:none' : '' }}">
                            <label for="metadata_prefix" class="form-label">Metadata Prefix <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select class="form-select" id="metadata_prefix" name="metadata_prefix">
                                <option value="oai_dc" {{ ($peer->metadata_prefix ?? '') === 'oai_dc' ? 'selected' : '' }}>oai_dc (Dublin Core)</option>
                                <option value="oai_ead" {{ ($peer->metadata_prefix ?? '') === 'oai_ead' ? 'selected' : '' }}>oai_ead (EAD)</option>
                                <option value="oai_eac" {{ ($peer->metadata_prefix ?? '') === 'oai_eac' ? 'selected' : '' }}>oai_eac (EAC-CPF)</option>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   {{ ($peer->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>{{ __('Harvest Settings') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="set_spec" class="form-label">Set Spec (optional) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <input type="text" class="form-control" id="set_spec" name="set_spec"
                                   value="{{ $peer->set_spec ?? old('set_spec', '') }}">
                            <div class="form-text">Restrict harvesting to a specific OAI set</div>
                        </div>

                        <div class="mb-3">
                            <label for="harvest_interval" class="form-label">Harvest Interval (hours) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <input type="number" class="form-control" id="harvest_interval" name="harvest_interval"
                                   value="{{ $peer->harvest_interval ?? old('harvest_interval', 24) }}" min="1">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-save me-2"></i>{{ __('Actions') }}</h6>
                    </div>
                    <div class="card-body">
                        <button type="submit" class="atom-btn-white w-100 mb-2">
                            <i class="bi bi-check-lg me-1"></i>{{ $isNew ? 'Create Peer' : 'Save Changes' }}
                        </button>
                        <a href="{{ route('federation.peers') }}" class="atom-btn-white w-100">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
