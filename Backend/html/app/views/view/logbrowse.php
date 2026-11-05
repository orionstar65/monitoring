<?php
$title = 'Log Browse - ' . APP_NAME;
$active_page = 'logbrowse';
$page_title = 'Log Browse';
ob_start();
?>

<style>
/* Log Browse Styles */
:root {
    --logbrowse-primary: #2563eb;
    --logbrowse-primary-dark: #1d4ed8;
    --logbrowse-success: #10b981;
    --logbrowse-warning: #f59e0b;
    --logbrowse-error: #ef4444;
    --logbrowse-surface: #ffffff;
    --logbrowse-background: #f8fafc;
    --logbrowse-text-primary: #1e293b;
    --logbrowse-text-secondary: #64748b;
    --logbrowse-divider: #e2e8f0;
    --logbrowse-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --logbrowse-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Override main layout constraints */
.content-area {
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Main Container */
.logbrowse-container {
    min-height: calc(100vh - 120px);
    background: var(--logbrowse-background);
    padding: 1.5rem;
}

/* Filter Panel */
.filter-panel {
    background: var(--logbrowse-surface);
    border-radius: 12px;
    box-shadow: var(--logbrowse-shadow);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--logbrowse-divider);
}

.filter-panel h5 {
    color: var(--logbrowse-text-primary);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 500;
    color: var(--logbrowse-text-primary);
    font-size: 0.875rem;
}

.filter-group .form-control,
.filter-group .form-select {
    border: 2px solid var(--logbrowse-divider);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.filter-group .form-control:focus,
.filter-group .form-select:focus {
    border-color: var(--logbrowse-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-filter {
    background: var(--logbrowse-primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-filter:hover {
    background: var(--logbrowse-primary-dark);
    transform: translateY(-1px);
}

.btn-clear {
    background: var(--logbrowse-text-secondary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-clear:hover {
    background: var(--logbrowse-text-primary);
}

.btn-remove {
    background: var(--logbrowse-error);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-remove:hover:not(:disabled) {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-remove:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.cleanup-progress {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    background: var(--logbrowse-background);
    border: 1px solid var(--logbrowse-divider);
    font-size: 0.875rem;
    color: var(--logbrowse-text-primary);
}

.cleanup-progress.error {
    border-color: var(--logbrowse-error);
    color: var(--logbrowse-error);
}

/* Stats Panel */
.stats-panel {
    background: var(--logbrowse-surface);
    border-radius: 12px;
    box-shadow: var(--logbrowse-shadow);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--logbrowse-divider);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--logbrowse-surface) 0%, #f8fafc 100%);
    border: 1px solid var(--logbrowse-divider);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--logbrowse-shadow-lg);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--logbrowse-primary);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--logbrowse-text-secondary);
    font-weight: 500;
}

/* Content Panel */
.content-panel {
    background: var(--logbrowse-surface);
    border-radius: 12px;
    box-shadow: var(--logbrowse-shadow);
    border: 1px solid var(--logbrowse-divider);
    overflow: hidden;
}

/* Tabs */
.log-tabs {
    border-bottom: 1px solid var(--logbrowse-divider);
    background: #f8fafc;
    padding: 0;
    margin: 0;
    display: flex;
}

.log-tabs .nav-link {
    border: none;
    border-radius: 0;
    padding: 1rem 1.5rem;
    font-weight: 500;
    color: var(--logbrowse-text-secondary);
    transition: all 0.2s ease;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.log-tabs .nav-link:hover {
    color: var(--logbrowse-primary);
    background: rgba(37, 99, 235, 0.05);
}

.log-tabs .nav-link.active {
    color: var(--logbrowse-primary);
    background: var(--logbrowse-surface);
    border-bottom: 2px solid var(--logbrowse-primary);
}

/* Table */
.log-table-container {
    padding: 1.5rem;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.log-table th {
    background: #f8fafc;
    color: var(--logbrowse-text-primary);
    font-weight: 600;
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 2px solid var(--logbrowse-divider);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.log-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--logbrowse-divider);
    vertical-align: middle;
}

.log-table tbody tr:hover {
    background: rgba(37, 99, 235, 0.02);
}

.log-table .badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
}

/* Pagination */
.pagination-panel {
    background: #f8fafc;
    border-top: 1px solid var(--logbrowse-divider);
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--logbrowse-text-secondary);
    font-weight: 500;
}

.pagination {
    display: flex;
    gap: 0.25rem;
    margin: 0;
    list-style: none;
}

.page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--logbrowse-divider);
    border-radius: 6px;
    color: var(--logbrowse-text-primary);
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    display: block;
}

.page-link:hover {
    background: var(--logbrowse-primary);
    color: white;
    border-color: var(--logbrowse-primary);
}

.page-item.active .page-link {
    background: var(--logbrowse-primary);
    color: white;
    border-color: var(--logbrowse-primary);
}

.page-item.disabled .page-link {
    color: var(--logbrowse-text-secondary);
    background: transparent;
    cursor: not-allowed;
}

/* Loading States */
.loading-container {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--logbrowse-text-secondary);
    flex-direction: column;
    gap: 1rem;
}

.loading-spinner {
    width: 2rem;
    height: 2rem;
    border: 3px solid var(--logbrowse-divider);
    border-top: 3px solid var(--logbrowse-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--logbrowse-text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--logbrowse-primary);
}

.empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--logbrowse-text-primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .filter-actions {
        justify-content: stretch;
    }
    
    .filter-actions .btn-filter,
    .filter-actions .btn-clear,
    .filter-actions .btn-remove {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .pagination-panel {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .pagination {
        justify-content: center;
    }
}

/* Device Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-online {
    background: rgba(16, 185, 129, 0.1);
    color: var(--logbrowse-success);
}

.status-inactive {
    background: rgba(245, 158, 11, 0.1);
    color: var(--logbrowse-warning);
}

.status-offline {
    background: rgba(239, 68, 68, 0.1);
    color: var(--logbrowse-error);
}

.status-online::before {
    content: '●';
    color: var(--logbrowse-success);
}

.status-inactive::before {
    content: '●';
    color: var(--logbrowse-warning);
}

.status-offline::before {
    content: '●';
    color: var(--logbrowse-error);
}

/* Bootstrap compatibility */
.fw-bold { font-weight: bold !important; }
.small { font-size: 0.875em; }
.text-muted { color: var(--logbrowse-text-secondary) !important; }
.table-responsive { overflow-x: auto; }
</style>

<style>
.key-timeline-wrapper{padding:12px;min-width:0}
.key-timeline-heading{font-weight:700;margin-bottom:12px}
.key-timeline-heading small{font-weight:400;color:var(--bs-secondary-color);margin-left:8px}
.key-timeline-scroller{overflow-x:auto;padding:8px 0 15px}
.key-timeline-track{display:flex;gap:8px;min-width:max-content;border-bottom:2px solid var(--bs-border-color);padding-bottom:6px}
.key-timeline-event{position:relative;display:flex;flex-direction:column;gap:9px;width:150px;min-height:92px;padding:10px;border:1px solid var(--bs-border-color);border-radius:10px;background:var(--bs-body-bg);text-align:left;color:inherit}
.key-timeline-event.selected{outline:2px solid var(--bs-primary);outline-offset:1px}
.key-timeline-time{font-size:.73rem;color:var(--bs-secondary-color)}
.key-timeline-marker{width:9px;height:9px;border-radius:50%;background:var(--bs-primary)}
.key-timeline-preview{display:flex;flex-direction:column;gap:3px;overflow-wrap:anywhere}
.key-timeline-preview small{font-size:.69rem;color:var(--bs-secondary-color)}
.key-context-panel{margin-top:14px;border:1px solid var(--bs-border-color);border-radius:12px;padding:15px;min-height:90px}
.key-readable{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.6}
.key-badges{display:flex;flex-wrap:wrap;gap:5px}
.key-token{font-size:.75rem;padding:5px 7px}
@media(max-width:700px){.key-timeline-event{width:115px}.key-context-panel{padding:10px}}
</style>
<div class="logbrowse-container">
    <!-- Filter Panel -->
    <div class="filter-panel">
        <h5>
            <i class="fas fa-filter"></i>
            Filters
        </h5>
        <div class="filter-row">
            <div class="filter-group">
                <label for="deviceFilter">Device</label>
                <select id="deviceFilter" class="form-select">
                    <option value="">All Devices</option>
                    <?php foreach ($monitors as $monitor): ?>
                        <option value="<?php echo $monitor['id']; ?>">
                            <?= htmlspecialchars(($monitor['user_no']??'—').'('.($monitor['username']?:'Unassigned').'): '.($monitor['last_seen_ip']?:$monitor['ip_address']).' : '.($monitor['platform']??'Unknown OS').'-'.($monitor['type']??'Device'),ENT_QUOTES,'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="startDate">Start Date</label>
                <input type="date" id="startDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label for="endDate">End Date</label>
                <input type="date" id="endDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-actions">
                <button class="btn-filter" onclick="applyFilters()">
                    <i class="fas fa-search"></i>
                    Filter
                </button>
                <button class="btn-clear" onclick="clearFilters()">
                    <i class="fas fa-times"></i>
                    Clear
                </button>
                <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                <button class="btn-remove" id="btnRemoveScreenshots" onclick="removeScreenshots()">
                    <i class="fas fa-trash"></i>
                    Remove Screenshots
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
        <div class="cleanup-progress" id="cleanupProgress" style="display: none;"></div>
        <?php endif; ?>
    </div>

    <!-- Stats Panel -->
    <div class="stats-panel">
        <h5>
            <i class="fas fa-chart-bar"></i>
            Statistics
        </h5>
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-number" id="totalLogs">0</div>
                <div class="stat-label">Total Logs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="browserLogs">0</div>
                <div class="stat-label">Browser History</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="keyLogs">0</div>
                <div class="stat-label">Key Logs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="usbLogs">0</div>
                <div class="stat-label">USB Logs</div>
            </div>
        </div>
    </div>

    <!-- Content Panel -->
    <div class="content-panel">
        <!-- Tabs -->
        <div class="log-tabs" id="logTabs">
            <button class="nav-link active" onclick="switchTab('browser')">
                <i class="fas fa-globe"></i>
                Browser History
            </button>
            <button class="nav-link" onclick="switchTab('key')">
                <i class="fas fa-keyboard"></i>
                Key Logs
            </button>
            <button class="nav-link" onclick="switchTab('usb')">
                <i class="fas fa-usb"></i>
                USB Logs
            </button>
        </div>

        <!-- Table Container -->
        <div class="log-table-container" id="tableContainer">
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <div>Loading logs...</div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-panel" id="paginationPanel" style="display: none;">
            <div class="pagination-info" id="paginationInfo">
                Showing 0 to 0 of 0 entries
            </div>
            <nav aria-label="Log Pagination">
                <ul class="pagination" id="pagination">
                    <!-- Pagination will be generated here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<script>
// Global variables
let currentTab = 'browser';
let currentPage = 1;
let currentPerPage = 20;
let currentFilters = {
    device_id: '',
    start_date: '',
    end_date: ''
};

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Set default date values
    const today = localDateString();
    document.getElementById('startDate').value = today;
    document.getElementById('endDate').value = today;
    
    // Refresh available devices from the same source used by the live status pages.
    // Keep server-rendered options if the API is temporarily unavailable.
    loadAvailableDevices();
    updateFilters();
    loadStats();
    loadLogs();
});

function loadAvailableDevices() {
    const select = document.getElementById('deviceFilter');
    if (!select) return;
    const previousValue = select.value;
    fetch(`<?php echo BASE_URL; ?>logbrowse/api_devices`, {credentials: 'same-origin'})
        .then(response => {
            if (!response.ok) throw new Error(`Device API returned HTTP ${response.status}`);
            return response.json();
        })
        .then(devices => {
            if (!Array.isArray(devices)) throw new Error('Invalid device API response');
            // Never discard valid server-rendered options for a failed/empty API.
            if (!devices.length && select.options.length > 1) return;
            const fragment = document.createDocumentFragment();
            const all = document.createElement('option');
            all.value = '';
            all.textContent = 'All Devices';
            fragment.appendChild(all);
            devices.forEach(device => {
                const option = document.createElement('option');
                option.value = String(device.id);
                option.textContent = `${device.username || 'Unassigned'} (Device #${device.id} · ${device.ip_address || 'Unknown IP'})`;
                fragment.appendChild(option);
            });
            select.replaceChildren(fragment);
            if (Array.from(select.options).some(option => option.value === previousValue)) {
                select.value = previousValue;
            }
            currentFilters.device_id = select.value;
        })
        .catch(error => {
            console.error('Unable to refresh log browse device list:', error);
            if (select.options.length <= 1) {
                const option = document.createElement('option');
                option.value = '';
                option.disabled = true;
                option.textContent = 'Unable to load devices — refresh page';
                select.appendChild(option);
            }
        });
}

function localDateString() {
    const d = new Date();
    return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
}

function switchTab(tab) {
    currentTab = tab;
    currentPage = 1;
    
    // Update active tab
    document.querySelectorAll('.log-tabs .nav-link').forEach(link => {
        link.classList.remove('active');
    });
    const activeButton = document.querySelector('.log-tabs .nav-link[onclick="switchTab(\\'' + tab + '\\')"]');
    if (activeButton) activeButton.classList.add('active');
    
    // Load logs for new tab
    loadLogs();
}

function updateFilters() {
    currentFilters = {
        device_id: document.getElementById('deviceFilter').value,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value
    };
}

function applyFilters() {
    updateFilters();
    currentPage = 1;
    loadStats();
    loadLogs();
}

function clearFilters() {
    document.getElementById('deviceFilter').value = '';
    document.getElementById('startDate').value = localDateString();
    document.getElementById('endDate').value = localDateString();
    applyFilters();
}

// ---------------------------------------------------------------------------
// Screenshot cleanup (admin only)
// ---------------------------------------------------------------------------
const CLEANUP_BATCH_SIZE = 2000;

function escapeCleanupText(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showCleanupProgress(message, isError) {
    const panel = document.getElementById('cleanupProgress');
    if (!panel) return;

    panel.style.display = 'block';
    panel.className = 'cleanup-progress' + (isError ? ' error' : '');
    panel.innerHTML = message;
}

function buildCleanupBody(extra) {
    const body = new URLSearchParams({
        device_id: currentFilters.device_id,
        start_date: currentFilters.start_date,
        end_date: currentFilters.end_date
    });

    Object.keys(extra || {}).forEach(key => body.append(key, extra[key]));
    return body;
}

function removeScreenshots() {
    updateFilters();

    if (!currentFilters.start_date || !currentFilters.end_date) {
        alert('Please select a start date and an end date first.');
        return;
    }

    const deviceSelect = document.getElementById('deviceFilter');
    const deviceLabel = currentFilters.device_id
        ? deviceSelect.options[deviceSelect.selectedIndex].text.trim()
        : 'ALL DEVICES';

    showCleanupProgress('<i class="fas fa-spinner fa-spin"></i> Checking how many screenshots match the filter...');

    fetch('<?php echo BASE_URL; ?>cleanup/api_preview_screenshots', {
        method: 'POST',
        body: buildCleanupBody()
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            showCleanupProgress('Error: ' + escapeCleanupText(data.error || 'Failed to preview screenshots'), true);
            return;
        }

        if (data.total === 0) {
            showCleanupProgress('No screenshots found for the selected device and date range.');
            return;
        }

        const scope = `${data.total.toLocaleString()} screenshot(s) (${data.total_size_formatted})\n`
            + `Device: ${deviceLabel}\n`
            + `Date range: ${currentFilters.start_date} to ${currentFilters.end_date}`;

        if (!confirm(`This will permanently delete:\n\n${scope}\n\nScreenshots and thumbnails will be removed from disk. This action cannot be undone.\n\nContinue?`)) {
            showCleanupProgress('Cleanup cancelled.');
            return;
        }

        // Deleting across every device is the widest possible action - require typing
        if (!currentFilters.device_id) {
            const typed = prompt('No device is selected, so screenshots for ALL DEVICES in this date range will be deleted.\n\nType DELETE to confirm:');
            if (typed !== 'DELETE') {
                showCleanupProgress('Cleanup cancelled.');
                return;
            }
        } else if (!confirm('Are you absolutely sure? This permanently removes the image files.')) {
            showCleanupProgress('Cleanup cancelled.');
            return;
        }

        runCleanupBatches(data.total);
    })
    .catch(error => {
        showCleanupProgress('Error: ' + error.message, true);
    });
}

function runCleanupBatches(totalToDelete) {
    const btn = document.getElementById('btnRemoveScreenshots');
    const originalHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';

    let deletedRows = 0;
    let deletedFiles = 0;
    let deletedThumbnails = 0;
    let freedBytes = 0;
    const errors = [];

    const finish = (message, isError) => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showCleanupProgress(message, isError);
        loadStats();
        loadLogs();
    };

    const runBatch = () => {
        fetch('<?php echo BASE_URL; ?>cleanup/api_delete_screenshots', {
            method: 'POST',
            body: buildCleanupBody({ limit: CLEANUP_BATCH_SIZE })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                finish('Error: ' + escapeCleanupText(data.error || 'Failed to delete screenshots'), true);
                return;
            }

            deletedRows += data.deleted_rows;
            deletedFiles += data.deleted_files;
            deletedThumbnails += data.deleted_thumbnails;
            freedBytes += data.freed_bytes;

            if (data.errors && data.errors.length) {
                errors.push(...data.errors);
            }

            const progress = `<i class="fas fa-spinner fa-spin"></i> Deleted ${deletedRows.toLocaleString()} of `
                + `${totalToDelete.toLocaleString()} screenshot(s)... (${data.remaining.toLocaleString()} remaining)`;
            showCleanupProgress(progress);

            // No progress in this batch means the remaining rows cannot be removed
            if (data.deleted_rows === 0) {
                finish(`Stopped with ${data.remaining.toLocaleString()} screenshot(s) remaining - the files could not be deleted. `
                    + `Removed ${deletedFiles.toLocaleString()} image(s) and ${deletedThumbnails.toLocaleString()} thumbnail(s).`
                    + (errors.length ? '<br>First error: ' + escapeCleanupText(errors[0]) : ''), true);
                return;
            }

            if (data.remaining > 0) {
                runBatch();
                return;
            }

            const summary = `<i class="fas fa-check-circle"></i> Removed ${deletedFiles.toLocaleString()} screenshot(s) and `
                + `${deletedThumbnails.toLocaleString()} thumbnail(s), freeing ${formatBytes(freedBytes)}. `
                + `${deletedRows.toLocaleString()} database record(s) deleted.`;
            finish(summary + (errors.length ? `<br>${errors.length} file(s) reported errors.` : ''), false);
        })
        .catch(error => {
            finish('Error: ' + error.message, true);
        });
    };

    runBatch();
}

function formatBytes(bytes) {
    if (!bytes) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(k)), sizes.length - 1);
    return `${(bytes / Math.pow(k, i)).toFixed(2)} ${sizes[i]}`;
}

function loadStats() {
    const params = new URLSearchParams(currentFilters);
    
    fetch(`<?php echo BASE_URL; ?>logbrowse/api_stats?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalLogs').textContent = data.stats.total.toLocaleString();
                document.getElementById('browserLogs').textContent = data.stats.browser_history.toLocaleString();
                document.getElementById('keyLogs').textContent = data.stats.key_logs.toLocaleString();
                document.getElementById('usbLogs').textContent = data.stats.usb_logs.toLocaleString();
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
        });
}

function loadLogs() {
    const params = new URLSearchParams({
        ...currentFilters,
        page: currentPage,
        per_page: currentPerPage
    });
    
    const apiEndpoint = getApiEndpoint();
    
    // Show loading state
    document.getElementById('tableContainer').innerHTML = `
        <div class="loading-container">
            <div class="loading-spinner"></div>
            <div>Loading ${currentTab} logs...</div>
        </div>
    `;
    
    fetch(`${apiEndpoint}?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogs(data.logs, data.pagination);
            } else {
                showError('Failed to load logs');
            }
        })
        .catch(error => {
            console.error('Error loading logs:', error);
            showError('Error loading logs: ' + error.message);
        });
}

function getApiEndpoint() {
    switch (currentTab) {
        case 'browser':
            return `<?php echo BASE_URL; ?>logbrowse/api_browser_logs`;
        case 'key':
            return `<?php echo BASE_URL; ?>logbrowse/api_key_logs`;
        case 'usb':
            return `<?php echo BASE_URL; ?>logbrowse/api_usb_logs`;
        default:
            return `<?php echo BASE_URL; ?>logbrowse/api_browser_logs`;
    }
}

function displayLogs(logs, pagination) {
    if (logs.length === 0) {
        showEmptyState();
        return;
    }
    
    const tableHtml = currentTab === 'key' ? createKeyTimeline(logs) : createTable(logs);
    document.getElementById('tableContainer').innerHTML = tableHtml;
    
    // Update pagination
    updatePagination(pagination);
}

function createKeyTimeline(logs) {
    const rows = logs.map((log,index) => {
        const at = escapeHtml(log.date || '');
        const device = escapeHtml(log.user_no || '—') + '(' + escapeHtml(log.username || 'Unassigned') + '): ' + escapeHtml(log.ip_address || '—');
        return `<button type="button" class="key-timeline-event" data-key-index="${index}" aria-label="Inspect key context at ${at}">
          <span class="key-timeline-time">${at}</span>
          <span class="key-timeline-marker"></span>
          <span class="key-timeline-preview"><strong>${escapeHtml(log.key||'')}</strong><small>${device} · ${escapeHtml(log.application||'')}</small></span>
        </button>`;
    }).join('');
    window.keyTimelineItems = logs;
    return `<div class="key-timeline-wrapper">
      <div class="key-timeline-heading">Key activity timeline <small>Select a marker to see keys within ±2 minutes</small></div>
      <div class="key-timeline-scroller"><div class="key-timeline-track">${rows}</div></div>
      <div class="key-context-panel" id="keyContextPanel"><div class="text-muted">Select a time above to inspect the nearby sequence.</div></div>
    </div>`;
}

async function inspectKeyContext(index) {
    const event = (window.keyTimelineItems||[])[index];
    if (!event) return;
    document.querySelectorAll('.key-timeline-event').forEach((element,i)=>element.classList.toggle('selected',i===index));
    const panel = document.getElementById('keyContextPanel');
    if (!panel) return;
    panel.textContent='Loading surrounding key events…';
    const params=new URLSearchParams({device_id:event.monitor_ip_id,at:event.date});
    try {
        const response=await fetch(`<?php echo BASE_URL; ?>logbrowse/api_key_context?${params}`);
        if(!response.ok) throw new Error('HTTP '+response.status);
        const data=await response.json();
        if(!data.success) throw new Error(data.error||'Unable to load context');
        const keys=data.logs||[];
        let readable='';
        const badges=keys.map(item=>{
            const raw=String(item.key_pressed||'');
            const text=/^(space|spacebar)$/i.test(raw)?' ':raw.length===1?raw:'';
            readable+=text;
            return '<span class="badge bg-light text-dark border key-token" title="'+escapeHtml(item.key_date)+'">'+escapeHtml(raw||'—')+'</span>';
        }).join('');
        panel.innerHTML='<div class="fw-semibold mb-2">'+escapeHtml(event.date)+' · '+keys.length+' keys'+(data.limited?' (limited to first 2,000)':'')+'</div>'
           +'<div class="key-readable mb-3">'+escapeHtml(readable||'No printable text in this window')+'</div>'
           +'<div class="key-badges">'+badges+'</div>';
    } catch(error){ panel.textContent='Cannot load key context: '+error.message; }
}

document.addEventListener('click',event=>{
    const button=event.target.closest('.key-timeline-event');
    if(button) inspectKeyContext(Number(button.dataset.keyIndex));
});

function createTable(logs) {
    const headers = getTableHeaders();
    let tableRows = '';
    
    logs.forEach(log => {
        tableRows += '<tr>';
        headers.forEach(header => {
            const value = formatCellValue(log, header);
            tableRows += `<td>${value}</td>`;
        });
        tableRows += '</tr>';
    });
    
    return `
        <div class="table-responsive">
            <table class="log-table">
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

function getTableHeaders() {
    switch (currentTab) {
        case 'browser':
            return ['Date', 'Device', 'Browser', 'URL', 'Title'];
        case 'key':
            return ['Date', 'Device', 'Application', 'Key', 'Action'];
        case 'usb':
            return ['Date', 'Device', 'Device Name', 'Action', 'Details'];
        default:
            return ['Date', 'Device', 'Browser', 'URL', 'Title'];
    }
}

function formatCellValue(log, header) {
    switch (header) {
        case 'Date':
            return formatDate(log.date);
        case 'Device':
            return formatDevice(log);
        case 'Browser':
            return `<span class="badge" style="background-color: #007bff; color: white;">${escapeHtml(log.browser)}</span>`;
        case 'URL':
            if (log.url && log.url.length > 50) {
                return `<a href="${escapeHtml(log.url)}" target="_blank" title="${escapeHtml(log.url)}">${escapeHtml(log.url.substring(0, 47))}...</a>`;
            }
            return `<a href="${escapeHtml(log.url)}" target="_blank">${escapeHtml(log.url)}</a>`;
        case 'Title':
            if (log.title && log.title.length > 40) {
                return `<span title="${escapeHtml(log.title)}">${escapeHtml(log.title.substring(0, 37))}...</span>`;
            }
            return escapeHtml(log.title);
        case 'Application':
            return `<span class="badge" style="background-color: #17a2b8; color: white;">${escapeHtml(log.application)}</span>`;
        case 'Key':
            return `<span class="badge" style="background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6;">${escapeHtml(log.key)}</span>`;
        case 'Action':
            const actionClass = log.action === 'Connected' ? 'background-color: #28a745; color: white;' : 
                              log.action === 'Disconnected' ? 'background-color: #dc3545; color: white;' : 
                              log.action === 'keydown' ? 'background-color: #007bff; color: white;' : 'background-color: #6c757d; color: white;';
            return `<span class="badge" style="${actionClass}">${escapeHtml(log.action)}</span>`;
        case 'Device Name':
            return `<span class="badge" style="background-color: #ffc107; color: #212529;">${escapeHtml(log.device)}</span>`;
        case 'Details':
            return escapeHtml(log.details);
        default:
            return escapeHtml(log[header.toLowerCase()] || '');
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return `<small class="text-muted">${date.toLocaleString()}</small>`;
}

function formatDevice(log) {
    const statusClass = getDeviceStatus(log.username, log.updated_at);
    return `
        <div>
            <div class="fw-bold">${escapeHtml(log.username || 'Unknown')}</div>
            <div class="small text-muted">${escapeHtml(log.ip_address)}</div>
            <span class="status-badge ${statusClass}">${getStatusText(statusClass)}</span>
        </div>
    `;
}

function getDeviceStatus(username, updatedAt) {
    if (!updatedAt) return 'status-offline';
    
    const lastActivity = new Date(updatedAt);
    const now = new Date();
    const diffMinutes = (now - lastActivity) / (1000 * 60);
    
    // Use same thresholds as PHP calculateClientStatus(): <= 5 min = online, <= 60 min = inactive, > 60 min = offline
    if (diffMinutes <= 5) return 'status-online';
    if (diffMinutes <= 60) return 'status-inactive';
    return 'status-offline';
}

function getStatusText(statusClass) {
    switch (statusClass) {
        case 'status-online': return 'Online';
        case 'status-inactive': return 'Inactive';
        case 'status-offline': return 'Offline';
        default: return 'Unknown';
    }
}

function updatePagination(pagination) {
    if (pagination.total_pages <= 1) {
        document.getElementById('paginationPanel').style.display = 'none';
        return;
    }
    
    document.getElementById('paginationPanel').style.display = 'flex';
    
    // Update pagination info
    const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
    const end = Math.min(pagination.current_page * pagination.per_page, pagination.total_entries);
    document.getElementById('paginationInfo').textContent = 
        `Showing ${start} to ${end} of ${pagination.total_entries.toLocaleString()} entries`;
    
    // Generate pagination links
    let paginationHtml = '';
    
    // Previous button
    paginationHtml += `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Page numbers
    const maxVisible = 5;
    let startPage = Math.max(1, pagination.current_page - 2);
    let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
    
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHtml += `
            <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>
        `;
    }
    
    // Next button
    paginationHtml += `
        <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    `;
    
    document.getElementById('pagination').innerHTML = paginationHtml;
}

function changePage(page) {
    if (page < 1) return;
    currentPage = page;
    loadLogs();
}

function showEmptyState() {
    document.getElementById('tableContainer').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h5>No Logs Found</h5>
            <p>No ${currentTab} logs found for the selected filters.</p>
        </div>
    `;
    document.getElementById('paginationPanel').style.display = 'none';
}

function showError(message) {
    document.getElementById('tableContainer').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <h5>Error</h5>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
    document.getElementById('paginationPanel').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?>