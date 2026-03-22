@extends('theme::layouts.1col')
@section('title', 'Order Management')
@section('body-class', 'browse')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shopping-bag me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Order Management</h1></div>
  </div>
  @if(isset($rows) && count($rows))
    <div class="table-responsive"><table class="table table-bordered table-hover mb-0">
      <thead><tr><th>#</th><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>@foreach($rows as $row)<tr>@foreach((array)$row as $v)<td>{{ $v }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div>
    @if(isset($pager))@include('ahg-core::components.pager', ['pager' => $pager])@endif
  @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>
  @endif
@endsection
