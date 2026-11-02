<?php
$title='Users - '.APP_NAME;
$active_page='users';$page_title='User Management';
$escape=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');
ob_start();
?>
<?php if(!empty($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
 <?= $escape($_SESSION['success_message']) ?>
 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
</div>
<?php unset($_SESSION['success_message']);endif; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
 <div><h3 class="mb-1">User Management</h3><p class="text-muted small mb-0">Accounts and their assigned monitors</p></div>
 <a href="<?= BASE_URL ?>users/create" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add User</a>
</div>
<div class="card shadow-sm"><div class="card-body">
 <div class="table-responsive">
 <table id="users-table" class="table table-hover align-middle advanced-table" style="width:100%">
 <thead><tr><th>Full name</th><th>Username</th><th>Email address</th><th>Role</th><th>Created</th><th>Last activity</th><th data-dt-order="disable">Actions</th></tr></thead>
 <tbody>
 <?php foreach($users as $user): ?>
 <tr>
  <td class="fw-semibold"><?= $escape($user['name']??'') ?></td>
  <td><?= $escape($user['username']) ?></td>
  <td><?= ($user['email']??'')!=='' ? $escape($user['email']) : '<span class="text-muted">—</span>' ?></td>
  <td><span class="badge <?= $user['role']==='admin'?'bg-primary':'bg-secondary' ?>"><?= $escape(ucfirst($user['role'])) ?></span></td>
  <td data-order="<?= $escape($user['created_at']) ?>"><?= $escape($user['created_at']) ?></td>
  <td data-order="<?= $escape($user['last_activity']??'') ?>"><?= $user['last_activity'] ? $escape($user['last_activity']) : '<span class="text-muted">Never</span>' ?></td>
  <td class="text-nowrap">
    <a href="<?= BASE_URL ?>users/edit/<?= (int)$user['id'] ?>" class="btn btn-sm btn-light" aria-label="Edit user" title="Edit"><i class="fas fa-pen"></i></a>
    <?php if((int)$user['id']!==(int)$_SESSION['user_id']): ?>
    <button type="button" class="btn btn-sm btn-light text-warning reset-user"
      data-id="<?= (int)$user['id'] ?>" data-name="<?= $escape($user['name']) ?>"
      aria-label="Reset to default password" title="Reset password"><i class="fas fa-key"></i></button>
    <form method="post" action="<?= BASE_URL ?>users/delete/<?= (int)$user['id'] ?>" class="d-inline"
      onsubmit="return confirm('Delete user and revoke their access?')">
      <button type="submit" class="btn btn-sm btn-light text-danger" aria-label="Delete user" title="Delete"><i class="fas fa-trash"></i></button>
    </form>
    <?php endif; ?>
  </td>
 </tr>
 <?php endforeach; ?>
 </tbody></table></div>
</div></div>
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordTitle" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><h5 id="resetPasswordTitle" class="modal-title">Reset user password?</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
 <div class="modal-body">Reset the password for <strong id="resetAccountName"></strong> to
 <code>1234567890</code>? This is a temporary password. The account must change it on next login, and all desktop sessions will be revoked.</div>
 <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
 <form id="resetPasswordForm" method="post"><button type="submit" class="btn btn-warning">Reset password</button></form></div>
 </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const modalEl=document.getElementById('resetPasswordModal');
 const modal=new bootstrap.Modal(modalEl);
 document.querySelectorAll('.reset-user').forEach(button=>{
  button.addEventListener('click',()=>{
   document.getElementById('resetAccountName').textContent=button.dataset.name;
   document.getElementById('resetPasswordForm').action=<?= json_encode(BASE_URL.'users/reset_password/',JSON_HEX_TAG|JSON_HEX_APOS) ?>+encodeURIComponent(button.dataset.id);
   modal.show();
  });
 });
});
</script>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php'; ?>
