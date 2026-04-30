@extends('theme::layouts.2col')
@section('title', 'System Information')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-server me-2"></i>{{ __('System Information') }}</h1>
@endsection

@section('content')
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">Server environment</div>
      <div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
          <tbody>
            <tr><td class="fw-bold" style="width:35%">PHP version</td><td>{{ $info['php_version'] }}</td></tr>
            <tr><td class="fw-bold">Laravel version</td><td>{{ $info['laravel_version'] }}</td></tr>
            <tr><td class="fw-bold">Server software</td><td>{{ $info['server_software'] }}</td></tr>
            <tr><td class="fw-bold">Operating system</td><td>{{ $info['os'] }}</td></tr>
            <tr><td class="fw-bold">Memory limit</td><td>{{ $info['memory_limit'] }}</td></tr>
            <tr><td class="fw-bold">Max execution time</td><td>{{ $info['max_execution_time'] }}s</td></tr>
            <tr><td class="fw-bold">Upload max filesize</td><td>{{ $info['upload_max_filesize'] }}</td></tr>
            <tr><td class="fw-bold">Post max size</td><td>{{ $info['post_max_size'] }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">Database</div>
      <div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
          <tbody>
            <tr><td class="fw-bold" style="width:35%">Database size</td><td>{{ $info['database_size_mb'] }} MB</td></tr>
            <tr><td class="fw-bold">Table count</td><td>{{ $info['table_count'] }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">PHP extensions ({{ count($info['extensions']) }})</div>
      <div class="card-body">
        <div class="row">
          @foreach(array_chunk($info['extensions'], (int) ceil(count($info['extensions']) / 4)) as $chunk)
            <div class="col-md-3">
              <ul class="list-unstyled mb-0">
                @foreach($chunk as $ext)
                  <li><code>{{ $ext }}</code></li>
                @endforeach
              </ul>
            </div>
          @endforeach
        </div>
      </div>
    </div>
@endsection
