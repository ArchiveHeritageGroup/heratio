{{--
  NER extraction modal. Include in after-content section.
  Usage: @include('ahg-core::partials._ner-modal', ['objectId' => $io->id, 'objectTitle' => $io->title])
--}}
@auth
@if(\Illuminate\Support\Facades\Route::has('io.ai.review'))
<div class="modal fade" id="nerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-brain me-2"></i>Extract Entities (NER)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Extract persons, organizations, places, dates from <strong>{{ $objectTitle ?? 'this record' }}</strong></p>
        <div class="text-center mb-3"><button type="button" class="btn btn-primary btn-lg" id="nerExtractBtn"><i class="fas fa-brain me-2"></i>Extract Entities</button></div>
        <div id="nerResults" style="display:none">
          <span class="text-muted small" id="nerResultsMeta"></span>
          <div id="nerResultsBody" class="mt-2"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('io.ai.review') }}?object_id={{ $objectId }}" class="btn btn-outline-primary btn-sm" id="nerFooterReview" style="display:none"><i class="fas fa-list-check me-1"></i>Review</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var oid={{ $objectId }};
  var icons={PERSON:'fa-user',ORG:'fa-building',GPE:'fa-map-marker-alt',DATE:'fa-calendar',LOC:'fa-globe',NORP:'fa-users',EVENT:'fa-bolt',WORK_OF_ART:'fa-palette',LANGUAGE:'fa-language',FAC:'fa-landmark'};
  var colors={PERSON:'primary',ORG:'success',GPE:'info',DATE:'warning',LOC:'info',NORP:'secondary',EVENT:'danger',WORK_OF_ART:'dark',LANGUAGE:'secondary',FAC:'secondary'};
  document.getElementById('nerExtractBtn').addEventListener('click',function(){
    var b=this;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Extracting...';
    fetch('/admin/ai/ner/extract/'+oid,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'','Accept':'application/json'}})
    .then(function(r){return r.json()})
    .then(function(d){
      b.disabled=false;b.innerHTML='<i class="fas fa-brain me-2"></i>Re-Extract';
      if(!d.success){document.getElementById('nerResultsBody').innerHTML='<div class="alert alert-danger">'+(d.error||'Failed')+'</div>';document.getElementById('nerResults').style.display='';return;}
      var ent=d.entities||{},cnt=d.entity_count||0,t=d.processing_time_ms||0;
      document.getElementById('nerResultsMeta').textContent='Found '+cnt+' entities in '+t+'ms';
      document.getElementById('nerResults').style.display='';document.getElementById('nerFooterReview').style.display='';
      if(!cnt){document.getElementById('nerResultsBody').innerHTML='<p class="text-muted">No entities found.</p>';return;}
      var h='';for(var tp in ent){var ic=icons[tp]||'fa-tag',cl=colors[tp]||'secondary';h+='<div class="mb-2"><h6><i class="fas '+ic+' me-1 text-'+cl+'"></i>'+tp+' <span class="badge bg-'+cl+'">'+ent[tp].length+'</span></h6><div class="d-flex flex-wrap gap-1">';ent[tp].forEach(function(e){h+='<span class="badge bg-'+cl+' bg-opacity-10 text-'+cl+' border border-'+cl+'">'+e+'</span>';});h+='</div></div>';}
      document.getElementById('nerResultsBody').innerHTML=h;
    }).catch(function(e){b.disabled=false;b.innerHTML='<i class="fas fa-brain me-2"></i>Extract Entities';document.getElementById('nerResultsBody').innerHTML='<div class="alert alert-danger">'+e.message+'</div>';document.getElementById('nerResults').style.display='';});
  });
})();
</script>
@endif
@endauth
