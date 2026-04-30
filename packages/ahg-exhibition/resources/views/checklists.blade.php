{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Checklists')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $checklists = $exhibition->checklists ?? collect();
  $templates = $templates ?? [];

  $totalItems = 0;
  $completedItems = 0;
  $overdueItems = [];
  $today = new DateTime();
  foreach ($checklists as $cl) {
    $items = is_object($cl) && isset($cl->items) ? $cl->items : (is_array($cl) ? ($cl['items'] ?? []) : []);
    if (!is_array($items) && is_object($items) && method_exists($items, 'toArray')) { $items = $items->toArray(); }
    if (!is_array($items)) { $items = []; }
    $totalItems += count($items);
    foreach ($items as $it) {
      $itObj = (object) $it;
      if (!empty($itObj->is_completed)) { $completedItems++; }
      if (!empty($itObj->due_date) && empty($itObj->is_completed)) {
        try {
          $due = new DateTime($itObj->due_date);
          if ($due < $today) {
            $itObj->checklist_name = is_object($cl) ? ($cl->name ?? '') : ($cl['name'] ?? '');
            $overdueItems[] = $itObj;
          }
        } catch (\Throwable $e) {}
      }
    }
  }
  $overallProgress = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">Exhibitions</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">Checklists</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>{{ __('Exhibition Checklists') }}</h1>
      <div class="btn-group">
        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
          <i class="fas fa-plus"></i> {{ __('Create Checklist') }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          @forelse($templates as $template)
            @php $tpl = (object) $template; @endphp
            <li>
              <form method="post" action="{{ route('exhibition.checklists', ['id' => $exId]) }}" style="display: inline;">
                @csrf
                <input type="hidden" name="template_id" value="{{ $tpl->id ?? '' }}">
                <button type="submit" class="dropdown-item">
                  {{ $tpl->name ?? '' }}
                  <small class="text-muted d-block">{{ $tpl->item_count ?? 0 }} items</small>
                </button>
              </form>
            </li>
          @empty
            <li><span class="dropdown-item text-muted">{{ __('No templates available') }}</span></li>
          @endforelse
        </ul>
      </div>
    </div>

    @if(empty($checklists) || (is_object($checklists) && method_exists($checklists, 'isEmpty') && $checklists->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-square-check fa-3x text-muted mb-3"></i>
          <h5>{{ __('No checklists created yet') }}</h5>
          <p class="text-muted">Create checklists to track tasks for planning, installation, and closing.</p>
        </div>
      </div>
    @else
      @foreach($checklists as $checklist)
        @php
          $cl = (object) $checklist;
          $items = $cl->items ?? [];
          if (is_object($items) && method_exists($items, 'toArray')) { $items = $items->toArray(); }
          if (!is_array($items)) { $items = []; }
          $total = count($items);
          $completed = 0;
          foreach ($items as $it) { if (!empty(((object)$it)->is_completed)) { $completed++; } }
          $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
        @endphp
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">{{ $cl->name ?? '' }}</h5>
              <small class="text-muted text-capitalize">{{ str_replace('_', ' ', $cl->checklist_type ?? ($cl->category ?? 'general')) }}</small>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="text-end">
                <span class="badge bg-{{ $progress == 100 ? 'success' : ($progress > 50 ? 'info' : 'secondary') }} fs-6">{{ $progress }}%</span>
                <br><small class="text-muted">{{ $completed }}/{{ $total }} complete</small>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="progress" style="height: 4px; border-radius: 0;">
              <div class="progress-bar bg-{{ $progress == 100 ? 'success' : 'primary' }}" style="width: {{ $progress }}%"></div>
            </div>
            @if(!empty($items))
              <ul class="list-group list-group-flush">
                @foreach($items as $item)
                  @php $it = (object) $item; @endphp
                  <li class="list-group-item {{ !empty($it->is_completed) ? 'bg-light' : '' }}">
                    <div class="d-flex align-items-start">
                      <div class="form-check me-3">
                        <input type="checkbox" class="form-check-input" style="transform: scale(1.3);" {{ !empty($it->is_completed) ? 'checked disabled' : '' }}>
                      </div>
                      <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                          <div>
                            <span class="{{ !empty($it->is_completed) ? 'text-decoration-line-through text-muted' : '' }}">{{ $it->name ?? '' }}</span>
                            @if(!empty($it->assigned_to))
                              <br><small class="text-muted"><i class="fas fa-user me-1"></i> {{ $it->assigned_to }}</small>
                            @endif
                          </div>
                          <div class="text-end">
                            @if(!empty($it->due_date))
                              @php
                                $isOverdue = false;
                                try { $dueObj = new DateTime($it->due_date); $isOverdue = $dueObj < $today && empty($it->is_completed); } catch (\Throwable $e) {}
                              @endphp
                              <span class="badge {{ $isOverdue ? 'bg-danger' : 'bg-light text-dark' }}">Due: {{ $it->due_date }}</span>
                            @endif
                          </div>
                        </div>
                        @if(!empty($it->notes))
                          <p class="small text-muted mb-0 mt-1">{{ $it->notes }}</p>
                        @endif
                      </div>
                    </div>
                  </li>
                @endforeach
              </ul>
            @else
              <div class="p-4 text-center text-muted">
                <p class="mb-0">No items in this checklist</p>
              </div>
            @endif
          </div>
          <div class="card-footer bg-transparent">
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addItemModal" data-checklist-id="{{ $cl->id ?? '' }}" data-checklist-name="{{ $cl->name ?? '' }}">
              <i class="fas fa-plus"></i> {{ __('Add Item') }}
            </button>
          </div>
        </div>
      @endforeach
    @endif
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Exhibition Info') }}</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Overall Progress') }}</h5>
      </div>
      <div class="card-body">
        <div class="text-center mb-3">
          <div class="display-4">{{ $overallProgress }}%</div>
          <small class="text-muted">{{ $completedItems }} of {{ $totalItems }} items complete</small>
        </div>
        <div class="progress" style="height: 8px;">
          <div class="progress-bar bg-{{ $overallProgress == 100 ? 'success' : 'primary' }}" style="width: {{ $overallProgress }}%"></div>
        </div>
      </div>
    </div>

    @if(!empty($overdueItems))
      <div class="card mb-3 border-danger">
        <div class="card-header bg-danger text-white">
          <h5 class="mb-0"><i class="fas fa-triangle-exclamation me-2"></i> Overdue Items</h5>
        </div>
        <ul class="list-group list-group-flush">
          @foreach(array_slice($overdueItems, 0, 5) as $item)
            <li class="list-group-item">
              <strong class="small">{{ $item->name ?? '' }}</strong>
              <br><small class="text-danger">Due: {{ $item->due_date ?? '' }}</small>
              <br><small class="text-muted">{{ $item->checklist_name ?? '' }}</small>
            </li>
          @endforeach
          @if(count($overdueItems) > 5)
            <li class="list-group-item text-center">
              <small class="text-muted">+{{ count($overdueItems) - 5 }} more overdue</small>
            </li>
          @endif
        </ul>
      </div>
    @endif

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Checklist Types') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled small mb-0">
          <li class="mb-1"><strong>{{ __('Planning') }}</strong> - Pre-exhibition tasks</li>
          <li class="mb-1"><strong>{{ __('Installation') }}</strong> - Setup tasks</li>
          <li class="mb-1"><strong>{{ __('Opening') }}</strong> - Launch preparation</li>
          <li class="mb-1"><strong>{{ __('Operation') }}</strong> - During exhibition</li>
          <li><strong>{{ __('Closing') }}</strong> - Deinstallation tasks</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add Checklist Item') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.checklists', ['id' => $exId]) }}">
        @csrf
        <input type="hidden" name="checklist_id" id="addItemChecklistId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Checklist') }}</label>
            <input type="text" id="addItemChecklistName" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Task Name <span class="text-danger">*</span></label>
            <input type="text" name="task_name" class="form-control" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">{{ __('Assigned To') }}</label>
              <input type="text" name="assigned_to" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">{{ __('Due Date') }}</label>
              <input type="date" name="due_date" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Add Item') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
