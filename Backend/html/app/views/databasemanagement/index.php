<?php
$title='Database Management - '.APP_NAME;
$active_page='db-management';
$page_title='Database Management';
$e=static fn($value)=>htmlspecialchars((string)($value??''),ENT_QUOTES,'UTF-8');
$human=static function($bytes):string {
    $value=max(0,(float)$bytes);
    foreach(['B','KB','MB','GB','TB'] as $unit){
        if($value<1024 || $unit==='TB')return number_format($value,$unit==='B'?0:1).' '.$unit;
        $value/=1024;
    }
    return '';
};
ob_start();
?>
<style>
.db-admin-wrap{max-width:1440px;margin:0 auto}
.db-admin-wrap .card{border-radius:9px}
.db-admin-wrap .card-body{padding:13px 15px}
.db-admin-wrap .db-section-title{font-size:1rem;font-weight:650;margin-bottom:4px}
.db-admin-wrap .table th{font-size:.74rem;font-weight:650}
.db-admin-wrap .table td{font-size:.8rem}
.db-admin-wrap .db-meta{font-size:.75rem;color:var(--bs-secondary-color)}
.db-admin-wrap .db-num{font-variant-numeric:tabular-nums;white-space:nowrap}
.db-admin-wrap .db-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.db-admin-wrap .modal .form-label{font-size:.81rem}
</style>
<div class="db-admin-wrap">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3 flex-wrap">
        <div>
            <h4 class="mb-1">Database Management</h4>
            <p class="text-muted small mb-0">Super Admin only · <?= $e($databaseName) ?> · <?= count($tables) ?> tables</p>
        </div>
        <a href="<?= BASE_URL ?>databasemanagement" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-sync-alt me-1"></i>Refresh statistics
        </a>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="db-meta">Total database allocation</div>
                <div class="h4 mb-0"><?= $human($totalBytes) ?></div>
                <div class="db-meta mt-1">Data + indexes, from MySQL statistics</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="db-meta">Tables</div>
                <div class="h4 mb-0"><?= count($tables) ?></div>
                <div class="db-meta mt-1">Includes system and history tables</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="db-meta">Age-cleanable history tables</div>
                <div class="h4 mb-0"><?= count($cleanable) ?></div>
                <div class="db-meta mt-1">Identity and monitoring settings are protected</div>
            </div></div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body pb-1">
            <div class="d-flex justify-content-between gap-2 align-items-start mb-2 flex-wrap">
                <div>
                    <div class="db-section-title">Table status</div>
                    <p class="db-meta mb-0">InnoDB row counts and data sizes are estimates; they are not live exact counts.</p>
                </div>
            </div>
            <div class="table-responsive" style="max-height:520px;overflow:auto">
                <table class="table table-hover table-sm align-middle mb-1">
                    <thead class="table-light sticky-top">
                        <tr><th>Table name</th><th class="text-end">Estimated rows</th>
                            <th class="text-end">Data</th><th class="text-end">Indexes</th>
                            <th class="text-end">Total size</th><th>Engine</th>
                            <th>Created at</th><th>Retention</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($tables as $t): ?>
                        <tr>
                            <td class="font-monospace"><?= $e($t['TABLE_NAME']) ?></td>
                            <td class="text-end db-num"><?= number_format((int)$t['TABLE_ROWS']) ?></td>
                            <td class="text-end db-num"><?= $human($t['DATA_LENGTH']) ?></td>
                            <td class="text-end db-num"><?= $human($t['INDEX_LENGTH']) ?></td>
                            <td class="text-end db-num fw-semibold"><?= $human((int)$t['DATA_LENGTH']+(int)$t['INDEX_LENGTH']) ?></td>
                            <td><?= $e($t['ENGINE']) ?></td>
                            <td><?= !empty($t['has_created_at'])?'<span class="text-success">Yes</span>':'<span class="text-muted">No</span>' ?></td>
                            <td>
                                <?php if($t['cleanable']): ?>
                                    <span class="badge bg-primary">History</span>
                                <?php elseif($t['TABLE_NAME']==='screenshot_metadata'): ?>
                                    <span class="badge bg-secondary">Use screenshot cleanup</span>
                                <?php else: ?>
                                    <span class="text-muted small">Protected / excluded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(!$tables): ?>
                        <tr><td colspan="8" class="text-muted text-center py-4">No database tables found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="db-section-title">Clean old database logs</div>
                    <p class="small text-muted mb-2">
                        Delete event and history rows older than a selected number of months, using
                        each row's <code>created_at</code> timestamp. User accounts, monitors,
                        credentials and assignments will remain untouched.
                    </p>
                    <p class="db-meta mb-2">
                        Eligible: <?= $cleanable?$e(implode(', ',$cleanable)):'no matching tables' ?>.
                        Screenshot files and metadata are managed separately through Screenshot cleanup.
                        Create a backup first; cleanup cannot be undone.
                    </p>
                    <div class="db-actions">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                            data-bs-target="#dbCleanupModal" <?= !$cleanable?'disabled':'' ?>>
                            <i class="fas fa-broom me-1"></i>Clean DB
                        </button>
                        <span class="small" id="dbCleanupProgress" role="status" aria-live="polite"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="db-section-title">Backup and restore</div>
                    <p class="small text-muted mb-2">
                        Download a compressed full SQL backup (schema and records).
                        Backups contain private monitoring data, credentials and personal information:
                        store the file securely. Restoring replaces database tables and may
                        invalidate active sessions.
                    </p>
                    <form method="post" action="<?= BASE_URL ?>databasemanagement/download_backup" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']??'') ?>">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-download me-1"></i>Download backup (.sql.gz)
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal"
                        data-bs-target="#dbRestoreModal">
                        <i class="fas fa-undo me-1"></i>Restore from backup
                    </button>
                    <div class="db-meta mt-2">Requires mysqldump/mysql binaries and sufficient server temporary disk space.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="dbCleanupModal" tabindex="-1" aria-labelledby="dbCleanupTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2">
            <h5 class="modal-title" id="dbCleanupTitle">Confirm database cleanup</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="dbCleanupForm">
            <div class="modal-body">
                <label class="form-label fw-semibold" for="dbRetentionMonths">Keep the latest (months)</label>
                <input class="form-control form-control-sm" type="number" min="1" max="120"
                    step="1" value="3" name="months" id="dbRetentionMonths" required>
                <p class="small mt-2 mb-2" id="dbRetentionDescription">
                    Data received more than 3 months ago will be permanently removed from
                    eligible keyboard, browser and USB log tables. Newer records remain available.
                </p>
                <p class="small text-muted mb-2">Only historical log records are affected.
                    Screenshot cleanup is separate; user accounts and device records are never deleted.</p>
                <label class="form-label" for="dbCleanupConfirm">Type <strong>DELETE OLD LOGS</strong> to confirm</label>
                <input class="form-control form-control-sm" id="dbCleanupConfirm"
                    autocomplete="off" required placeholder="DELETE OLD LOGS">
                <div class="small text-danger mt-2" id="dbCleanupError" role="alert"></div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-sm btn-danger" type="submit" id="dbCleanupStart">Delete old logs</button>
            </div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="dbRestoreModal" tabindex="-1" aria-labelledby="dbRestoreTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2"><h5 id="dbRestoreTitle" class="modal-title">Restore database backup</h5>
            <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button></div>
        <form action="<?= BASE_URL ?>databasemanagement/restore_backup" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']??'') ?>">
            <div class="modal-body">
                <p class="small text-danger">This operation can replace all records and tables.
                    Database schema changes cannot be rolled back automatically. If restore fails,
                    the database may be partially restored. First download a fresh backup.</p>
                <label for="dbRestoreFile" class="form-label">MonitorClient SQL backup (.sql.gz)</label>
                <input id="dbRestoreFile" name="backup" type="file" class="form-control form-control-sm"
                    accept=".gz,.sql.gz,application/gzip" required>
                <label for="dbRestorePhrase" class="form-label mt-3">Type <strong>RESTORE DATABASE</strong> to confirm</label>
                <input id="dbRestorePhrase" name="confirm" class="form-control form-control-sm"
                    autocomplete="off" required>
                <label class="small mt-3 d-flex gap-2">
                    <input type="checkbox" name="acknowledge" value="yes" required>
                    <span>I have downloaded a fresh backup and understand that restore is destructive.</span>
                </label>
                <p class="db-meta mt-2">You must have generated a backup in this session within the last two hours.
                    The maximum upload size also depends on your PHP configuration.</p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-warning" id="dbRestoreSubmit">Restore database</button>
            </div>
        </form>
    </div></div>
</div>
<script>
(() => {
    const month=document.getElementById('dbRetentionMonths');
    const description=document.getElementById('dbRetentionDescription');
    const form=document.getElementById('dbCleanupForm');
    const progress=document.getElementById('dbCleanupProgress');
    const error=document.getElementById('dbCleanupError');
    const button=document.getElementById('dbCleanupStart');
    if(!month||!form)return;
    const updateDescription=()=>{
        const count=Math.max(1,Math.min(120,Number(month.value)||3));
        const cutoff=new Date();
        cutoff.setMonth(cutoff.getMonth()-count);
        description.textContent='Records received before '+
            cutoff.toLocaleDateString(undefined,{year:'numeric',month:'long',day:'numeric'})+
            ' (more than '+count+' month'+(count===1?'':'s')+' old) will be permanently deleted from eligible history tables. More recent records are retained.';
    };
    month.addEventListener('input',updateDescription);
    updateDescription();
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        if(document.getElementById('dbCleanupConfirm').value.trim()!=='DELETE OLD LOGS'){
            error.textContent='Enter the exact confirmation phrase.';return;
        }
        const retention=Number(month.value);
        if(!Number.isInteger(retention)||retention<1||retention>120){
            error.textContent='Choose 1–120 months.';return;
        }
        button.disabled=true;error.textContent='';
        let total=0,index=0,batches=0;
        try{
            // One bounded DELETE per request. No large client-side row fetches.
            while(batches++<100000){
                const payload=new URLSearchParams({
                    months:String(retention),table_index:String(index),confirm:'DELETE OLD LOGS',
                    csrf_token:document.querySelector('meta[name="csrf-token"]')?.content||''
                });
                const response=await fetch(<?= json_encode(BASE_URL.'databasemanagement/cleanup_batch') ?>,{
                    method:'POST',credentials:'same-origin',body:payload
                });
                const data=await response.json();
                if(!response.ok||!data.success)throw Error(data.error||'Cleanup failed');
                total+=Number(data.deleted)||0;
                index=Number(data.next_table)||0;
                progress.textContent='Deleted '+total.toLocaleString()+' rows · '+(data.table||'Completed');
                if(data.done){
                    bootstrap.Modal.getInstance(document.getElementById('dbCleanupModal'))?.hide();
                    progress.textContent='Cleanup completed: '+total.toLocaleString()+' rows removed. Refresh table statistics.';
                    button.disabled=false;
                    return;
                }
            }
            throw Error('Stopped after maximum cleanup batches. Run cleanup again if needed.');
        }catch(exc){
            error.textContent=exc.message;
            progress.textContent='Cleanup stopped after '+total.toLocaleString()+' removed rows.';
            button.disabled=false;
        }
    });
    document.getElementById('dbRestoreSubmit')?.addEventListener('click',event=>{
        if(document.getElementById('dbRestorePhrase').value!=='RESTORE DATABASE'){
            event.preventDefault();alert('Type RESTORE DATABASE exactly to confirm.');return;
        }
        if(!confirm('Replace the current database with this backup? This cannot be undone automatically.')){
            event.preventDefault();
        }
    });
})();
</script>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php'; ?>
