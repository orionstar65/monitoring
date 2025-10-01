<?php
$title = 'Dashboard - ' . APP_NAME;
$active_page = 'dashboard';
$page_title = 'Dashboard';
ob_start();
?>
<?php if (isset($syncedCount) && $syncedCount > 0): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-sync-alt me-2"></i>
    <strong>Data Sync Complete!</strong> <?php echo $syncedCount; ?> new monitor(s) were discovered and added to the database.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Total Users</h5>
                        <h2 class="card-text"><?php echo $totalUsers; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Total Monitors</h5>
                        <h2 class="card-text"><?php echo $totalMonitors; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-desktop fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Active Monitors</h5>
                        <h2 class="card-text"><?php echo $activeMonitors; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 