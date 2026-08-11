{{-- Generic per-procedure documented evidence/proof (#1460 Phase 1).
     Drop-in: @include('spectrum::_procedure-evidence', ['objectId'=>..,'procedureType'=>..,'slug'=>..,'procedureId'=>null])
     Keys on (object_id, procedure_type) so it works for every flow. --}}
@php
    $procedureId = $procedureId ?? null;
    $evSvc = app(\AhgSpectrum\Services\ProcedureEvidenceService::class);
    $evidence = $evSvc->list((int) $objectId, (string) $procedureType, $procedureId ? (int) $procedureId : null);
    $canEdit = \AhgCore\Services\AclService::hasPermission(auth()->id(), 'update');
    $canDelete = \AhgCore\Services\AclService::hasPermission(auth()->id(), 'delete');
    $objectDOs = \Illuminate\Support\Facades\DB::table('digital_object')
        ->where('object_id', (int) $objectId)->whereNotNull('name')
        ->select('id', 'name')->orderBy('name')->limit(200)->get();
    $cats = \AhgSpectrum\Services\ProcedureEvidenceService::CATEGORIES;
@endphp

<div class="card mb-4" id="procedure-evidence">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-paperclip me-2"></i>{{ __('Documented evidence / proof') }}</span>
        <span class="badge bg-secondary">{{ count($evidence) }}</span>
    </div>
    <div class="card-body">
        @if (empty($evidence))
            <p class="text-muted mb-3"><i class="fas fa-info-circle me-2"></i>{{ __('No evidence attached to this procedure yet.') }}</p>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>{{ __('Evidence') }}</th><th>{{ __('Category') }}</th><th>{{ __('Date') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($evidence as $ev)
                            <tr>
                                <td>
                                    <i class="fas fa-{{ $ev->evidence_kind === 'link' ? 'link' : 'file' }} me-1 text-muted"></i>
                                    <a href="{{ route('ahgspectrum.evidence.download', ['id' => $ev->id]) }}" target="_blank" rel="noopener">
                                        {{ $ev->original_name ?: ($ev->do_name ?? ('#'.$ev->id)) }}</a>
                                    @if ($ev->evidence_kind === 'link')<span class="badge bg-info text-dark ms-1">linked</span>@endif
                                    @if ($ev->description)<div class="small text-muted">{{ $ev->description }}</div>@endif
                                </td>
                                <td>@if ($ev->category)<span class="badge bg-light text-dark">{{ $ev->category }}</span>@endif</td>
                                <td class="small">{{ $ev->evidence_date ?: \Illuminate\Support\Str::of((string) $ev->created_at)->substr(0, 10) }}</td>
                                <td class="text-end">
                                    @if ($canDelete)
                                        <form method="post" action="{{ route('ahgspectrum.evidence.delete', ['id' => $ev->id]) }}" class="d-inline"
                                              onsubmit="return confirm('{{ __('Remove this evidence?') }}');">
                                            @csrf
                                            <input type="hidden" name="slug" value="{{ $slug }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canEdit)
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="small text-uppercase text-muted">{{ __('Upload a file') }}</h6>
                    <form method="post" action="{{ route('ahgspectrum.evidence.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="slug" value="{{ $slug }}">
                        <input type="hidden" name="procedure_type" value="{{ $procedureType }}">
                        @if ($procedureId)<input type="hidden" name="procedure_id" value="{{ $procedureId }}">@endif
                        <div class="mb-2"><input type="file" name="file" class="form-control form-control-sm" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><select name="category" class="form-select form-select-sm">
                                @foreach ($cats as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
                            </select></div>
                            <div class="col-6"><input type="date" name="evidence_date" class="form-control form-control-sm" title="{{ __('Evidence date') }}"></div>
                        </div>
                        <input type="text" name="description" class="form-control form-control-sm mb-2" placeholder="{{ __('Description (optional)') }}">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <h6 class="small text-uppercase text-muted">{{ __('Link an existing digital object') }}</h6>
                    <form method="post" action="{{ route('ahgspectrum.evidence.link') }}">
                        @csrf
                        <input type="hidden" name="slug" value="{{ $slug }}">
                        <input type="hidden" name="procedure_type" value="{{ $procedureType }}">
                        @if ($procedureId)<input type="hidden" name="procedure_id" value="{{ $procedureId }}">@endif
                        <div class="mb-2">
                            <select name="digital_object_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('- choose a digital object -') }}</option>
                                @foreach ($objectDOs as $do)<option value="{{ $do->id }}">{{ $do->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><select name="category" class="form-select form-select-sm">
                                @foreach ($cats as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
                            </select></div>
                            <div class="col-6"><input type="date" name="evidence_date" class="form-control form-control-sm" title="{{ __('Evidence date') }}"></div>
                        </div>
                        <input type="text" name="description" class="form-control form-control-sm mb-2" placeholder="{{ __('Description (optional)') }}">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-link me-1"></i>{{ __('Link') }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
