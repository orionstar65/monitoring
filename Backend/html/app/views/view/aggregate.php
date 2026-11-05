<?php
$title='Monitoring overview - '.APP_NAME;
$active_page='view';$page_title='Monitoring overview';
ob_start();
?>
<div class="container-fluid px-2 px-md-3">
 <div class="mb-3"><h3 class="mb-1">Monitoring overview</h3>
 <p class="text-muted mb-0">Aggregate activity for your assigned devices. Detailed screenshots, browser history, keystrokes and USB activity are restricted.</p></div>
 <div class="row g-3" id="viewerTotals">
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Total logs</div><h2 id="viewerTotal">—</h2></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Browser events</div><h2 id="viewerBrowser">—</h2></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Key events</div><h2 id="viewerKeys">—</h2></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted">USB events</div><h2 id="viewerUsb">—</h2></div></div></div>
 </div>
 <div class="mt-3 small text-muted" id="viewerStatus" role="status">Loading summary…</div>
</div>
<script>
(async()=>{
 try{
  const response=await fetch(<?= json_encode(BASE_URL.'logbrowse/api_stats') ?>,{credentials:'same-origin'});
  if(!response.ok)throw new Error('HTTP '+response.status);
  const data=await response.json();
  if(!data.success)throw new Error('Summary unavailable');
  const s=data.stats;
  for(const [id,v] of Object.entries({viewerTotal:s.total,viewerBrowser:s.browser_history,viewerKeys:s.key_logs,viewerUsb:s.usb_logs})){
   document.getElementById(id).textContent=Number(v||0).toLocaleString();
  }
  document.getElementById('viewerStatus').textContent='Summary only · Log details are restricted for viewer accounts.';
 }catch(err){document.getElementById('viewerStatus').textContent='Could not load summary: '+err.message;}
})();
</script>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php';
