<?php
$title='Edit Monitor - '.APP_NAME;
$active_page='monitors';$page_title='Edit Monitor';
$e=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');
$locked=!empty($monitor['settings_locked'])||!empty($monitor['assignment_locked']);
$last=$monitor['last_monitor_tic']??null;
$age=$last ? time()-strtotime($last) : PHP_INT_MAX;
$status=$age<=300?'Online':($age<=3600?'Idle':'Offline');
$statusClass=$age<=300?'success':($age<=3600?'warning':'secondary');
ob_start();
?>
<style>
.monitor-editor {max-width:1200px;margin:0 auto}
.monitor-editor .card{border:1px solid var(--bs-border-color,#e2e8f0);border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(16,24,40,.04)}
.monitor-editor .card-header{background:transparent;border-bottom:1px solid var(--bs-border-color,#e2e8f0);padding:19px 22px}
.monitor-editor .card-body{padding:23px}
.monitor-editor .form-label{font-size:.875rem;font-weight:600;margin-bottom:8px}
.monitor-editor .form-control,.monitor-editor .form-select{min-height:43px;border-radius:9px}
.monitor-editor textarea.form-control{min-height:88px}
.monitor-editor .summary-item{display:flex;flex-direction:column;gap:5px;padding:13px 0}
.monitor-editor .summary-item dt{font-size:.75rem;font-weight:600;letter-spacing:.025em;color:var(--bs-secondary-color,#64748b)}
.monitor-editor .summary-item dd{margin:0;font-size:.95rem;font-weight:500;overflow-wrap:anywhere}
.monitor-editor .lock-line{background:var(--bs-tertiary-bg,#f8fafc);border-radius:11px;border:1px solid var(--bs-border-color,#e2e8f0);padding:16px}
</style>
<div class="monitor-editor">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
        <h3 class="mb-0">Monitor #<?= (int)$monitor['id'] ?></h3>
        <span class="badge bg-<?= $statusClass ?>"><?= $status ?></span>
        <?php if($locked): ?><span class="badge text-bg-secondary"><i class="fas fa-lock me-1"></i>Locked</span><?php endif; ?>
      </div>
      <span class="text-muted">Monitor #<?= (int)$monitor['id'] ?></span>
    </div>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>monitors"><i class="fas fa-arrow-left me-2"></i>Monitors</a>
  </div>
  <?php if(!empty($error)): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Monitor settings</h5></div>
        <div class="card-body">
          <form method="post" action="<?= BASE_URL ?>monitors/edit/<?= (int)$monitor['id'] ?>" id="monitor-edit-form">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="owner_user_id">Assigned user</label>
                <select class="form-select" id="owner_user_id" name="owner_user_id">
                  <option value="">Unassigned</option>
                  <?php foreach($accounts as $account): ?>
                    <?php $number=(string)($account['user_no']??'—');$count=(int)($account['device_count']??0); ?>
                    <option value="<?= (int)$account['id'] ?>" <?= (int)($_POST['owner_user_id']??$monitor['owner_user_id']??0)===(int)$account['id']?'selected':'' ?>>
                      <?= $e($number.': '.($account['name']??'').' ('.$count.' Devices)') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="platform">OS</label>
                <select class="form-select" name="platform" id="platform">
                  <?php foreach([''=>'Not set','Windows'=>'Windows','Mac'=>'Mac','Ubuntu'=>'Ubuntu'] as $value=>$label): ?>
                    <option value="<?= $e($value) ?>" <?= (string)($_POST['platform']??$monitor['platform']??'')===(string)$value?'selected':'' ?>><?= $e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="type">Machine</label>
                <select class="form-select" id="type" name="type" required>
                  <?php foreach(['desktop'=>'Desktop','laptop'=>'Laptop','server'=>'Server'] as $value=>$label): ?>
                    <option value="<?= $value ?>" <?= (string)($_POST['type']??$monitor['type']??'desktop')===$value?'selected':'' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="remark">Remark</label>
                <textarea class="form-control" name="remark" id="remark" maxlength="500" rows="3"><?= $e($_POST['remark']??$monitor['remark']??'') ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label" for="slack_id">Slack ID</label>
                <input class="form-control" name="slack_id" id="slack_id" maxlength="255" value="<?= $e($_POST['slack_id']??$monitor['slack_id']??'') ?>">
              </div>
              <div class="col-12">
                <div class="lock-line d-flex align-items-center justify-content-between gap-3">
                  <label class="fw-semibold m-0" for="settings_locked">Lock Settings</label>
                  <div class="form-check form-switch m-0">
                    <input class="form-check-input" style="cursor:pointer" type="checkbox" role="switch"
                           id="settings_locked" name="settings_locked" value="1"
                           <?= (isset($_POST['settings_locked'])||($_SERVER['REQUEST_METHOD']??'GET')!=='POST' && $locked)?'checked':'' ?>>
                  </div>
                </div>
              </div>
              <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                <a href="<?= BASE_URL ?>monitors" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-check me-2"></i>Save Changes</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Device information</h5></div>
        <div class="card-body">
          <dl class="row gx-3 mb-0">
            <div class="col-sm-6 summary-item"><dt>Monitor ID</dt><dd class="font-monospace"><?= (int)$monitor['id'] ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>User No</dt><dd class="font-monospace"><?= $e($monitor['owner_user_no']??'—') ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>Configured IP</dt><dd class="font-monospace"><?= $e($monitor['ip_address']??'—') ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>Observed IP</dt><dd class="font-monospace"><?= $e($monitor['last_seen_ip']??'—') ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>MAC</dt><dd class="font-monospace"><?= $e($monitor['mac_address']??'—') ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>Status</dt><dd><?= $status ?></dd></div>
            <div class="col-12 summary-item"><dt>Last activity</dt><dd><?= $e($last??'Never') ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>Created</dt><dd><?= $e($monitor['created_at']??'—') ?></dd></div>
            <div class="col-sm-6 summary-item"><dt>Updated</dt><dd><?= $e($monitor['updated_at']??'—') ?></dd></div>
          </dl>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <a href="<?= BASE_URL ?>view/logs/<?= (int)$monitor['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-chart-line me-2"></i>View Logs</a>
        <a href="<?= BASE_URL ?>view" class="btn btn-outline-secondary"><i class="fas fa-desktop me-2"></i>Live Monitoring</a>
      </div>
    </div>
  </div>
</div>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php'; ?>
