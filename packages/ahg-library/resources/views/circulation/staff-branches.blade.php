@extends('theme::layouts.1col')
@section('title', __('Staff Branches'))
@section('body-class', 'admin library')

@section('content')
<h1><i class="fas fa-code-branch me-2"></i>{{ __('Staff Branches') }}</h1>

{{-- #1473: where each member of counter staff works. Unlocked staff can still
     change branch themselves at the circulation desk; locked staff cannot,
     which is what a consortium with contractually separate outlets needs. --}}
<p class="text-muted">
  {{ __('Staff not listed here choose their branch at the circulation desk. Lock a member of staff to keep them at the branch you set.') }}
</p>

@unless($branchAware ?? false)
  <div class="alert alert-info">{{ __('Circulation branches are not set up on this installation yet.') }}</div>
@endunless

<div class="card">
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>{{ __('Staff') }}</th>
          <th>{{ __('Branch') }}</th>
          <th>{{ __('Locked') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($assignments ?? [] as $a)
          <tr>
            <td>{{ $a->username }} <small class="text-muted">{{ $a->email }}</small></td>
            <td>
              @if((int) ($a->all_branches ?? 0) === 1)
                <span class="text-muted">{{ __('All branches') }}</span>
              @elseif($a->branch_id)
                {{ $branchOptions[(int) $a->branch_id] ?? ('#' . $a->branch_id) }}
              @else
                <span class="text-muted">{{ __('Not set') }}</span>
              @endif
            </td>
            <td>
              @if((int) ($a->locked ?? 0) === 1)
                <span class="badge bg-warning text-dark"><i class="fas fa-lock me-1"></i>{{ __('Locked') }}</span>
              @else
                <span class="text-muted">{{ __('No') }}</span>
              @endif
            </td>
            <td class="text-end">
              <form method="post" action="{{ route('library.staff-branches.delete', $a->user_id) }}"
                    onsubmit="return confirm('{{ __('Remove this staff branch?') }}');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-muted text-center py-3">{{ __('No staff branches set.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header"><i class="fas fa-plus me-2"></i>{{ __('Set a staff branch') }}</div>
  <div class="card-body">
    <form method="post" action="{{ route('library.staff-branches.save') }}" class="row g-3">
      @csrf
      <div class="col-md-4">
        <label class="form-label" for="sb_user">{{ __('Staff') }} <span class="text-danger">*</span></label>
        <select name="user_id" id="sb_user" class="form-select" required>
          <option value="">{{ __('Choose...') }}</option>
          @foreach($users ?? [] as $uid => $uname)
            <option value="{{ $uid }}" @selected((int) old('user_id') === (int) $uid)>{{ $uname }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="sb_branch">{{ __('Branch') }}</label>
        <select name="branch_id" id="sb_branch" class="form-select @error('branch_id') is-invalid @enderror">
          <option value="0">{{ __('Not set') }}</option>
          @foreach($branchOptions ?? [] as $bid => $bname)
            <option value="{{ $bid }}" @selected((int) old('branch_id') === (int) $bid)>{{ $bname }}</option>
          @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
        <div class="form-check">
          <input type="checkbox" name="all_branches" id="sb_all" class="form-check-input" value="1" @checked(old('all_branches'))>
          <label class="form-check-label" for="sb_all">{{ __('Works across all branches') }}</label>
        </div>
        <div class="form-check">
          <input type="checkbox" name="locked" id="sb_locked" class="form-check-input" value="1" @checked(old('locked'))>
          <label class="form-check-label" for="sb_locked">{{ __('Locked: cannot change branch at the desk') }}</label>
        </div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
