<?php
$title = 'Edit User - ' . APP_NAME;
$active_page = 'users';
$page_title = 'Edit User';
ob_start();
?>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>users/edit/<?php echo $user['id']; ?>">
            <div class="mb-3">
                <label class="form-label">User No
                <input class="form-control font-monospace" type="text" inputmode="numeric" pattern="[1-9][0-9]{0,14}" maxlength="15" name="user_no" required value="<?= htmlspecialchars($user['user_no']??'',ENT_QUOTES,'UTF-8') ?>"></label>
                <div class="form-text">Unique numeric User No. Administrators can update it; desktop selectors will use the new number.</div>
            </div>
            <div class="mb-3"><label class="form-label">Full name<input class="form-control" name="name" maxlength="150" value="<?= htmlspecialchars($user['name']??'',ENT_QUOTES,'UTF-8') ?>" required></label></div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="password" class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="<?php echo ROLE_USER; ?>" <?php echo $user['role'] === ROLE_USER ? 'selected' : ''; ?>>User</option>
                            <option value="<?php echo ROLE_ADMIN; ?>" <?php echo $user['role'] === ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo BASE_URL; ?>users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            

        </form>
    </div>
</div>

<?php if((int)$user['id']!==(int)($_SESSION['user_id']??0)): ?>
<div class="card mt-3">
 <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
   <h6 class="mb-1">Account actions</h6>
   <p class="small text-muted mb-0">Reset credentials or permanently delete this account.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
   <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editUserResetModal">
    <i class="fas fa-key me-1"></i>Reset password
   </button>
   <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#editUserDeleteModal">
    <i class="fas fa-trash me-1"></i>Delete user
   </button>
  </div>
 </div>
</div>
<div class="modal fade" id="editUserResetModal" tabindex="-1" aria-labelledby="editUserResetTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="editUserResetTitle">Reset password?</h5>
   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">Reset <?= htmlspecialchars($user['name']??'',ENT_QUOTES,'UTF-8') ?> to the existing temporary default password <code>1234567890</code>? The user must change it at their next login. All their device sessions will be revoked.</div>
  <div class="modal-footer">
   <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
   <form method="post" action="<?= BASE_URL ?>users/reset_password/<?= (int)$user['id'] ?>">
    <input type="hidden" name="return_to" value="edit">
    <button type="submit" class="btn btn-warning">Reset password</button>
   </form>
  </div>
 </div></div>
</div>
<div class="modal fade" id="editUserDeleteModal" tabindex="-1" aria-labelledby="editUserDeleteTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="editUserDeleteTitle">Delete user?</h5>
   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">Permanently delete <?= htmlspecialchars($user['name']??'',ENT_QUOTES,'UTF-8') ?>? This can affect assigned monitors and their session mappings. This action cannot be undone.</div>
  <div class="modal-footer">
   <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
   <form method="post" action="<?= BASE_URL ?>users/delete/<?= (int)$user['id'] ?>" onsubmit="return confirm('Delete this account permanently?')">
    <button type="submit" class="btn btn-danger">Delete user</button>
   </form>
  </div>
 </div></div>
</div>
<?php endif; ?>

<?php if(!empty($_SESSION['success_message'])): ?>
<div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message'],ENT_QUOTES,'UTF-8') ?></div>
<?php unset($_SESSION['success_message']); endif; ?>
<div class="card mt-3">
 <div class="card-header d-flex justify-content-between align-items-center"><strong>Mapped monitors</strong><span class="badge bg-secondary"><?= count($monitors??[]) ?> Devices</span></div>
 <div class="card-body p-0">
  <div class="table-responsive">
   <table class="table table-hover align-middle mb-0">
    <thead><tr><th>IP address</th><th>Machine</th><th>OS</th><th>Status</th><th>Last activity</th><th>Remark</th><th>Lock</th><th style="min-width:300px">Actions</th></tr></thead>
    <tbody>
    <?php foreach(($monitors??[]) as $monitor): ?>
     <?php $esc=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8'); $st=$monitor['status_info']??[]; ?>
     <tr>
       <td><a href="<?= BASE_URL ?>monitors/edit/<?= (int)$monitor['id'] ?>"><?= $esc($monitor['last_seen_ip']?:$monitor['ip_address']) ?></a></td>
       <td><?= $esc(ucfirst($monitor['type']??'—')) ?></td>
       <td><?= $esc($monitor['platform']??'—') ?></td>
       <td><span class="badge bg-<?= $esc($st['status_class']??'secondary') ?>"><?= $esc($st['status_text']??'Offline') ?></span></td>
       <td class="text-nowrap"><?= $esc($monitor['last_monitor_tic']??'Never') ?></td>
       <td><?= $esc($monitor['remark']??'—') ?></td>
       <td><?= !empty($monitor['settings_locked'])||!empty($monitor['assignment_locked'])?'<span class="badge bg-warning text-dark">Locked</span>':'<span class="badge bg-light text-dark">Unlocked</span>' ?></td>
       <td>
        <form method="post" class="d-flex gap-2 align-items-center" action="<?= BASE_URL ?>users/remap_monitor/<?= (int)$user['id'] ?>" onsubmit="return confirm('Change this monitor assignment? This revokes its desktop session.')">
          <input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
          <select class="form-select form-select-sm" name="owner_user_id" aria-label="Remap monitor">
            <option value="">Unassigned</option>
            <?php foreach(($accounts??[]) as $account): ?>
             <option value="<?= (int)$account['id'] ?>" <?= (int)$account['id']===(int)$monitor['owner_user_id']?'selected':'' ?>><?= $esc(($account['user_no']??'—').': '.($account['name']??'').' ('.(int)($account['device_count']??0).' Devices)') ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
          <button type="submit" class="btn btn-sm btn-outline-danger" onclick="this.form.querySelector('select[name=owner_user_id]').value=''" title="Unlink monitor">Unlink</button>
        </form>
       </td>
     </tr>
    <?php endforeach; ?>
    <?php if(empty($monitors)): ?><tr><td colspan="8" class="text-muted text-center py-4">No monitors are mapped to this user.</td></tr><?php endif; ?>
    </tbody>
   </table>
  </div>
 </div>
</div>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 