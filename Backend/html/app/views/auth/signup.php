<?php
$title='Account registration unavailable - '.APP_NAME;
$page_title='Account registration';
ob_start();
?>
<div class="card mx-auto" style="max-width:540px">
 <div class="card-body p-4">
  <h5>Account registration is administrator-managed</h5>
  <p class="text-muted mb-3">Ask an administrator to create an account and assign a User No.</p>
  <a class="btn btn-primary" href="<?= BASE_URL ?>auth/login">Return to sign in</a>
 </div>
</div>
<?php $content=ob_get_clean(); include BASEPATH.'app/views/layouts/main.php';