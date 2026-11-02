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
    cursor: pointer;
    user-select: none;
    position: relative;
    padding: 0.75rem 0.5rem;
    transition: background-color 0.2s ease;
}

.monitor-table th:hover {
    background-color:rgb(4, 99, 194);
}

.monitor-table th.sortable {
    padding-right: 1.5rem;
}

.monitor-table th .sort-indicator {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.7rem;
    opacity: 0.5;
    transition: opacity 0.2s ease;
}

.monitor-table th.sort-asc .sort-indicator,
.monitor-table th.sort-desc .sort-indicator {
    opacity: 1;
}

.monitor-table th.sort-asc .sort-indicator::before {
    content: '▲';
    color: #fff;
}

.monitor-table th.sort-desc .sort-indicator::before {
    content: '▼';
    color: #fff;
}

.monitor-table td {
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
}

.monitor-table tbody tr {
    transition: background-color 0.15s ease, transform 0.1s ease;
}

.monitor-table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.03);
    transform: translateX(2px);
}

.monitor-table .btn-group .btn {
    margin-right: 2px;
    transition: all 0.2s ease;
}

.monitor-table .btn-group .btn:last-child {
    margin-right: 0;
}

.monitor-table .btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.monitor-table code {
    font-size: 0.8rem;
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.monitor-table .badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.35rem 0.65rem;
}

.monitor-table .text-muted {
    font-size: 0.8rem;
}

/* Filter Panel Styles */
.filter-panel {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.filter-panel .filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #dee2e6;
}

.filter-panel .filter-header h5 {
    margin: 0;
    font-weight: 600;
    color: #495057;
    font-size: 1rem;
}

.filter-panel .filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-panel .filter-group {
    display: flex;
    flex-direction: column;
}

.filter-panel .filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.filter-panel .filter-group input,
.filter-panel .filter-group select {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.filter-panel .filter-group input:focus,
.filter-panel .filter-group select:focus {
    border-color: #80bdff;
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.filter-panel .filter-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid #dee2e6;
}

.filter-panel .btn-clear {
    background: #6c757d;
    border-color: #6c757d;
    color: white;
    transition: all 0.2s ease;
}

.filter-panel .btn-clear:hover {
    background: #5a6268;
    border-color: #545b62;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Enhanced table card */
.table-card {
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-radius: 0.5rem;
    overflow: hidden;
}

.table-card .card-body {
    padding: 0;
}

.table-card .table-responsive {
    border-radius: 0.5rem;
}

.table-card .table {
    margin-bottom: 0;
}

/* Results counter */
.results-counter {
    padding: 0.75rem 1.25rem;
    background:rgb(236, 236, 236);
    border-bottom: 1px solid #dee2e6;
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
    border: 1px solidrgb(207, 207, 207);
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
    
    .filter-panel .filter-row {
        grid-template-columns: 1fr;
    }
    
    .filter-panel .filter-actions {
        flex-direction: column;
    }
    
    .filter-panel .filter-actions .btn {
        width: 100%;
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
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Users</h6>
                        <h4 class="mb-0"><?php echo $distinct_username_count; ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
    
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Note:</strong> Usernames can be updated by monitoring clients via API or tic events. The username field shows the current username from the client.
</div>

<!-- Filter Panel -->
<div class="filter-panel">
    <div class="filter-header">
        <h5><i class="fas fa-filter me-2"></i>Filters</h5>
    </div>
    <div class="filter-row">
        <div class="filter-group">
            <label for="filter-ip">IP Address</label>
            <input type="text" id="filter-ip" class="form-control" placeholder="Filter by IP...">
        </div>
        <div class="filter-group">
            <label for="filter-username">Username</label>
            <input type="text" id="filter-username" class="form-control" placeholder="Filter by username...">
        </div>
        <div class="filter-group">
            <label for="filter-slack">Slack ID</label>
            <input type="text" id="filter-slack" class="form-control" placeholder="Filter by Slack ID...">
        </div>
        <div class="filter-group">
            <label for="filter-type">Type</label>
            <select id="filter-type" class="form-select">
                <option value="">All Types</option>
                <option value="desktop">Desktop</option>
                <option value="laptop">Laptop</option>
                <option value="server">Server</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter-platform">Platform</label>
            <select id="filter-platform" class="form-select">
                <option value="">All Platforms</option>
                <option value="windows">Windows</option>
                <option value="mac">Mac</option>
                <option value="ubuntu">Ubuntu</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter-status">Status</label>
            <select id="filter-status" class="form-select">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>
    <div class="filter-actions">
        <button type="button" class="btn btn-clear btn-sm" id="clear-filters">
            <i class="fas fa-times me-2"></i>Clear Filters
        </button>
    </div>
</div>

<div class="card table-card">
    <div class="results-counter" id="results-counter">
        Showing <span id="results-count"><?php echo count($monitors); ?></span> of <?php echo count($monitors); ?> monitors
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover monitor-table">
                <thead class="table-dark">
                    <tr>
                        <th class="sortable" data-sort="id">ID<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="ip">IP Address<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="username">Username<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="slack">Slack ID<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="type">Type<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="platform">Platform<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="status">Status<span class="sort-indicator"></span></th>
                        <th class="sortable" data-sort="activity">Last Activity<span class="sort-indicator"></span></th>
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
    
    // Platform mapping
    $platform = $monitor['platform'] ?? null;
    $platform_icon = '';
    $platform_class = 'text-muted';
    if ($platform) {
        switch(strtolower($platform)) {
            case 'windows':
                $platform_icon = 'fab fa-windows';
                $platform_class = 'text-primary';
                break;
            case 'mac':
                $platform_icon = 'fab fa-apple';
                $platform_class = 'text-dark';
                break;
            case 'ubuntu':
                $platform_icon = 'fab fa-ubuntu';
                $platform_class = 'text-danger';
                break;
        }
    }
    
    // Last activity - store raw timestamp for client-side conversion
    $last_activity_timestamp = !empty($monitor['last_monitor_tic']) ? strtotime($monitor['last_monitor_tic']) : null;
    $last_activity_display = !empty($monitor['last_monitor_tic']) ? date('M j, Y H:i', strtotime($monitor['last_monitor_tic'])) : 'Never';
?>
                    <tr data-id="<?php echo $monitor['id']; ?>"
                        data-ip="<?php echo strtolower(htmlspecialchars($monitor['ip_address'])); ?>"
                        data-username="<?php echo strtolower(htmlspecialchars($monitor['username'] ?: 'no username')); ?>"
                        data-slack="<?php echo strtolower(htmlspecialchars($monitor['slack_id'] ?: 'no slack id')); ?>"
                        data-type="<?php echo strtolower($type); ?>"
                        data-platform="<?php echo strtolower($platform ?: 'no platform'); ?>"
                        data-status="<?php echo $monitor['dynamic_status']; ?>"
                        data-activity="<?php echo $last_activity_timestamp ?: '0'; ?>">
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
                            <?php if ($monitor['slack_id']): ?>
                                <i class="fab fa-slack me-1"></i><?php echo htmlspecialchars($monitor['slack_id']); ?>
                            <?php else: ?>
                                <span class="text-muted">No Slack ID</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $type_class; ?>">
                                <i class="<?php echo $type_icon; ?> me-1"></i><?php echo ucfirst($type); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($platform): ?>
                                <span class="<?php echo $platform_class; ?>">
                                    <i class="<?php echo $platform_icon; ?> me-1"></i><?php echo htmlspecialchars($platform); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No platform</span>
                            <?php endif; ?>
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
                                <?php if (($_SESSION['role']??'')!=='viewer'): ?>
                                <a href="<?php echo BASE_URL; ?>view/logs/<?php echo $monitor['id']; ?>" 
                                   class="btn btn-sm btn-outline-info" 
                                   title="View Logs">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>monitors/delete/<?php echo (int)$monitor['id']; ?>" style="display:inline"><button type="submit" 
                                   class="btn btn-sm btn-outline-danger" 
                                   title="Delete Monitor"
                                   onclick="return confirm('Are you sure you want to delete this monitor?')">
                                    <i class="fas fa-trash"></i>
                                </button></form>
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
    // State management
    let currentSort = { column: null, direction: 'asc' };
    let allRows = Array.from(document.querySelectorAll('tbody tr'));
    let totalCount = allRows.length;
    
    // Store original index for each row to restore original order
    allRows.forEach((row, index) => {
        row.setAttribute('data-original-index', index);
    });
    
    // Filter elements
    const filterIp = document.getElementById('filter-ip');
    const filterUsername = document.getElementById('filter-username');
    const filterSlack = document.getElementById('filter-slack');
    const filterType = document.getElementById('filter-type');
    const filterPlatform = document.getElementById('filter-platform');
    const filterStatus = document.getElementById('filter-status');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const resultsCount = document.getElementById('results-count');
    const tbody = document.querySelector('tbody');
    
    // Debounce function for text inputs
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Filter function
    function applyFilters() {
        const ipValue = filterIp.value.toLowerCase().trim();
        const usernameValue = filterUsername.value.toLowerCase().trim();
        const slackValue = filterSlack.value.toLowerCase().trim();
        const typeValue = filterType.value.toLowerCase();
        const platformValue = filterPlatform.value.toLowerCase();
        const statusValue = filterStatus.value.toLowerCase();
        
        let visibleCount = 0;
        
        allRows.forEach(row => {
            const ip = row.getAttribute('data-ip') || '';
            const username = row.getAttribute('data-username') || '';
            const slack = row.getAttribute('data-slack') || '';
            const type = row.getAttribute('data-type') || '';
            const platform = row.getAttribute('data-platform') || '';
            const status = row.getAttribute('data-status') || '';
            
            let visible = true;
            
            // Apply filters
            if (ipValue && !ip.includes(ipValue)) visible = false;
            if (usernameValue && !username.includes(usernameValue)) visible = false;
            if (slackValue && !slack.includes(slackValue)) visible = false;
            if (typeValue && type !== typeValue) visible = false;
            if (platformValue && platform !== platformValue) visible = false;
            if (statusValue && status !== statusValue) visible = false;
            
            if (visible) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update results counter
        resultsCount.textContent = visibleCount;
        
        // Apply sorting to visible rows
        applySort();
    }
    
    // Sort function
    function applySort() {
        const visibleRows = allRows.filter(row => row.style.display !== 'none');
        const hiddenRows = allRows.filter(row => row.style.display === 'none');
        
        if (currentSort.column) {
            visibleRows.sort((a, b) => {
                let aVal, bVal;
                
                switch(currentSort.column) {
                    case 'id':
                        aVal = parseInt(a.getAttribute('data-id')) || 0;
                        bVal = parseInt(b.getAttribute('data-id')) || 0;
                        break;
                    case 'ip':
                        aVal = a.getAttribute('data-ip') || '';
                        bVal = b.getAttribute('data-ip') || '';
                        break;
                    case 'username':
                        aVal = a.getAttribute('data-username') || '';
                        bVal = b.getAttribute('data-username') || '';
                        break;
                    case 'slack':
                        aVal = a.getAttribute('data-slack') || '';
                        bVal = b.getAttribute('data-slack') || '';
                        break;
                    case 'type':
                        aVal = a.getAttribute('data-type') || '';
                        bVal = b.getAttribute('data-type') || '';
                        break;
                    case 'platform':
                        aVal = a.getAttribute('data-platform') || '';
                        bVal = b.getAttribute('data-platform') || '';
                        break;
                    case 'status':
                        aVal = a.getAttribute('data-status') || '';
                        bVal = b.getAttribute('data-status') || '';
                        break;
                    case 'activity':
                        aVal = parseInt(a.getAttribute('data-activity')) || 0;
                        bVal = parseInt(b.getAttribute('data-activity')) || 0;
                        break;
                    default:
                        return 0;
                }
                
                let comparison = 0;
                if (typeof aVal === 'number') {
                    comparison = aVal - bVal;
                } else {
                    comparison = aVal.localeCompare(bVal);
                }
                
                return currentSort.direction === 'asc' ? comparison : -comparison;
            });
        } else {
            // Restore original order
            visibleRows.sort((a, b) => {
                const aIndex = parseInt(a.getAttribute('data-original-index')) || 0;
                const bIndex = parseInt(b.getAttribute('data-original-index')) || 0;
                return aIndex - bIndex;
            });
        }
        
        // Reorder DOM
        tbody.innerHTML = '';
        visibleRows.forEach(row => tbody.appendChild(row));
        hiddenRows.forEach(row => tbody.appendChild(row));
        
        // Re-update time display after reordering
        updateTimeDisplay();
    }
    
    // Update sort indicators
    function updateSortIndicators() {
        document.querySelectorAll('.sortable').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
            const indicator = th.querySelector('.sort-indicator');
            if (indicator) {
                indicator.textContent = '';
            }
        });
        
        if (currentSort.column) {
            const activeTh = document.querySelector(`th[data-sort="${currentSort.column}"]`);
            if (activeTh) {
                activeTh.classList.add(currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            }
        }
    }
    
    // Sort handler
    function handleSort(column) {
        if (currentSort.column === column) {
            // Toggle direction
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            // New column, start with ascending
            currentSort.column = column;
            currentSort.direction = 'asc';
        }
        
        updateSortIndicators();
        applySort();
    }
    
    // Event listeners
    filterIp.addEventListener('input', debounce(applyFilters, 300));
    filterUsername.addEventListener('input', debounce(applyFilters, 300));
    filterSlack.addEventListener('input', debounce(applyFilters, 300));
    filterType.addEventListener('change', applyFilters);
    filterPlatform.addEventListener('change', applyFilters);
    filterStatus.addEventListener('change', applyFilters);
    
    clearFiltersBtn.addEventListener('click', function() {
        filterIp.value = '';
        filterUsername.value = '';
        filterSlack.value = '';
        filterType.value = '';
        filterPlatform.value = '';
        filterStatus.value = '';
        currentSort = { column: null, direction: 'asc' };
        updateSortIndicators();
        applyFilters();
    });
    
    // Sortable headers
    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            if (column) {
                handleSort(column);
            }
        });
    });
    
    // Convert server timestamps to client local timezone
    const timeElements = document.querySelectorAll('.local-time[data-timestamp]');
    
    function updateTimeDisplay() {
        timeElements.forEach(function(element) {
            const timestamp = element.getAttribute('data-timestamp');
            const serverTime = element.getAttribute('data-server-time');
            
            if (timestamp && timestamp !== 'null' && timestamp !== '0') {
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
    }
    
    updateTimeDisplay();
    
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