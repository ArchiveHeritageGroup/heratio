{{-- Partial: Help sidebar --}}
<div class="help-sidebar"><div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-life-ring me-2"></i>Help Topics</div>
<div class="list-group list-group-flush">
  <a href="{{ route('help.category', 'getting-started') }}" class="list-group-item list-group-item-action"><i class="fas fa-play-circle me-2"></i>Getting Started</a>
  <a href="{{ route('help.category', 'descriptions') }}" class="list-group-item list-group-item-action"><i class="fas fa-file-alt me-2"></i>Descriptions</a>
  <a href="{{ route('help.category', 'admin') }}" class="list-group-item list-group-item-action"><i class="fas fa-cog me-2"></i>Administration</a>
  <a href="{{ route('help.index') }}" class="list-group-item list-group-item-action"><i class="fas fa-book me-2"></i>All Topics</a>
</div></div></div>
