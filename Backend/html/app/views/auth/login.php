<?php
$title = 'Login - ' . APP_NAME;
ob_start();
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h3 class="card-title"><?php echo APP_NAME; ?></h3>
                    </div>
                    
                    <?php if(!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
                    <?php if(!empty($_SESSION['success_message'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message'],ENT_QUOTES,'UTF-8') ?></div><?php unset($_SESSION['success_message']); endif; ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>auth/login">
                        <div class="mb-3">
                            <label for="identifier" class="form-label">User No</label>
                            <input type="text" class="form-control" id="identifier" name="identifier" autocomplete="username"
                                   value="<?= htmlspecialchars((string)($_POST['identifier']??''),ENT_QUOTES,'UTF-8') ?>"
                                   inputmode="numeric" pattern="[1-9][0-9]{0,14}" placeholder="User No" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Sign In</button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 