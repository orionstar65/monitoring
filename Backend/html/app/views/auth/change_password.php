<?php
$title='Change Password - '.APP_NAME;
$active_page='password';
$page_title='Change Password';
ob_start(); ?>
<div class="card mx-auto shadow-sm" style="max-width:520px">
  <div class="card-body p-4">
    <h3 class="h5 mb-3">Change Password</h3>
    <?php if($required): ?><div class="alert alert-warning">Your temporary password must be changed before continuing.</div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(BASE_URL,ENT_QUOTES,'UTF-8') ?>auth/change_password">
      <label class="form-label w-100">Current password<input name="current_password" type="password" autocomplete="current-password" required class="form-control"></label>
      <label class="form-label w-100">New password (6+ characters)<input name="new_password" type="password" autocomplete="new-password" minlength="6" required class="form-control"></label>
      <label class="form-label w-100">Confirm new password<input name="confirm_password" type="password" autocomplete="new-password" minlength="6" required class="form-control"></label>
      <button class="btn btn-primary mt-2" type="submit">Update Password</button>
    </form>
  </div>
</div>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php'; ?>
