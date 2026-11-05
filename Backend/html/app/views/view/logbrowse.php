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
/* Single CCTV-style key history ruler */
.key-timeline-wrapper{padding:12px;min-width:0}
.key-timeline-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font-weight:700;margin-bottom:10px}
.key-timeline-heading small{display:block;font-weight:400;color:var(--bs-secondary-color);font-size:.78rem}
.key-ruler-controls{display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:.78rem}
.key-ruler-controls select{max-width:138px}
.key-timeline-scroller{overflow-x:auto;overflow-y:hidden;background:var(--bs-tertiary-bg,#f4f6fa);border:1px solid var(--bs-border-color);border-radius:10px;scrollbar-width:thin}
.key-cctv-ruler{height:110px;position:relative;min-width:100%;background:repeating-linear-gradient(90deg,transparent,transparent 49px,rgba(127,127,127,.05) 50px)}
.key-cctv-baseline{position:absolute;top:74px;left:0;right:0;height:2px;background:var(--bs-border-color,#a8b1c0)}
.key-cctv-tick{position:absolute;top:43px;height:32px;width:1px;background:var(--bs-secondary-color,#677585);opacity:.55;pointer-events:none}
.key-cctv-tick.major{top:34px;height:41px;opacity:.8}
.key-cctv-tick-label{position:absolute;left:4px;top:-23px;font-size:.7rem;white-space:nowrap;color:var(--bs-secondary-color,#667085)}
.key-cctv-activity{position:absolute;top:65px;width:3px;height:18px;transform:translateX(-50%);border-radius:3px;background:var(--bs-primary,#3974cc);pointer-events:none;opacity:.8}
.key-cctv-playhead{position:absolute;top:13px;height:80px;width:2px;background:var(--bs-danger,#e45858);transform:translateX(-50%);pointer-events:none;z-index:3}
.key-cctv-playhead:before{content:"";position:absolute;left:-6px;top:-3px;border-left:7px solid transparent;border-right:7px solid transparent;border-top:10px solid var(--bs-danger,#e45858)}
.key-cctv-range{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;cursor:ew-resize;z-index:4}
.key-ruler-selected{font-size:.78rem;font-variant-numeric:tabular-nums;font-weight:600}
.key-context-panel{margin-top:12px;border:1px solid var(--bs-border-color);border-radius:10px;padding:14px;min-height:110px}
.key-context-heading{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px;font-size:.83rem}
.key-transcript{display:grid;grid-template-columns:90px minmax(0,1fr);gap:2px 12px}
.key-transcript-time{font-variant-numeric:tabular-nums;color:var(--bs-secondary-color);font-size:.75rem;padding-top:4px}
.key-transcript-content{min-height:25px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.75;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.84rem}
.key-transcript-content .key-deleted{color:var(--bs-secondary-color);text-decoration:line-through;text-decoration-thickness:2px;background:rgba(227,75,75,.09)}
.key-control-symbol{color:var(--bs-secondary-color);font-size:.73rem;opacity:.75}
.key-special-badge{display:inline-flex;margin:0 2px;padding:1px 5px;vertical-align:baseline;border-radius:5px;background:var(--bs-secondary-bg,#ecf0f5);color:var(--bs-body-color);border:1px solid var(--bs-border-color);font-family:inherit;font-size:.7rem;line-height:1.4}
.key-remaining{display:flex;flex-wrap:wrap;gap:6px;padding-top:8px}
.key-remaining .badge{font-size:.73rem;font-weight:500}
@media(max-width:650px){.key-timeline-wrapper{padding:8px}.key-context-panel{padding:10px}.key-transcript{grid-template-columns:68px minmax(0,1fr);gap:2px 7px}.key-transcript-content{font-size:.78rem}}
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
                option.textContent = `${device.user_no || '—'}(${device.username || 'Unassigned'}): ${device.last_seen_ip || device.ip_address || '—'} : ${device.platform || 'Unknown OS'}-${device.type || 'Device'}`;
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
    const activeButton = Array.from(document.querySelectorAll('.log-tabs .nav-link')).find(button => button.getAttribute('onclick') === `switchTab('${tab}')`);
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


const KEY_RULER_SCALES = {near:55, medium:14, wide:2.5};
let keyRulerState = null;
let keyContextRequestId = 0;

function keyEventMillis(stamp) {
    // MySQL timestamps have no offset; interpret all values consistently
    // within the ruler rather than mixing browser UTC parsing conventions.
    if (typeof stamp !== 'string') return NaN;
    const m = stamp.match(/^(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d):(\d\d)/);
    return m ? new Date(+m[1], +m[2]-1, +m[3], +m[4], +m[5], +m[6]).getTime() : NaN;
}
function keyClock(ms, fullDate = false) {
    const d = new Date(ms);
    if (!Number.isFinite(d.getTime())) return '—';
    const two = n => String(n).padStart(2,'0');
    const time = [d.getHours(),d.getMinutes(),d.getSeconds()].map(two).join(':');
    return fullDate ? [d.getFullYear(),two(d.getMonth()+1),two(d.getDate())].join('-')+' '+time : time;
}
function createKeyTimeline(logs) {
    const valid = logs.map(log => ({...log, ms:keyEventMillis(log.date)}))
        .filter(log=>Number.isFinite(log.ms) && Number(log.monitor_ip_id)>0)
        .sort((a,b)=>a.ms-b.ms);
    if (!valid.length) return '<div class="p-3 text-muted">No usable timestamps found for this page.</div>';
    const start = Math.floor((valid[0].ms-120000)/60000)*60000;
    const end = Math.ceil((valid[valid.length-1].ms+120000)/60000)*60000;
    keyRulerState = {events:valid,start,end,scale:'medium',selected:valid[valid.length-1].ms};
    // A single continuous ruler contains all loaded events, even across
    // multiple devices. Scrubbing chooses the nearest device as its context.
    requestAnimationFrame(()=>{
        if(document.getElementById('keyTimelineRuler')){
            renderKeyRuler(true);
            inspectKeyContextAt(keyRulerState.selected);
            loadFullKeyTimeline(keyRulerState);
        }
    });
    return `<div class="key-timeline-wrapper">
      <div class="key-timeline-heading">
       <div>Key activity history <small id="keyTimelineInfo">Loading full filtered range… · Drag the playhead or click the time ruler</small></div>
       <div class="key-ruler-controls">
        <label for="keyTimelineScale" class="text-muted">Scale</label>
        <select id="keyTimelineScale" class="form-select form-select-sm" aria-label="Timeline scale">
         <option value="near">15 min / view</option>
         <option value="medium" selected>1 hour / view</option>
         <option value="wide">6 hours / view</option>
        </select>
        <span class="key-ruler-selected" id="keyTimelineSelected"></span>
       </div>
      </div>
      <div class="key-timeline-scroller" id="keyTimelineViewport">
       <div class="key-cctv-ruler" id="keyTimelineRuler"></div>
      </div>
      <div class="key-context-panel" id="keyContextPanel" aria-live="polite">Select a moment on the timeline to inspect nearby keys.</div>
    </div>`;
}
async function loadFullKeyTimeline(state) {
    const info=document.getElementById('keyTimelineInfo');
    const params=new URLSearchParams({
        start_date:currentFilters.start_date||localDateString(),
        end_date:currentFilters.end_date||localDateString(),
        device_id:currentFilters.device_id||''
    });
    try{
        const response=await fetch(`<?php echo BASE_URL; ?>logbrowse/api_key_timeline?${params}`,{credentials:'same-origin'});
        if(!response.ok)throw new Error('HTTP '+response.status);
        const data=await response.json();
        if(!data.success)throw new Error(data.error||'Timeline unavailable');
        if(state!==keyRulerState||currentTab!=='key')return;
        const points=(data.events||[]).map(event=>({...event,ms:keyEventMillis(event.date)}))
            .filter(event=>Number.isFinite(event.ms)&&Number(event.monitor_ip_id)>0);
        if(points.length){
            state.events=points.sort((a,b)=>a.ms-b.ms);
            const start=keyEventMillis(data.start_date+' 00:00:00');
            const end=keyEventMillis(data.end_date+' 23:59:59');
            if(Number.isFinite(start)&&Number.isFinite(end)&&end>start){
                state.start=start;
                state.end=end;
            }
            state.selected=state.events[state.events.length-1].ms;
            renderKeyRuler(true);
            inspectKeyContextAt(state.selected);
        }
        if(info)info.textContent=`${data.start_date} – ${data.end_date} · ${data.bucket_minutes}-minute activity buckets${data.limited?' · First 3,000 buckets shown':''} · Drag or click to inspect ±2 minutes`;
    }catch(error){
        if(state===keyRulerState&&info)info.textContent='Showing current page only ('+error.message+'). Drag or click to inspect ±2 minutes.';
    }
}
function renderKeyRuler(centerPlayhead = false) {
    const state = keyRulerState;
    const ruler=document.getElementById('keyTimelineRuler');
    const viewport=document.getElementById('keyTimelineViewport');
    if(!state||!ruler||!viewport)return;
    const span=state.end-state.start;
    const durationMinutes=span/60000;
    const width=Math.min(24000,Math.max(viewport.clientWidth||800,Math.round(durationMinutes*KEY_RULER_SCALES[state.scale])));
    const pxPerMinute=width/durationMinutes;
    const ticks=[1,2,5,10,15,30,60,120,360,720,1440];
    const step=ticks.find(value=>value*pxPerMinute>=90)||1440;
    const startMinute=Math.ceil(state.start/60000/step)*step;
    state.width=width;
    const toX=ms=>Math.min(width,Math.max(0,(ms-state.start)/span*width));
    const tickParts=[];
    for(let minute=startMinute;minute*60000<=state.end&&tickParts.length<350;minute+=step){
        const ms=minute*60000;
        const time=keyClock(ms);
        tickParts.push(`<div class="key-cctv-tick major" style="left:${toX(ms)}px"><span class="key-cctv-tick-label">${escapeHtml(time)}</span></div>`);
    }
    const markers=state.events.map(event=>{
        const density=Math.max(1,Number(event.event_count)||1);
        const height=Math.min(33,Math.max(10,8+Math.log2(density+1)*5));
        return `<div class="key-cctv-activity" style="left:${toX(event.ms)}px;height:${height}px;top:${83-height}px" title="${escapeHtml(event.date)} · ${density} key events" aria-hidden="true"></div>`;
    }).join('');
    const selectedSeconds=Math.max(0,Math.min(Math.round(span/1000),Math.round((state.selected-state.start)/1000)));
    ruler.style.width=width+'px';
    ruler.innerHTML='<div class="key-cctv-baseline"></div>'
        +tickParts.join('')+markers
        +`<div class="key-cctv-playhead" id="keyTimelinePlayhead" style="left:${toX(state.selected)}px"></div>`
        +`<input id="keyTimelineRange" class="key-cctv-range" type="range" min="0" max="${Math.round(span/1000)}" step="1" value="${selectedSeconds}" aria-label="Select time on keyboard history timeline">`;
    document.getElementById('keyTimelineSelected').textContent=keyClock(state.selected,true);
    if(centerPlayhead){
        viewport.scrollLeft=Math.max(0,toX(state.selected)-viewport.clientWidth/2);
    }
}
function updateKeyPlayhead(seconds) {
    const state=keyRulerState;
    if(!state)return;
    state.selected=Math.min(state.end,Math.max(state.start,state.start+Number(seconds)*1000));
    const playhead=document.getElementById('keyTimelinePlayhead');
    if(playhead)playhead.style.left=((state.selected-state.start)/(state.end-state.start)*state.width)+'px';
    const display=document.getElementById('keyTimelineSelected');
    if(display)display.textContent=keyClock(state.selected,true);
}
function transcriptKeyRows(keys) {
    const lines=[{time:keys[0]?.key_date||'',tokens:[]}];
    const extras=[];
    let current=lines[0];
    const addBadge=(raw,time)=>{
        current.tokens.push({kind:'badge',raw,time});
        extras.push({raw,time});
    };
    for(const entry of keys) {
        const raw=String(entry.key_pressed??'');
        const key=raw.trim().replace(/^\[(.*)\]$/,'$1').replace(/^<(.*)>$/,'$1');
        const time=String(entry.key_date||'');
        if(/^(?:(?:shift|alt)\+)?(?:enter|return|numpadenter)$/i.test(key) || raw==='\n' || raw==='\r'){
            current.tokens.push({kind:'newline'});
            current={time,tokens:[]};
            lines.push(current);
        }else if(/^(?:ctrl|control)\+(?:backspace|back)$/i.test(key)){
            let count=0,started=false;
            for(let k=current.tokens.length-1;k>=0;k--){
                const token=current.tokens[k];
                if(token.kind!=='char'||token.deleted)continue;
                if(token.value.trim()==='' && started)break;
                token.deleted=true;count++;started=true;
            }
            if(count)current.tokens.push({kind:'effect',value:'⌫ word',time});
            else addBadge(raw||'Ctrl+Backspace',time);
        }else if(/^(backspace|back|bksp|vk_back)$/i.test(key)){
            let last=null;
            for(let k=current.tokens.length-1;k>=0;k--){
                if(current.tokens[k].kind==='char'&&!current.tokens[k].deleted){last=current.tokens[k];break;}
            }
            if(last){
                last.deleted=true;
                current.tokens.push({kind:'effect',value:'⌫',time});
            }else addBadge(raw||'Backspace',time);
        }else if(/^(space|spacebar)$/i.test(key)||raw===' '){
            current.tokens.push({kind:'char',value:' ',time,deleted:false});
        }else if(/^tab$/i.test(key)||raw==='\t'){
            current.tokens.push({kind:'char',value:'    ',time,deleted:false});
        }else if(raw.length===1){
            current.tokens.push({kind:'char',value:raw,time,deleted:false});
        }else{
            // Modifiers and navigation shortcuts are not inferable text edits.
            // Preserve the event without inventing a caret position.
            addBadge(raw||'Unknown',time);
        }
    }
    const transcript=lines.map(line=>{
        const content=line.tokens.map(token=>{
            if(token.kind==='char')return `<span class="${token.deleted?'key-deleted':''}" title="${escapeHtml(token.time)}">${escapeHtml(token.value)}</span>`;
            if(token.kind==='newline')return '<span class="key-control-symbol" title="Enter">↵</span>';
            if(token.kind==='effect')return `<span class="key-control-symbol" title="Backspace at ${escapeHtml(token.time)}">${token.value}</span>`;
            return `<span class="key-special-badge" title="${escapeHtml(token.time)}">${escapeHtml(token.raw)}</span>`;
        }).join('');
        return `<div class="key-transcript-time">${escapeHtml((line.time||'').split(' ').pop())}</div>
            <div class="key-transcript-content">${content||'<span class="text-muted"> </span>'}</div>`;
    }).join('');
    const specials=extras.map(item=>`<span class="badge bg-light text-dark border" title="${escapeHtml(item.time)}">${escapeHtml(item.raw)}</span>`).join('');
    return '<div class="key-transcript" role="table" aria-label="Keyboard events with timestamps">'+transcript+'</div>'
        +(specials?'<div class="small text-muted mt-3 mb-1">Other key events</div><div class="key-remaining">'+specials+'</div>':'');
}
async function inspectKeyContextAt(ms) {
    const state=keyRulerState;
    const panel=document.getElementById('keyContextPanel');
    if(!state||!panel||!state.events.length)return;
    const nearest=state.events.reduce((best,event)=>Math.abs(event.ms-ms)<Math.abs(best.ms-ms)?event:best,state.events[0]);
    const seq=++keyContextRequestId;
    const center=keyClock(ms,true);
    panel.textContent='Loading keyboard events around '+center+'…';
    const params=new URLSearchParams({device_id:String(nearest.monitor_ip_id),at:center});
    try{
        const response=await fetch(`<?php echo BASE_URL; ?>logbrowse/api_key_context?${params}`,{credentials:'same-origin'});
        if(!response.ok)throw new Error('HTTP '+response.status);
        const data=await response.json();
        if(seq!==keyContextRequestId||!panel.isConnected)return;
        if(!data.success)throw new Error(data.error||'Unable to load context');
        const keys=(data.logs||[]);
        const device=`${nearest.user_no||'—'}(${nearest.username||'Unassigned'}): ${nearest.ip_address||'—'}`;
        panel.innerHTML=`<div class="key-context-heading"><strong>${escapeHtml(center)}</strong>
          <span class="text-muted">${escapeHtml(device)} · ${keys.length} keys${data.limited?' (first 2,000)':''}</span></div>`
          +(keys.length?transcriptKeyRows(keys):'<div class="text-muted">No keys recorded within two minutes of this point.</div>');
    }catch(error){
        if(seq===keyContextRequestId)panel.textContent='Cannot load key context: '+error.message;
    }
}
document.addEventListener('input',event=>{
    if(event.target?.id==='keyTimelineRange')updateKeyPlayhead(event.target.value);
});
document.addEventListener('change',event=>{
    if(event.target?.id==='keyTimelineScale' && keyRulerState){
        keyRulerState.scale=event.target.value;
        renderKeyRuler(true);
    }else if(event.target?.id==='keyTimelineRange' && keyRulerState){
        updateKeyPlayhead(event.target.value);
        inspectKeyContextAt(keyRulerState.selected);
    }
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
    const statusClass = getDeviceStatus(log.heartbeat_age_seconds);
    const label = `${log.user_no || '—'}(${log.username || 'Unassigned'}): ${log.ip_address || '—'} : ${log.platform || 'Unknown OS'}-${log.type || 'Device'}`;
    return `<div class="small">
        <div class="fw-semibold">${escapeHtml(label)}</div>
        <span class="status-badge ${statusClass}">${getStatusText(statusClass)}</span>
    </div>`;
}

function getDeviceStatus(ageSeconds) {
    const age = Number(ageSeconds);
    if (ageSeconds === null || ageSeconds === undefined || !Number.isFinite(age)) return 'status-offline';
    if (age <= 300) return 'status-online';
    if (age <= 3600) return 'status-inactive';
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
    
    // Key timeline uses sequential event navigation instead of page numbers.
    // The backend still fetches bounded pages to avoid loading all keystrokes.
    if(currentTab==='key'){
        const prev= Math.max(1,pagination.current_page-1);
        const next= pagination.current_page+1;
        document.getElementById('pagination').innerHTML = `
          <li class="page-item ${pagination.has_prev?'':'disabled'}">
            <button type="button" class="page-link" ${pagination.has_prev?'':'disabled'} onclick="changePage(${prev})" aria-label="Previous key events">
              <i class="fas fa-chevron-left me-1"></i>Previous events
            </button>
          </li>
          <li class="page-item ${pagination.has_next?'':'disabled'}">
            <button type="button" class="page-link" ${pagination.has_next?'':'disabled'} onclick="changePage(${next})" aria-label="Next key events">
              Next events<i class="fas fa-chevron-right ms-1"></i>
            </button>
          </li>`;
        return;
    }

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