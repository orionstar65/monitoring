<?php
$title = 'Edit Monitor - ' . APP_NAME;
$active_page = 'monitors';
$page_title = 'Edit Monitor - ' . htmlspecialchars($monitor['ip_address']);
ob_start();
?>
<style>
.readonly-field {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #6c757d;
}

.readonly-field:focus {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    box-shadow: none;
}

.monitor-info-card {
    border-left: 4px solid #007bff;
}

.type-selector {
    border-left: 4px solid #28a745;
}
</style>

<div class="row">
    <div class="col-md-8">
        <div class="card monitor-info-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Monitor Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="ip_address" class="form-label">
                                <i class="fas fa-network-wired me-1"></i>IP Address
                            </label>
                            <input type="text" class="form-control readonly-field" id="ip_address" 
                                   value="<?php echo htmlspecialchars($monitor['ip_address']); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="mac_address" class="form-label">
                                <i class="fas fa-ethernet me-1"></i>MAC Address
                            </label>
                            <input type="text" class="form-control readonly-field" id="mac_address" 
                                   value="<?php echo htmlspecialchars($monitor['mac_address'] ?: 'Not available'); ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" class="form-control readonly-field" id="username" 
                                   value="<?php echo htmlspecialchars($monitor['username'] ?: 'No username set'); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">
                                <i class="fas fa-circle me-1"></i>Status
                            </label>
                            <input type="text" class="form-control readonly-field" id="status" 
                                   value="<?php 
                                   $last_activity = strtotime($monitor['updated_at']);
                                   $time_diff = time() - $last_activity;
                                   echo ($time_diff <= 300) ? 'Active' : 'Inactive'; 
                                   ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="created_at" class="form-label">
                                <i class="fas fa-calendar-plus me-1"></i>Created
                            </label>
                            <input type="text" class="form-control readonly-field local-time" id="created_at" 
                                   data-timestamp="<?php echo strtotime($monitor['created_at']); ?>"
                                   value="<?php echo date('M j, Y H:i', strtotime($monitor['created_at'])); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="updated_at" class="form-label">
                                <i class="fas fa-calendar-check me-1"></i>Last Updated
                            </label>
                            <input type="text" class="form-control readonly-field local-time" id="updated_at" 
                                   data-timestamp="<?php echo strtotime($monitor['updated_at']); ?>"
                                   value="<?php echo date('M j, Y H:i', strtotime($monitor['updated_at'])); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card type-selector">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Edit Monitor Settings
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo BASE_URL; ?>monitors/edit/<?php echo $monitor['id']; ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($monitor['username'] ?: ''); ?>"
                               placeholder="Enter username (optional)">
                        <div class="form-text">
                            Set or update the username for this monitor.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slack_id" class="form-label">
                            <i class="fab fa-slack me-1"></i>Slack ID
                        </label>
                        <input type="text" class="form-control" id="slack_id" name="slack_id" 
                               value="<?php echo htmlspecialchars($monitor['slack_id'] ?: ''); ?>"
                               placeholder="Enter Slack ID (optional)">
                        <div class="form-text">
                            Slack ID for grouping notifications. Leave empty to use username.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">
                            <i class="fas fa-desktop me-1"></i>Device Type
                        </label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="desktop" <?php echo $monitor['type'] === 'desktop' ? 'selected' : ''; ?>>
                                <i class="fas fa-desktop"></i> Desktop
                            </option>
                            <option value="laptop" <?php echo $monitor['type'] === 'laptop' ? 'selected' : ''; ?>>
                                <i class="fas fa-laptop"></i> Laptop
                            </option>
                            <option value="server" <?php echo $monitor['type'] === 'server' ? 'selected' : ''; ?>>
                                <i class="fas fa-server"></i> Server
                            </option>
                        </select>
                        <div class="form-text">
                            Select the type of device this monitor represents.
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Monitor
                        </button>
                        <a href="<?php echo BASE_URL; ?>monitors" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Monitors
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Convert server timestamps to client local timezone
    const timeElements = document.querySelectorAll('.local-time[data-timestamp]');
    
    timeElements.forEach(function(element) {
        const timestamp = element.getAttribute('data-timestamp');
        const serverTime = element.value; // Current value is server time
        
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
            element.value = localTime;
            
            // Add tooltip showing server time for reference
            element.setAttribute('title', 'Server time: ' + serverTime);
        }
    });
    
    // Add timezone indicator
    const timezoneInfo = document.createElement('div');
    timezoneInfo.className = 'text-muted small mt-3';
    timezoneInfo.innerHTML = '<i class="fas fa-clock me-1"></i>Times shown in your local timezone (' + Intl.DateTimeFormat().resolvedOptions().timeZone + ')';
    
    // Add timezone info after the form
    const form = document.querySelector('form');
    if (form) {
        form.parentNode.insertBefore(timezoneInfo, form.nextSibling);
    }
});
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 