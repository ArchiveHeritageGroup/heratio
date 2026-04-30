{{--
  FTP / SFTP Upload — connection and path settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('ftp')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'FTP / SFTP Upload')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-server me-2"></i>FTP / SFTP Upload</h1>
<p class="text-muted">FTP / SFTP connection settings</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.ftp') }}">
    @csrf

    {{-- Card 1: FTP / SFTP Connection --}}
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-server me-2"></i>FTP / SFTP Connection</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label for="ftp_protocol" class="form-label fw-bold">{{ __('Protocol') }}</label>
            <select class="form-select" id="ftp_protocol" name="ftp_protocol">
              @php $curProto = $settings['ftp_protocol'] ?? 'sftp'; @endphp
              <option value="sftp" {{ $curProto === 'sftp' ? 'selected' : '' }}>{{ __('SFTP (SSH)') }}</option>
              <option value="ftp" {{ $curProto === 'ftp' ? 'selected' : '' }}>{{ __('FTP') }}</option>
            </select>
            <div class="form-text">SFTP recommended for security</div>
          </div>
          <div class="col-md-5">
            <label for="ftp_host" class="form-label fw-bold">{{ __('Host') }}</label>
            <input type="text" class="form-control" id="ftp_host" name="ftp_host"
                   value="{{ $settings['ftp_host'] ?? '' }}" placeholder="192.168.0.112">
          </div>
          <div class="col-md-3">
            <label for="ftp_port" class="form-label fw-bold">{{ __('Port') }}</label>
            <input type="number" class="form-control" id="ftp_port" name="ftp_port"
                   value="{{ $settings['ftp_port'] ?? '22' }}" min="1" max="65535">
          </div>
          <div class="col-md-4">
            <label for="ftp_username" class="form-label fw-bold">{{ __('Username') }}</label>
            <input type="text" class="form-control" id="ftp_username" name="ftp_username"
                   value="{{ $settings['ftp_username'] ?? '' }}">
          </div>
          <div class="col-md-4">
            <label for="ftp_password" class="form-label fw-bold">{{ __('Password') }}</label>
            <input type="password" class="form-control" id="ftp_password" name="ftp_password"
                   value="{{ $settings['ftp_password'] ?? '' }}" placeholder="{{ __('Leave blank to keep current') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-outline-secondary d-block w-100" id="test-ftp-btn" disabled>
              <i class="fas fa-plug me-1"></i>Test Connection
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Remote Path --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Remote Path</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="ftp_remote_path" class="form-label fw-bold">{{ __('Remote Base Path') }}</label>
            <input type="text" class="form-control" id="ftp_remote_path" name="ftp_remote_path"
                   value="{{ $settings['ftp_remote_path'] ?? '/uploads' }}" placeholder="{{ __('/uploads') }}">
            <div class="form-text">Path as seen by the SFTP/FTP user (e.g. /uploads). Used for uploading and listing files.</div>
          </div>
          <div class="col-md-6">
            <label for="ftp_disk_path" class="form-label fw-bold">{{ __('Server Disk Path') }}</label>
            <input type="text" class="form-control" id="ftp_disk_path" name="ftp_disk_path"
                   value="{{ $settings['ftp_disk_path'] ?? '' }}" placeholder="{{ __('/sftp/ftpuser/uploads') }}">
            <div class="form-text">Actual filesystem path on the server where files land. Shown to users for the CSV digitalObjectPath column.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 3: Options --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Options</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ftp_passive_mode"
                     name="ftp_passive_mode" value="1"
                     {{ ($settings['ftp_passive_mode'] ?? 'true') === 'true' || ($settings['ftp_passive_mode'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="ftp_passive_mode">{{ __('Passive Mode') }}</label>
            </div>
            <div class="form-text">Enable passive mode for FTP connections (recommended for firewalled servers). Only applies to FTP, not SFTP.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save
      </button>
    </div>
  </form>
@endsection
