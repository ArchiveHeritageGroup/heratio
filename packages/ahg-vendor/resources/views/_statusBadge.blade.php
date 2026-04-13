{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@php
$statusConfig = [
    'pending_approval' => ['label' => 'Pending Approval', 'color' => 'warning', 'icon' => 'clock'],
    'approved' => ['label' => 'Approved', 'color' => 'info', 'icon' => 'check'],
    'dispatched' => ['label' => 'Dispatched', 'color' => 'primary', 'icon' => 'truck'],
    'received_by_vendor' => ['label' => 'At Vendor', 'color' => 'secondary', 'icon' => 'building'],
    'in_progress' => ['label' => 'In Progress', 'color' => 'info', 'icon' => 'spinner'],
    'completed' => ['label' => 'Completed', 'color' => 'success', 'icon' => 'check-circle'],
    'ready_for_collection' => ['label' => 'Ready', 'color' => 'success', 'icon' => 'box'],
    'returned' => ['label' => 'Returned', 'color' => 'dark', 'icon' => 'undo'],
    'cancelled' => ['label' => 'Cancelled', 'color' => 'danger', 'icon' => 'times'],
];

$config = $statusConfig[$status ?? ''] ?? ['label' => ucfirst(str_replace('_', ' ', (string) ($status ?? ''))), 'color' => 'secondary', 'icon' => 'question'];
@endphp
<span class="badge bg-{{ $config['color'] }}">
    <i class="fas fa-{{ $config['icon'] }} me-1"></i>{{ $config['label'] }}
</span>
