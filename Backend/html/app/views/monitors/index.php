<?php
$title='Monitors - '.APP_NAME;$active_page='monitors';$page_title='Monitors';
$escape=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');
ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
 <div><h3 class="mb-1">Monitors</h3><p class="text-muted small mb-0">Devices, ownership and current activity</p></div>
 <?php if(($_SESSION['role']??'')==='admin'): ?>
 <a class="btn btn-primary" href="<?= BASE_URL ?>monitors/create"><i class="fas fa-plus me-2"></i>Add Monitor</a>
 <?php endif; ?>
</div>
<div class="row g-3 mb-4">
 <div class="col-sm-4"><div class="card p-3"><div class="text-muted small">Monitors</div><div class="fs-3 fw-bold"><?= count($monitors) ?></div></div></div>
 <div class="col-sm-4"><div class="card p-3"><div class="text-muted small">Active</div><div class="fs-3 fw-bold text-success"><?= count(array_filter($monitors,static fn($m)=>$m['dynamic_status']==='active')) ?></div></div></div>
 <div class="col-sm-4"><div class="card p-3"><div class="text-muted small">Users</div><div class="fs-3 fw-bold"><?= (int)$distinct_username_count ?></div></div></div>
</div>
<div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
<table id="monitors-table" class="table table-hover align-middle advanced-table" style="width:100%">
<thead><tr><th>ID</th><th>User (full name)</th><th>IP address</th><th>Machine</th><th>OS</th><th>Status</th><th>Last activity</th><th>Remark</th><th data-dt-order="disable">Actions</th></tr></thead>
<tbody>
<?php foreach($monitors as $m): ?>
<tr>
 <td><?= (int)$m['id'] ?></td>
 <td><?= $m['owner_name']!==null ? $escape($m['owner_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
 <td><code><?= $escape($m['ip_address']) ?></code></td>
 <td><?= $escape(ucfirst(strtolower($m['type']??'desktop'))) ?></td>
 <td><?= $escape($m['platform']??'—') ?></td>
 <td><span class="badge <?= $m['dynamic_status']==='active'?'bg-success':'bg-secondary' ?>"><?= $escape($m['status_text']) ?></span></td>
 <td data-order="<?= $escape($m['last_monitor_tic']??'') ?>"><?= $m['last_monitor_tic']?$escape($m['last_monitor_tic']):'Never' ?></td>
 <td><?= $escape($m['remark']??'') ?></td>
 <td class="text-nowrap">
 <a href="<?= BASE_URL ?>view/logs/<?= (int)$m['id'] ?>" class="btn btn-sm btn-light" title="View logs" aria-label="View logs"><i class="fas fa-chart-line"></i></a>
 <?php if(($_SESSION['role']??'')==='admin'): ?>
 <a href="<?= BASE_URL ?>monitors/edit/<?= (int)$m['id'] ?>" class="btn btn-sm btn-light" title="Edit" aria-label="Edit monitor"><i class="fas fa-pen"></i></a>
 <form method="post" action="<?= BASE_URL ?>monitors/delete/<?= (int)$m['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this monitor and its logs?')">
 <button type="submit" class="btn btn-sm btn-light text-danger" aria-label="Delete monitor" title="Delete"><i class="fas fa-trash"></i></button></form>
 <?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div></div>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php'; ?>
