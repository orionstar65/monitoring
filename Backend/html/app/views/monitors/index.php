<?php
$title = 'Monitor IPs - ' . APP_NAME;
$active_page = 'monitors';
$page_title = 'Monitor IPs';
ob_start();
?>
<style>
.monitor-table {
    font-size: 0.9rem;
}

.monitor-table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.monitor-table td {
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
}

.monitor-table .btn-group .btn {
    margin-right: 2px;
}

.monitor-table .btn-group .btn:last-child {
    margin-right: 0;
}

.monitor-table code {
    font-size: 0.8rem;
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

.monitor-table .badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.monitor-table .text-muted {
    font-size: 0.8rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .monitor-table {
        font-size: 0.8rem;
    }
    
    .monitor-table .btn-group {
        flex-direction: column;
    }
    
    .monitor-table .btn-group .btn {
        margin-bottom: 2px;
        margin-right: 0;
    }
}
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Monitor IPs</h3>
    <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
    <a href="<?php echo BASE_URL; ?>monitors/create" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add Monitor
    </a>
    <?php endif; ?>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Monitors</h6>
                        <h4 class="mb-0"><?php echo count($monitors); ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-desktop fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Active</h6>
                        <h4 class="mb-0"><?php echo count(array_filter($monitors, function($m) { return $m['dynamic_status'] === 'active'; })); ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Inactive</h6>
                        <h4 class="mb-0"><?php echo count(array_filter($monitors, function($m) { return $m['dynamic_status'] === 'inactive'; })); ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pause-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Unique Users</h6>
                        <h4 class="mb-0"><?php echo $distinct_username_count; ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Note:</strong> Usernames can be updated by monitoring clients via API or tic events. The username field shows the current username from the client.
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover monitor-table">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>IP Address</th>
                        <th>Username</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($monitors as $monitor): 
    // Use dynamic status from controller
    $status_class = $monitor['status_class'];
    $status_text = $monitor['status_text'];
    
    // Type mapping - use the type field from database, default to 'desktop' if not set
    $type = $monitor['type'] ?: 'desktop';
    $type_class = '';
    $type_icon = '';
    switch(strtolower($type)) {
        case 'laptop':
            $type_class = 'bg-info';
            $type_icon = 'fas fa-laptop';
            break;
        case 'server':
            $type_class = 'bg-warning text-dark';
            $type_icon = 'fas fa-server';
            break;
        case 'desktop':
        default:
            $type_class = 'bg-primary';
            $type_icon = 'fas fa-desktop';
            break;
    }
    
    // Last activity - store raw timestamp for client-side conversion
    $last_activity_timestamp = $monitor['updated_at'] ? strtotime($monitor['updated_at']) : null;
    $last_activity_display = $monitor['updated_at'] ? date('M j, Y H:i', strtotime($monitor['updated_at'])) : 'Never';
?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?php echo $monitor['id']; ?></span>
                        </td>
                        <td>
                            <code><?php echo htmlspecialchars($monitor['ip_address']); ?></code>
                        </td>
                        <td>
                            <?php if ($monitor['username']): ?>
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($monitor['username']); ?>
                            <?php else: ?>
                                <span class="text-muted">No username</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $type_class; ?>">
                                <i class="<?php echo $type_icon; ?> me-1"></i><?php echo ucfirst($type); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $status_class; ?>">
                                <i class="fas fa-circle me-1"></i><?php echo $status_text; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted local-time" 
                                   data-timestamp="<?php echo $last_activity_timestamp; ?>"
                                   data-server-time="<?php echo $last_activity_display; ?>">
                                <?php echo $last_activity_display; ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                                <a href="<?php echo BASE_URL; ?>monitors/edit/<?php echo $monitor['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Edit Monitor">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo BASE_URL; ?>view/logs/<?php echo $monitor['id']; ?>" 
                                   class="btn btn-sm btn-outline-info" 
                                   title="View Logs">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                                <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                                <a href="<?php echo BASE_URL; ?>monitors/delete/<?php echo $monitor['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   title="Delete Monitor"
                                   onclick="return confirm('Are you sure you want to delete this monitor?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Convert server timestamps to client local timezone
    const timeElements = document.querySelectorAll('.local-time[data-timestamp]');
    
    timeElements.forEach(function(element) {
        const timestamp = element.getAttribute('data-timestamp');
        const serverTime = element.getAttribute('data-server-time');
        
        if (timestamp && timestamp !== 'null') {
            // Convert Unix timestamp to client local time
            const date = new Date(parseInt(timestamp) * 1000);
            
            // Format for display (e.g., "Jan 15, 2024 14:30")
            const options = {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            };
            
            const localTime = date.toLocaleString('en-US', options);
            
            // Update the display
            element.textContent = localTime;
            
            // Add tooltip showing server time for reference
            element.setAttribute('title', 'Server time: ' + serverTime);
            element.style.cursor = 'help';
        }
    });
    
    // Optional: Add timezone indicator
    const timezoneInfo = document.createElement('div');
    timezoneInfo.className = 'text-muted small mt-2';
    timezoneInfo.innerHTML = '<i class="fas fa-clock me-1"></i>Times shown in your local timezone (' + Intl.DateTimeFormat().resolvedOptions().timeZone + ')';
    
    // Add timezone info after the table
    const tableContainer = document.querySelector('.table-responsive');
    if (tableContainer) {
        tableContainer.parentNode.insertBefore(timezoneInfo, tableContainer.nextSibling);
    }
});
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 