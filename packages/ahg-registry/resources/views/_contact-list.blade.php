{{--
  Contact list table for institution / vendor pages.
  Vars: $contacts (iterable), $canEdit (bool), $entityType ('vendor'|'institution').

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $showEdit = ! empty($canEdit);
    $eType = $entityType ?? '';
    $editRoute = $eType === 'vendor' ? 'registry.myVendorContactEdit' : 'registry.myInstitutionContactEdit';
@endphp
@if (! empty($contacts) && count($contacts) > 0)
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Role') }}</th>
                    @if ($showEdit)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($contacts as $c)
                    @php
                        $c = (object) $c;
                        $fullName = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
                        $roles = [];
                        if (! empty($c->roles)) {
                            $roles = is_string($c->roles) ? (json_decode($c->roles, true) ?: []) : (array) $c->roles;
                        } elseif (! empty($c->role)) {
                            $roles = [$c->role];
                        }
                    @endphp
                    <tr>
                        <td>
                            {{ $fullName }}
                            @if (! empty($c->is_primary))
                                <i class="fas fa-star text-warning ms-1" title="{{ __('Primary Contact') }}"></i>
                            @endif
                        </td>
                        <td>@if (! empty($c->job_title))<small>{{ $c->job_title }}</small>@endif</td>
                        <td>
                            @if (! empty($c->email))
                                <a href="mailto:{{ $c->email }}" class="small">{{ $c->email }}</a>
                            @endif
                        </td>
                        <td>
                            <small>
                                @if (! empty($c->phone)){{ $c->phone }}@endif
                                @if (! empty($c->mobile))@if (! empty($c->phone))<br>@endif{{ $c->mobile }}@endif
                            </small>
                        </td>
                        <td>
                            @foreach ($roles as $role)
                                <span class="badge bg-light text-dark border me-1">{{ ucfirst(str_replace('_', ' ', $role)) }}</span>
                            @endforeach
                        </td>
                        @if ($showEdit)
                            <td class="text-end text-nowrap">
                                @php
                                    $editHref = \Illuminate\Support\Facades\Route::has($editRoute)
                                        ? route($editRoute, ['id' => (int) $c->id])
                                        : '#';
                                @endphp
                                <a href="{{ $editHref }}" class="btn btn-sm btn-outline-primary" title="{{ __('Edit') }}">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted small mb-0">{{ __('No contacts listed.') }}</p>
@endif
