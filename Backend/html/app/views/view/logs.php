<?php
$title = 'Log Management - ' . APP_NAME;
$active_page = 'logs';
$page_title = 'Log Management';
ob_start();
?>
<style>
/* Log Management Styles */
:root {
    --log-primary: #1976d2;
    --log-primary-dark: #1565c0;
    --log-success: #4caf50;
    --log-warning: #ff9800;
    --log-error: #f44336;
    --log-surface: #ffffff;
    --log-background: #fafafa;
    --log-text-primary: #212121;
    --log-text-secondary: #757575;
    --log-divider: #e0e0e0;
    --log-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    --log-shadow-hover: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* Override main layout constraints for log management page */
.content-area {
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Log Management Dashboard Layout */
.log-dashboard-container {
    display: flex;
    height: calc(100vh - 120px);
    gap: 1.5rem;
    padding: 1.5rem;
    background: var(--log-background);
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}

.log-sidebar-panel {
    width: 360px;
    min-width: 320px;
    max-width: 400px;
    background: var(--log-surface);
    border-radius: 16px;
    box-shadow: var(--log-shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex-shrink: 0;
}

.log-main-content {
    flex: 1;
    min-width: 0;
    background: var(--log-surface);
    border-radius: 16px;
    box-shadow: var(--log-shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Log Sidebar */
.log-sidebar-panel .sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--log-divider);
    background: var(--log-surface);
}

.log-sidebar-panel .sidebar-header h6 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--log-text-primary);
    margin-bottom: 1rem;
}

.log-sidebar-panel .filter-container {
    margin-top: 0.75rem;
}

.log-sidebar-panel .filter-container .form-control {
    border-radius: 12px;
    border: 2px solid var(--log-divider);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.log-sidebar-panel .filter-container .form-control:focus {
    border-color: var(--log-primary);
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.log-sidebar-panel .sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.log-sidebar-panel .monitor-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--log-divider);
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--log-surface);
    position: relative;
}

.log-sidebar-panel .monitor-item:hover {
    background: rgba(25, 118, 210, 0.05);
    transform: translateX(4px);
}

.log-sidebar-panel .monitor-item.selected {
    background: rgba(25, 118, 210, 0.1);
    border-left: 4px solid var(--log-primary);
}

.log-sidebar-panel .monitor-item.selected::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--log-primary);
}

.log-sidebar-panel .monitor-item-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.log-sidebar-panel .monitor-item-name {
    font-weight: 600;
    color: var(--log-text-primary);
    flex: 1;
    font-size: 0.95rem;
}

.log-sidebar-panel .monitor-item-status {
    margin-left: auto;
}

.log-sidebar-panel .monitor-item-username {
    font-size: 0.8rem;
    color: var(--log-text-secondary);
    margin-left: 1.5rem;
    line-height: 1.3;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Log Main Content */
.log-main-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--log-divider);
    background: var(--log-surface);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.log-main-header h6 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--log-text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.log-main-content-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

/* Log Tabs */
.log-tabs {
    border-bottom: 1px solid var(--log-divider);
    margin-bottom: 1.5rem;
}

.log-tabs .nav-link {
    border: none;
    border-radius: 8px 8px 0 0;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    color: var(--log-text-secondary);
    transition: all 0.2s ease;
}

.log-tabs .nav-link:hover {
    color: var(--log-primary);
    background: rgba(25, 118, 210, 0.05);
}

.log-tabs .nav-link.active {
    color: var(--log-primary);
    background: var(--log-surface);
    border-bottom: 2px solid var(--log-primary);
}

/* Log Table */
.log-table {
    font-size: 0.875rem;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--log-shadow);
}

.log-table th {
    font-size: 0.8rem;
    padding: 1rem;
    background: var(--log-primary);
    color: white;
    font-weight: 600;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.log-table td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-color: var(--log-divider);
}

.log-table tbody tr:hover {
    background: rgba(25, 118, 210, 0.05);
}

.log-table .badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
}

/* Log Filters */
.log-filters {
    background: var(--log-background);
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.log-filters .form-control,
.log-filters .form-select {
    border-radius: 8px;
    border: 2px solid var(--log-divider);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.log-filters .form-control:focus,
.log-filters .form-select:focus {
    border-color: var(--log-primary);
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

/* Log Pagination */
.log-pagination {
    padding: 1rem;
    background: var(--log-background);
    border-top: 1px solid var(--log-divider);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 0 0 12px 12px;
}

.log-pagination .pagination {
    margin: 0;
}

.log-pagination .page-link {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 8px;
    margin: 0 0.125rem;
    border: 1px solid var(--log-divider);
    color: var(--log-text-primary);
    transition: all 0.2s ease;
}

.log-pagination .page-item.active .page-link {
    background-color: var(--log-primary);
    border-color: var(--log-primary);
    color: white;
}

.log-pagination .page-item.disabled .page-link {
    color: var(--log-text-secondary);
    background-color: var(--log-surface);
    border-color: var(--log-divider);
}

.log-pagination-info {
    font-size: 0.875rem;
    color: var(--log-text-secondary);
    font-weight: 500;
}

/* Empty States */
.log-empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--log-text-secondary);
}

.log-empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--log-primary);
}

.log-empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--log-text-primary);
}

/* Loading States */
.log-loading-container {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: var(--log-text-secondary);
    flex-direction: column;
    gap: 1rem;
}

.log-loading-container i {
    font-size: 2rem;
    color: var(--log-primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .log-dashboard-container {
        flex-direction: column;
        height: auto;
        gap: 1rem;
        padding: 1rem;
    }
    
    .log-sidebar-panel {
        width: 100%;
        min-width: auto;
        max-width: none;
        max-height: 300px;
    }
    
    .log-main-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .log-filters {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Status Badges */
.live-status-badge {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.status-online {
    background: linear-gradient(135deg, var(--log-success), #66bb6a);
    color: white;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.status-inactive {
    background: linear-gradient(135deg, var(--log-warning), #ffb74d);
    color: white;
    box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}

.status-offline {
    background: linear-gradient(135deg, var(--log-error), #ef5350);
    color: white;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
}
</style>

<!-- Log Management Dashboard Container -->
<div class="log-dashboard-container">
    <!-- Left Sidebar - Monitor List -->
    <div class="log-sidebar-panel">
        <div class="sidebar-header">
            <h6>
                <i class="fas fa-list me-2"></i>
                Select Monitor
            </h6>
            <div class="filter-container">
                <input type="text" class="form-control" id="monitorFilter" placeholder="Filter by username, type, or IP...">
            </div>
        </div>
        <div class="sidebar-content" id="monitorList">
            <!-- Monitor items will be loaded here -->
        </div>
    </div>
    
    <!-- Right Main Content - Logs -->
    <div class="log-main-content">
        <div class="log-main-header">
            <h6>
                <i class="fas fa-file-alt me-2"></i>
                Log Data
                <span class="badge bg-secondary ms-2" id="logCount">0</span>
            </h6>
            <div class="d-flex align-items-center gap-3">
                <div id="logDateFilterContainer"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0">Per page:</label>
                    <select class="form-select" id="perPageSelect" style="width: auto;">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="log-main-content-area">
            <div id="logsContainer">
                <div class="log-loading-container">
                    <i class="fas fa-spinner fa-spin"></i>
                    <div>Select a monitor to view logs...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be available
function waitForJQuery(callback) {
    if (typeof jQuery !== 'undefined') {
        callback();
    } else {
        setTimeout(function() {
            waitForJQuery(callback);
        }, 100);
    }
}

let currentPage = 1;
let currentPerPage = 20;
let selectedMonitor = null;
let allMonitors = [];
let filteredMonitors = [];
let currentLogType = 'browser_history';

// Initialize when jQuery is ready
waitForJQuery(function() {
    // Load initial data
    loadMonitors();
    
    // Event handlers
    setupEventHandlers();
});

function setupEventHandlers() {
    // Monitor filter
    $('#monitorFilter').on('input', function() {
        filterMonitors();
    });
    
    // Per-page selector
    $('#perPageSelect').change(function() {
        currentPerPage = parseInt($(this).val());
        if (selectedMonitor) {
            loadLogs(selectedMonitor.id, 1);
        }
    });
    
    // Monitor selection
    $(document).on('click', '.monitor-item', function() {
        const monitorId = parseInt($(this).data('monitor-id'));
        selectMonitor(monitorId);
    });
}

function filterMonitors() {
    const filterText = $('#monitorFilter').val().toLowerCase().trim();
    
    if (filterText === '') {
        filteredMonitors = [...allMonitors];
    } else {
        filteredMonitors = allMonitors.filter(monitor => {
            const username = (monitor.username || '').toLowerCase();
            const type = (monitor.type || '').toLowerCase();
            const ip = monitor.ip_address.toLowerCase();
            
            return username.includes(filterText) || 
                   type.includes(filterText) ||
                   ip.includes(filterText);
        });
    }
    
    updateMonitorList(filteredMonitors);
}

function loadMonitors() {
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_live_status",
        method: "GET",
        success: function(data) {
            allMonitors = data;
            filteredMonitors = [...data];
            updateMonitorList(filteredMonitors);
        },
        error: function(xhr, status, error) {
            console.log("Failed to load monitors:", error);
        }
    });
}

function updateMonitorList(monitors) {
    let html = '';
    
    monitors.forEach(function(monitor) {
        const isSelected = selectedMonitor && selectedMonitor.id === monitor.id;
        
        // Use the status calculated by the backend
        let statusText = monitor.status_text || 'Offline';
        let statusClass = monitor.live_status || 'offline';
        
        html += `
            <div class="monitor-item ${isSelected ? 'selected' : ''}" data-monitor-id="${monitor.id}">
                <div class="monitor-item-header">
                    <div class="monitor-item-name">${monitor.username}</div>
                    <div class="monitor-item-status">
                        <span class="live-status-badge status-${statusClass}">
                            <i class="fas fa-circle"></i>
                            <span>${statusText}</span>
                        </span>
                    </div>
                </div>
                ${monitor.type && monitor.type !== 'Unknown' ? 
                    `<div class="monitor-item-username">
                        <i class="fas fa-desktop"></i>
                        <span>${monitor.type} - ${monitor.ip_address}</span>
                    </div>` : ''}
            </div>
        `;
    });
    
    $('#monitorList').html(html);
}

function selectMonitor(monitorId) {
    // Find monitor
    const monitor = allMonitors.find(m => m.id === monitorId);
    if (!monitor) return;
    
    selectedMonitor = monitor;
    
    // Update UI
    $('.monitor-item').removeClass('selected');
    $(`.monitor-item[data-monitor-id="${monitorId}"]`).addClass('selected');
    
    // Load logs for this monitor
    loadLogs(monitorId, 1);
    
    // Setup date filter
    setupDateFilter();
}

function setupDateFilter() {
    const today = new Date().toISOString().split('T')[0];
    const dateFilterHtml = `
        <form id="logDateFilterForm" class="d-flex flex-wrap align-items-center gap-2 mb-0">
            <label for="logStartDate" class="form-label mb-0">Start:</label>
            <input type="date" id="logStartDate" name="start_date" class="form-control form-control-sm" style="width: 140px;" value="${today}">
            <label for="logEndDate" class="form-label mb-0">End:</label>
            <input type="date" id="logEndDate" name="end_date" class="form-control form-control-sm" style="width: 140px;" value="${today}">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <button type="button" id="logClearDateFilter" class="btn btn-sm btn-secondary">Clear</button>
        </form>
    `;
    $('#logDateFilterContainer').html(dateFilterHtml);
    
    // Setup date filter handlers
    $('#logDateFilterForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        if (selectedMonitor) {
            loadLogs(selectedMonitor.id, 1);
        }
    });
    $('#logClearDateFilter').off('click').on('click', function() {
        $('#logStartDate').val('');
        $('#logEndDate').val('');
        if (selectedMonitor) {
            loadLogs(selectedMonitor.id, 1);
        }
    });
}

function loadLogs(monitorId, page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: currentPerPage,
        log_type: currentLogType
    });
    
    // Add date filter parameters if they exist
    const startDate = $('#logStartDate').val();
    const endDate = $('#logEndDate').val();
    if (startDate) {
        params.append('start_date', startDate);
    }
    if (endDate) {
        params.append('end_date', endDate);
    }
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_logs_paginated/" + monitorId + "?" + params.toString(),
        method: "GET",
        success: function(response) {
            displayLogs(response);
        },
        error: function(xhr, status, error) {
            console.log("Failed to load logs:", error);
            $('#logsContainer').html(`
                <div class="log-empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Logs</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function displayLogs(response) {
    const container = $('#logsContainer');
    
    if (!response.logs || response.logs.length === 0) {
        container.html(`
            <div class="log-empty-state">
                <i class="fas fa-file-alt"></i>
                <h5>No Log Entries</h5>
                <p>No log entries found for the selected monitor and filters.</p>
            </div>
        `);
        $('#logCount').text("0");
        return;
    }
    
    // Update log count
    $('#logCount').text(response.pagination.total_entries);
    
    // Create tabs for different log types
    const tabsHtml = `
        <ul class="nav nav-tabs log-tabs" id="logTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link ${currentLogType === 'browser_history' ? 'active' : ''}" 
                        onclick="switchLogType('browser_history')" type="button">
                    <i class="fas fa-globe me-2"></i>
                    Browser History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link ${currentLogType === 'key_logs' ? 'active' : ''}" 
                        onclick="switchLogType('key_logs')" type="button">
                    <i class="fas fa-keyboard me-2"></i>
                    Key Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link ${currentLogType === 'usb_logs' ? 'active' : ''}" 
                        onclick="switchLogType('usb_logs')" type="button">
                    <i class="fas fa-usb me-2"></i>
                    USB Logs
                </button>
            </li>
        </ul>
    `;
    
    // Create log table
    const tableHtml = createLogTable(response.logs, response.log_type);
    
    // Create pagination
    const paginationHtml = createLogPagination(response.pagination);
    
    container.html(tabsHtml + tableHtml + paginationHtml);
}

function switchLogType(logType) {
    currentLogType = logType;
    
    // Update active tab
    $('.log-tabs .nav-link').removeClass('active');
    $(`.log-tabs .nav-link[onclick="switchLogType('${logType}')"]`).addClass('active');
    
    // Reload logs
    if (selectedMonitor) {
        loadLogs(selectedMonitor.id, 1);
    }
}

function createLogTable(logs, logType) {
    let headers = [];
    let tableRows = '';
    
    // Define headers based on log type
    switch (logType) {
        case 'browser_history':
            headers = ['Date', 'Browser', 'URL', 'Title'];
            break;
        case 'key_logs':
            headers = ['Date', 'Application', 'Key', 'Action'];
            break;
        case 'usb_logs':
            headers = ['Date', 'Device', 'Action', 'Details'];
            break;
    }
    
    // Create table rows
    logs.forEach(function(log) {
        tableRows += '<tr>';
        headers.forEach(function(header) {
            const key = header.toLowerCase().replace(/\s+/g, '');
            let value = log[key] || '';
            
            // Format value based on type
            if (key === 'url' && value.length > 50) {
                value = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 200px;">${value.substring(0, 47)}...</a>`;
            } else if (key === 'url') {
                value = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 40) {
                value = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 150px;">${value.substring(0, 37)}...</span>`;
            } else if (key === 'key') {
                value = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                value = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                value = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                value = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                value = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    value = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    value = `<small class="text-muted">${value}</small>`;
                }
            }
            
            tableRows += `<td>${value}</td>`;
        });
        tableRows += '</tr>';
    });
    
    return `
        <div class="table-responsive">
            <table class="table table-sm log-table">
                <thead>
                    <tr>
                        ${headers.map(header => `<th>${header}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        </div>
    `;
}

function createLogPagination(pagination) {
    if (pagination.total_pages <= 1) {
        return `
            <div class="log-pagination">
                <div class="log-pagination-info">
                    Showing ${pagination.total_entries} of ${pagination.total_entries} entries
                </div>
            </div>
        `;
    }
    
    let pageNumbers = '';
    const maxVisible = 5;
    const currentPage = pagination.current_page;
    const totalPages = pagination.total_pages;
    
    // Generate page numbers
    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, start + maxVisible - 1);
    
    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }
    
    if (start > 1) {
        pageNumbers += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogs(${selectedMonitor.id}, 1); return false;">1</a></li>`;
        if (start > 2) {
            pageNumbers += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    for (let i = start; i <= end; i++) {
        pageNumbers += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadLogs(${selectedMonitor.id}, ${i}); return false;">${i}</a>
            </li>
        `;
    }
    
    if (end < totalPages) {
        if (end < totalPages - 1) {
            pageNumbers += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        pageNumbers += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogs(${selectedMonitor.id}, ${totalPages}); return false;">${totalPages}</a></li>`;
    }
    
    return `
        <div class="log-pagination">
            <div class="log-pagination-info">
                Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total_entries)} of ${pagination.total_entries} entries
            </div>
            <nav aria-label="Log Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadLogs(${selectedMonitor.id}, ${pagination.current_page - 1}); return false;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    ${pageNumbers}
                    <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadLogs(${selectedMonitor.id}, ${pagination.current_page + 1}); return false;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    `;
}
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?>
