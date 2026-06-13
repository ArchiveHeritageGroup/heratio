{{-- Research mode guide: inline, wizard-style overview of the three modes.
     Not a modal and not a click-through wizard - all three modes are shown
     side by side as step-cards, with the researcher's current mode highlighted.
     $experienceLevel is supplied by ResearchController::getSidebarData(). --}}
@php
    $expLevel = $experienceLevel ?? 'intermediate';
    $modes = [
        'beginning' => [
            'n' => 1,
            'label' => __('Beginning'),
            'icon' => 'fa-seedling',
            'who' => __('New or occasional researchers who want to create a simple project and collect a few key items quickly.'),
            'steps' => [
                __('Create a Project (title + brief abstract)'),
                __('Import 3-5 references into Bibliography'),
                __('Upload 1-2 sources and save to a Collection'),
                __('Make notes in a Notebook entry'),
                __('Export a bibliography for your draft'),
            ],
        ],
        'intermediate' => [
            'n' => 2,
            'label' => __('Intermediate'),
            'icon' => 'fa-diagram-project',
            'who' => __('Researchers running ongoing projects that need structured evidence capture, drafting and project management.'),
            'steps' => [
                __('Set up Project metadata and a DMP stub'),
                __('Ingest and tag sources; capture bibliographic metadata'),
                __('Record claims with supporting evidence and link sources'),
                __('Draft sections in the Writing Studio'),
                __('Run the Contradiction Engine as needed'),
            ],
        ],
        'advanced' => [
            'n' => 3,
            'label' => __('Advanced'),
            'icon' => 'fa-award',
            'who' => __('Power users preparing publication-ready, reproducible outputs with ethics tracking and cross-fonds analysis.'),
            'steps' => [
                __('Finalize manuscript sections and accept any AI-assisted drafts'),
                __('Run the Contradiction Engine and resolve flagged conflicts'),
                __('Prepare a Replication Pack with data and code'),
                __('Complete ethics approvals and link them in the project'),
                __('Publish outputs, link to a repository and track impact'),
            ],
        ],
    ];
@endphp

<div id="research-modes" class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-route me-2"></i>{{ __('Research mode guide') }}</span>
        @if(\Illuminate\Support\Facades\Route::has('help.article'))
        <a href="{{ route('help.article', 'research-modes-guide') }}" target="_blank" rel="noopener" class="small text-decoration-none">
            {{ __('Full guide') }} <i class="fas fa-up-right-from-square ms-1"></i>
        </a>
        @endif
    </div>
    <div class="card-body">
        <p class="text-muted small mb-4">{{ __('Pick the mode that fits you from the sidebar. Here is what each one is for and a short workflow - a map, not steps to click through.') }}</p>

        <div class="row g-3 align-items-stretch">
            @foreach($modes as $key => $m)
            @php $isCurrent = ($expLevel === $key); @endphp
            <div class="col-md-4">
                <div class="card h-100 {{ $isCurrent ? 'border-primary shadow-sm' : 'border-light' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge rounded-pill {{ $isCurrent ? 'bg-primary' : 'bg-secondary' }} me-2">{{ $m['n'] }}</span>
                            <h5 class="mb-0"><i class="fas {{ $m['icon'] }} me-1"></i>{{ $m['label'] }}</h5>
                            @if($isCurrent)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-auto">{{ __('Your mode') }}</span>
                            @endif
                        </div>
                        <p class="small text-muted">{{ $m['who'] }}</p>
                        <ol class="small ps-3 mb-0">
                            @foreach($m['steps'] as $step)
                            <li class="mb-1">{{ $step }}</li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <hr class="my-4">
        <h6 class="small text-uppercase text-muted">{{ __('Quick tips') }}</h6>
        <ul class="small mb-0">
            <li>{{ __('Share notebooks for collaborative drafting in Intermediate mode.') }}</li>
            <li>{{ __('Use AI Drafts as proposals only - always review before accepting.') }}</li>
            <li>{{ __('Keep provenance: detected AI use is gathered for your project AI-use disclosure statement, and you can record any other AI assistance in the disclosure log (research_ai_disclosure_log).') }}</li>
        </ul>
    </div>
</div>
