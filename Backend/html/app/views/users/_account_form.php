<?php
/** Shared user identity editor. $userFormMode = create|edit. */
$userFormMode=$userFormMode??'create';
$isEditing=$userFormMode==='edit';
$superAdmin=($_SESSION['role']??'')===ROLE_SUPER_ADMIN;
$e=static fn($value)=>htmlspecialchars((string)($value??''),ENT_QUOTES,'UTF-8');
$current=$user??[];
$number=$_POST['user_no']??($current['user_no']??'');
$name=$_POST['name']??($current['name']??'');
$role=$_POST['role']??($current['role']??ROLE_USER);
?>
<div class="container-fluid px-0" style="max-width:900px">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div><h4 class="mb-1"><?= $isEditing?'Edit user':'Create user' ?></h4>
            <p class="small text-muted mb-0"><?= $isEditing?'Update account information and access.':'Set the identity and access for a new account.' ?></p></div>
        <a href="<?= BASE_URL ?>users" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Users</a>
    </div>
    <?php if(!empty($error)): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= BASE_URL ?>users/<?= $isEditing?'edit/'.(int)($current['id']??0):'create' ?>" autocomplete="off">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent py-2"><strong>Account information</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="user_no" class="form-label">User No <span class="text-danger">*</span></label>
                        <input type="text" id="user_no" name="user_no" class="form-control font-monospace"
                            inputmode="numeric" pattern="[1-9][0-9]{0,14}" maxlength="15" required
                            value="<?= $e($number) ?>" placeholder="Unique numeric User No">
                    </div>
                    <div class="col-md-7">
                        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="150" required
                            value="<?= $e($name) ?>" placeholder="Full name">
                    </div>
                    <div class="col-md-6">
                        <label for="role" class="form-label">Role</label>
                        <?php if($superAdmin): ?>
                        <select id="role" name="role" class="form-select">
                            <option value="<?= ROLE_USER ?>" <?= $role===ROLE_USER?'selected':'' ?>>User</option>
                            <option value="<?= ROLE_SCOPED_ADMIN ?>" <?= $role===ROLE_SCOPED_ADMIN?'selected':'' ?>>Admin (assigned users only)</option>
                            <option value="<?= ROLE_SUPER_ADMIN ?>" <?= $role===ROLE_SUPER_ADMIN?'selected':'' ?>>Super Admin</option>
                        </select>
                        <?php else: ?>
                        <input type="text" id="role" class="form-control" value="User" readonly>
                        <?php endif; ?>
                        <div class="form-text">Admin sees assigned users and their monitors. Super Admin manages all accounts and destructive actions.</div>
                    </div>
                    <?php if($superAdmin): ?>
                    <div class="col-md-6">
                        <label for="password" class="form-label"><?= $isEditing?'New password (optional)':'Password' ?><?= $isEditing?'':' *' ?></label>
                        <input type="password" id="password" name="password" class="form-control"
                            minlength="6" maxlength="1024" autocomplete="new-password" <?= $isEditing?'':'required' ?>>
                        <div class="form-text"><?= $isEditing?'Leave empty to keep the existing password.':'Minimum six characters.' ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mb-3">
            <a href="<?= BASE_URL ?>users" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas <?= $isEditing?'fa-save':'fa-user-plus' ?> me-1"></i><?= $isEditing?'Save changes':'Create user' ?></button>
        </div>
    </form>
</div>
