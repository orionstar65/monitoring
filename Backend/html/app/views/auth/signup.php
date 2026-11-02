<?php
$title='Sign up - '.APP_NAME;ob_start();?>
<div class="container py-5"><div class="card mx-auto shadow-sm" style="max-width:460px"><div class="card-body p-4">
<h3>Create account</h3><p class="text-muted">Viewer access. An admin assigns monitors to your account.</p>
<?php if(!empty($error)):?><div class="alert alert-danger"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif;?>
<form method="post" action="<?= BASE_URL ?>auth/signup">
<label class="d-block mt-2">Name<input class="form-control" name="name" maxlength="150" required></label>
<label class="d-block mt-2">Username<input class="form-control" name="username" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" required></label>
<label class="d-block mt-2">Email<input class="form-control" type="email" name="email" required></label>
<label class="d-block mt-2">Password (12+ characters)<input class="form-control" type="password" name="password" minlength="12" required></label>
<button type="submit" class="btn btn-primary w-100 mt-3">Sign up</button></form>
<p class="mt-3"><a href="<?= BASE_URL ?>auth/login">Already registered? Sign in</a></p>
</div></div></div>
<?php $content=ob_get_clean();include BASEPATH.'app/views/layouts/main.php';?>