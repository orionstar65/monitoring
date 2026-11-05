<?php
$title='Create User - '.APP_NAME;
$active_page='users';$page_title='Create User';
$e=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');
ob_start();
?>
<div class="container-fluid px-0" style="max-width:860px">
 <div class="d-flex align-items-start justify-content-between mb-3 gap-3">
  <div><h4 class="mb-1">Create user</h4><p class="text-muted mb-0">Set the identity and account access for a new user.</p></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>users">Back to users</a>
 </div>
 <?php if(!empty($error)): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif; ?>
 <form method="post" action="<?= BASE_URL ?>users/create" autocomplete="off">
  <div class="card shadow-sm mb-3"><div class="card-header bg-transparent"><strong>Account information</strong></div>
   <div class="card-body"><div class="row g-3">
    <div class="col-md-5"><label for="user_no" class="form-label">User No <span class="text-danger">*</span></label>
     <input id="user_no" name="user_no" class="form-control font-monospace" inputmode="numeric" pattern="[1-9][0-9]{0,14}" maxlength="15" required value="<?= $e($_POST['user_no']??'') ?>" placeholder="Enter unique User No">
     <div class="form-text">Enter a unique number, up to 15 digits.</div></div>
    <div class="col-md-7"><label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
     <input id="name" name="name" class="form-control" maxlength="150" required value="<?= $e($_POST['name']??'') ?>" placeholder="Full name"></div>
    <div class="col-md-6"><label for="role" class="form-label">Role</label><select name="role" id="role" class="form-select">
     <option value="user" <?= ($_POST['role']??'user')==='user'?'selected':'' ?>>User</option>
     <option value="admin" <?= ($_POST['role']??'')==='admin'?'selected':'' ?>>Admin</option></select>
     <div class="form-text">User sees aggregate monitoring totals; Admin can manage accounts, logs and devices.</div></div>
    <div class="col-md-6"><label for="password" class="form-label">Password <span class="text-danger">*</span></label>
     <input type="password" id="password" name="password" class="form-control" minlength="6" maxlength="1024" autocomplete="new-password" required>
     <div class="form-text">Minimum six characters.</div></div>
   </div></div>
  </div>
  <div class="d-flex flex-wrap justify-content-end gap-2"><a href="<?= BASE_URL ?>users" class="btn btn-outline-secondary">Cancel</a>
   <button type="submit" class="btn btn-primary px-4"><i class="fas fa-user-plus me-2"></i>Create user</button></div>
 </form>
</div>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php';
