<?php
$title = 'API Logs - ' . APP_NAME;
$active_page = 'api-logs';
$page_title = 'API Request Logs';
ob_start();
?>
<style>
.api-logs-container {
    padding: 1.5rem;
    background: #fafafa;
    min-height: calc(100vh - 120px);
}

.filter-panel {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.filter-panel h5 {
    margin-bottom: 1.5rem;
    color: #1976d2;
    font-weight: 600;
}

.filter-row {
    margin-bottom: 1rem;
}

.filter-row label {
    font-weight: 500;
    font-size: 0.875rem;
    color: #424242;
    margin-bottom: 0.5rem;
}

.filter-row .form-control,
.filter-row .form-select {
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    padding: 0.625rem 1rem;
    transition: all 0.2s ease;
}

.filter-row .form-control:focus,
.filter-row .form-select:focus {
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.logs-table-container {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.logs-table {
    width: 100%;
}

.logs-table thead th {
    background: #f5f5f5;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #424242;
    border-bottom: 2px solid #e0e0e0;
    padding: 1rem;
    cursor: pointer;
    user-select: none;
}

.logs-table thead th:hover {
    background: #eeeeee;
}

.logs-table thead th.sortable::after {
    content: ' ↕';
    opacity: 0.5;
    font-size: 0.75rem;
}

.logs-table thead th.sort-asc::after {
    content: ' ↑';
    opacity: 1;
}

.logs-table thead th.sort-desc::after {
    content: ' ↓';
    opacity: 1;
}

.logs-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.logs-table tbody tr:hover {
    background: #f9f9f9;
}

.badge-request-type {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    font-weight: 500;
}

.badge-request-type.tic { background: #e3f2fd; color: #1976d2; }
.badge-request-type.browserhistory { background: #f3e5f5; color: #7b1fa2; }
.badge-request-type.keylog { background: #fff3e0; color: #e65100; }
.badge-request-type.usblog { background: #e8f5e9; color: #2e7d32; }
.badge-request-type.screenshot { background: #fce4ec; color: #c2185b; }
.badge-request-type.username { background: #e0f2f1; color: #00695c; }
.badge-request-type.config { background: #e1f5fe; color: #0277bd; }
.badge-request-type.config_update { background: #fff9c4; color: #f57f17; }

.badge-status {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    font-weight: 500;
}

.badge-status.success { background: #4caf50; color: white; }
.badge-status.warning { background: #ff9800; color: white; }
.badge-status.error { background: #f44336; color: white; }
.badge-status.info { background: #2196f3; color: white; }

.no-data-message {
    text-align: center;
    padding: 4rem 2rem;
    color: #757575;
}

.no-data-message i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.loading-spinner {
    text-align: center;
    padding: 4rem 2rem;
}

.pagination-info {
    font-size: 0.875rem;
    color: #757575;
    margin-top: 1rem;
}

.btn-export {
    margin-left: auto;
}
</style>

<div class="api-logs-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-list-alt me-2"></i>API Request Logs</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-danger" id="btnDeleteOld">
                <i class="fas fa-trash me-2"></i>Delete Old Logs (2+ months)
            </button>
            <button class="btn btn-primary btn-export" id="btnExport">
                <i class="fas fa-download me-2"></i>Export CSV
            </button>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <h5><i class="fas fa-filter me-2"></i>Filters</h5>
        <div class="row">
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterDate">Date</label>
                    <input type="date" class="form-control" id="filterDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterEndpoint">Endpoint</label>
                    <select class="form-select" id="filterEndpoint">
                        <option value="All">All Endpoints</option>
                        <option value="/eventhandler.php">eventhandler.php</option>
                        <option value="/webapi.php">webapi.php</option>
                        <option value="/webapi_config.php">webapi_config.php</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterRequestType">Request Type</label>
                    <select class="form-select" id="filterRequestType">
                        <option value="All">All Types</option>
                        <option value="Tic">Tic</option>
                        <option value="BrowserHistory">Browser History</option>
                        <option value="KeyLog">Key Log</option>
                        <option value="USBLog">USB Log</option>
                        <option value="screenshot">Screenshot</option>
                        <option value="username">Username</option>
                        <option value="config">Config</option>
                        <option value="config_update">Config Update</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterIP">IP Address</label>
                    <input type="text" class="form-control" id="filterIP" placeholder="Search IP...">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterStatusCode">Status Code</label>
                    <select class="form-select" id="filterStatusCode">
                        <option value="All">All Status Codes</option>
                        <option value="200">200 (Success)</option>
                        <option value="400">400 (Bad Request)</option>
                        <option value="403">403 (Forbidden)</option>
                        <option value="500">500 (Server Error)</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterMinResponseTime">Min Response Time (ms)</label>
                    <input type="number" class="form-control" id="filterMinResponseTime" placeholder="0" step="0.01">
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-row">
                    <label for="filterMaxResponseTime">Max Response Time (ms)</label>
                    <input type="number" class="form-control" id="filterMaxResponseTime" placeholder="10000" step="0.01">
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-row">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary w-100" id="btnApplyFilters">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Table Container -->
    <div class="logs-table-container">
        <div id="loadingSpinner" class="loading-spinner" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading logs...</p>
        </div>
        
        <div id="noDataMessage" class="no-data-message" style="display: none;">
            <i class="fas fa-inbox"></i>
            <h5>No Data Available</h5>
            <p id="noDataText">No logs found for the selected date and filters.</p>
        </div>

        <div id="logsTableWrapper" style="display: none;">
            <div class="table-responsive">
                <table class="table logs-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="created_at">Timestamp</th>
                            <th class="sortable" data-sort="endpoint">Endpoint</th>
                            <th class="sortable" data-sort="method">Method</th>
                            <th class="sortable" data-sort="request_type">Request Type</th>
                            <th class="sortable" data-sort="ip_address">IP Address</th>
                            <th class="sortable" data-sort="status_code">Status</th>
                            <th class="sortable" data-sort="response_time_ms">Response Time</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="pagination-info" id="paginationInfo"></div>
                <nav>
                    <ul class="pagination mb-0" id="pagination">
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentSortBy = 'created_at';
let currentSortOrder = 'DESC';
let currentFilters = {};

function loadLogs(page = 1) {
    currentPage = page;
    
    // Show loading
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('noDataMessage').style.display = 'none';
    document.getElementById('logsTableWrapper').style.display = 'none';
    
    // Get filter values
    const filters = {
        date: document.getElementById('filterDate').value,
        endpoint: document.getElementById('filterEndpoint').value,
        request_type: document.getElementById('filterRequestType').value,
        ip_address: document.getElementById('filterIP').value,
        status_code: document.getElementById('filterStatusCode').value,
        min_response_time: document.getElementById('filterMinResponseTime').value,
        max_response_time: document.getElementById('filterMaxResponseTime').value,
        page: page,
        per_page: 50,
        sort_by: currentSortBy,
        sort_order: currentSortOrder
    };
    
    currentFilters = filters;
    
    // Build query string
    const queryParams = new URLSearchParams();
    Object.keys(filters).forEach(key => {
        if (filters[key] !== '' && filters[key] !== 'All') {
            queryParams.append(key, filters[key]);
        }
    });
    
    // Fetch logs
    fetch('<?php echo BASE_URL; ?>apilogs/getLogs?' + queryParams.toString())
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingSpinner').style.display = 'none';
            
            if (!data.success || data.total === 0) {
                document.getElementById('noDataMessage').style.display = 'block';
                document.getElementById('noDataText').textContent = data.message || 'No logs found for the selected date and filters.';
                return;
            }
            
            // Display logs
            displayLogs(data.data);
            displayPagination(data);
            document.getElementById('logsTableWrapper').style.display = 'block';
        })
        .catch(error => {
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('noDataMessage').style.display = 'block';
            document.getElementById('noDataText').textContent = 'Error loading logs: ' + error.message;
            console.error('Error:', error);
        });
}

function displayLogs(logs) {
    const tbody = document.getElementById('logsTableBody');
    tbody.innerHTML = '';
    
    logs.forEach(log => {
        const row = document.createElement('tr');
        
        // Format timestamp
        const timestamp = new Date(log.created_at).toLocaleString();
        
        // Format request type badge
        const requestTypeClass = log.request_type ? log.request_type.toLowerCase().replace('_', '') : 'unknown';
        const requestTypeBadge = log.request_type 
            ? `<span class="badge badge-request-type ${requestTypeClass}">${log.request_type}</span>`
            : '<span class="text-muted">-</span>';
        
        // Format status code badge
        let statusClass = 'info';
        if (log.status_code >= 200 && log.status_code < 300) statusClass = 'success';
        else if (log.status_code >= 400 && log.status_code < 500) statusClass = 'warning';
        else if (log.status_code >= 500) statusClass = 'error';
        
        const statusBadge = `<span class="badge badge-status ${statusClass}">${log.status_code}</span>`;
        
        // Format response time
        const responseTime = parseFloat(log.response_time_ms).toFixed(2) + ' ms';
        
        row.innerHTML = `
            <td>${timestamp}</td>
            <td><code>${log.endpoint || '-'}</code></td>
            <td><span class="badge bg-secondary">${log.method || '-'}</span></td>
            <td>${requestTypeBadge}</td>
            <td><code>${log.ip_address || '-'}</code></td>
            <td>${statusBadge}</td>
            <td>${responseTime}</td>
        `;
        
        tbody.appendChild(row);
    });
}

function displayPagination(data) {
    const paginationInfo = document.getElementById('paginationInfo');
    const pagination = document.getElementById('pagination');
    
    const start = (data.page - 1) * data.per_page + 1;
    const end = Math.min(data.page * data.per_page, data.total);
    paginationInfo.textContent = `Showing ${start} to ${end} of ${data.total} logs`;
    
    pagination.innerHTML = '';
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${data.page === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" data-page="${data.page - 1}">Previous</a>`;
    pagination.appendChild(prevLi);
    
    // Page numbers
    const maxPages = Math.min(data.total_pages, 10);
    const startPage = Math.max(1, data.page - 5);
    const endPage = Math.min(data.total_pages, startPage + maxPages - 1);
    
    for (let i = startPage; i <= endPage; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === data.page ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
        pagination.appendChild(li);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${data.page >= data.total_pages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" data-page="${data.page + 1}">Next</a>`;
    pagination.appendChild(nextLi);
    
    // Add click handlers
    pagination.querySelectorAll('a.page-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(link.getAttribute('data-page'));
            if (page > 0 && page <= data.total_pages) {
                loadLogs(page);
            }
        });
    });
}

function handleSort(column) {
    if (currentSortBy === column) {
        currentSortOrder = currentSortOrder === 'ASC' ? 'DESC' : 'ASC';
    } else {
        currentSortBy = column;
        currentSortOrder = 'DESC';
    }
    
    // Update sort indicators
    document.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.getAttribute('data-sort') === currentSortBy) {
            th.classList.add(currentSortOrder === 'ASC' ? 'sort-asc' : 'sort-desc');
        }
    });
    
    loadLogs(1);
}

function exportToCSV() {
    if (!currentFilters || Object.keys(currentFilters).length === 0) {
        alert('Please load logs first');
        return;
    }
    
    // Build query string for export (get all records, no pagination)
    const queryParams = new URLSearchParams();
    Object.keys(currentFilters).forEach(key => {
        if (key !== 'page' && key !== 'per_page' && currentFilters[key] !== '' && currentFilters[key] !== 'All') {
            queryParams.append(key, currentFilters[key]);
        }
    });
    queryParams.append('per_page', '10000'); // Get all records
    queryParams.append('export', 'csv');
    
    window.location.href = '<?php echo BASE_URL; ?>apilogs/getLogs?' + queryParams.toString();
}

// Event listeners
document.getElementById('btnApplyFilters').addEventListener('click', () => {
    loadLogs(1);
});

document.querySelectorAll('th.sortable').forEach(th => {
    th.addEventListener('click', () => {
        handleSort(th.getAttribute('data-sort'));
    });
});

document.getElementById('btnExport').addEventListener('click', exportToCSV);

document.getElementById('btnDeleteOld').addEventListener('click', () => {
    if (!confirm('Are you sure you want to delete all API log tables older than 2 months? This action cannot be undone.')) {
        return;
    }
    
    if (!confirm('This will permanently delete all log data from tables older than 2 months. Are you absolutely sure?')) {
        return;
    }
    
    // Disable button and show loading
    const btn = document.getElementById('btnDeleteOld');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
    
    fetch('<?php echo BASE_URL; ?>apilogs/deleteOldTables', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert(`Successfully deleted ${data.deleted_count} log table(s) older than 2 months.`);
            // Reload logs to refresh the view
            loadLogs(1);
        } else {
            alert('Error: ' + (data.message || 'Failed to delete old logs'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error: ' + error.message);
    });
});

// Load logs on page load
document.addEventListener('DOMContentLoaded', () => {
    loadLogs(1);
});
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?>

