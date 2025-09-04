<?php
$title = 'Real-time Monitoring - ' . APP_NAME;
$active_page = 'view';
$page_title = 'Real-time Monitoring';
ob_start();
?>
<style>
/* Modern Real-time Monitoring Styles */
:root {
    --monitoring-primary: #1976d2;
    --monitoring-primary-dark: #1565c0;
    --monitoring-success: #4caf50;
    --monitoring-warning: #ff9800;
    --monitoring-error: #f44336;
    --monitoring-surface: #ffffff;
    --monitoring-background: #fafafa;
    --monitoring-text-primary: #212121;
    --monitoring-text-secondary: #757575;
    --monitoring-divider: #e0e0e0;
    --monitoring-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    --monitoring-shadow-hover: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* Status Badges with Modern Design */
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

.live-status-badge .pulse {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: inherit;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.05);
        opacity: 0.8;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.status-online {
    background: linear-gradient(135deg, var(--monitoring-success), #66bb6a);
    color: white;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.status-inactive {
    background: linear-gradient(135deg, var(--monitoring-warning), #ffb74d);
    color: white;
    box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}

.status-offline {
    background: linear-gradient(135deg, var(--monitoring-error), #ef5350);
    color: white;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
}

/* Override main layout constraints for monitoring page */
.content-area {
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Modern Dashboard Layout */
.dashboard-container {
    display: flex;
    height: calc(100vh - 120px);
    gap: 1.5rem;
    padding: 1.5rem;
    background: var(--monitoring-background);
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}

.sidebar-panel {
    width: 360px;
    min-width: 320px;
    max-width: 400px;
    background: var(--monitoring-surface);
    border-radius: 16px;
    box-shadow: var(--monitoring-shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex-shrink: 0;
}

.main-content {
    flex: 1;
    min-width: 0;
    background: var(--monitoring-surface);
    border-radius: 16px;
    box-shadow: var(--monitoring-shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Modern Status Overview */
.status-overview {
    background: linear-gradient(135deg, var(--monitoring-primary) 0%, var(--monitoring-primary-dark) 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px 16px 0 0;
}

.status-overview h5 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.status-overview p {
    opacity: 0.9;
    font-size: 0.95rem;
    margin-bottom: 0;
}

.status-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-top: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    padding: 1.25rem;
    border-radius: 12px;
    text-align: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    font-weight: 500;
}

/* Modern Sidebar */
.sidebar-panel .sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--monitoring-divider);
    background: var(--monitoring-surface);
}

.sidebar-panel .sidebar-header h6 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--monitoring-text-primary);
    margin-bottom: 1rem;
}

.sidebar-panel .filter-container {
    margin-top: 0.75rem;
}

.sidebar-panel .filter-container .form-control {
    border-radius: 12px;
    border: 2px solid var(--monitoring-divider);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.sidebar-panel .filter-container .form-control:focus {
    border-color: var(--monitoring-primary);
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.sidebar-panel .sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.sidebar-panel .monitor-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--monitoring-divider);
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--monitoring-surface);
    position: relative;
}

.sidebar-panel .monitor-item:hover {
    background: rgba(25, 118, 210, 0.05);
    transform: translateX(4px);
}

.sidebar-panel .monitor-item.selected {
    background: rgba(25, 118, 210, 0.1);
    border-left: 4px solid var(--monitoring-primary);
}

.sidebar-panel .monitor-item.selected::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--monitoring-primary);
}

.sidebar-panel .monitor-item-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.sidebar-panel .monitor-item-name {
    font-weight: 600;
    color: var(--monitoring-text-primary);
    flex: 1;
    font-size: 0.95rem;
}

.sidebar-panel .monitor-item-status {
    margin-left: auto;
}

.sidebar-panel .monitor-item-username {
    font-size: 0.8rem;
    color: var(--monitoring-text-secondary);
    margin-left: 1.5rem;
    line-height: 1.3;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Modern Main Content */
.main-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--monitoring-divider);
    background: var(--monitoring-surface);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.main-header h6 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--monitoring-text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.main-content-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

/* Modern Screenshot Grid */
.screenshot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.screenshot-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--monitoring-surface);
    box-shadow: var(--monitoring-shadow);
}

.screenshot-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--monitoring-shadow-hover);
}

.screenshot-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.screenshot-card:hover img {
    transform: scale(1.05);
}

.screenshot-info {
    padding: 1rem;
    background: var(--monitoring-surface);
}

.screenshot-badges {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.screenshot-badges .badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-weight: 500;
}

.screenshot-details {
    font-size: 0.8rem;
    color: var(--monitoring-text-secondary);
    line-height: 1.4;
}

.screenshot-timestamp {
    font-size: 0.8rem;
    color: var(--monitoring-text-secondary);
    text-align: center;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

/* Modern Form Controls */
.form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 6px;
    border: 2px solid var(--monitoring-divider);
}

.form-check-input:checked {
    background-color: var(--monitoring-primary);
    border-color: var(--monitoring-primary);
}

.form-select {
    border-radius: 12px;
    border: 2px solid var(--monitoring-divider);
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.form-select:focus {
    border-color: var(--monitoring-primary);
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

/* Modern Buttons */
.btn {
    border-radius: 12px;
    font-weight: 500;
    padding: 0.75rem 1.5rem;
    transition: all 0.2s ease;
    border: none;
    font-size: 0.875rem;
}

.btn-primary {
    background: var(--monitoring-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--monitoring-primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}

.btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    background: transparent;
}

.btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
}

/* Loading States */
.loading-container {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: var(--monitoring-text-secondary);
    flex-direction: column;
    gap: 1rem;
}

.loading-container i {
    font-size: 2rem;
    color: var(--monitoring-primary);
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--monitoring-text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--monitoring-primary);
}

.empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--monitoring-text-primary);
}

/* Modern Modal Styles */
.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
    border-bottom: 1px solid var(--monitoring-divider);
    padding: 1.5rem;
    border-radius: 16px 16px 0 0;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--monitoring-text-primary);
}

.modal-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-actions .btn {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
}

.modal-layout {
    height: calc(100vh - 140px);
}

.screenshot-section {
    height: 100%;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.screenshot-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.screenshot-container img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 8px;
}

/* Log Modal Styles */
.log-sub-modal .modal-dialog {
    max-width: 90%;
    max-height: 85vh;
}

.log-sub-modal .modal-content {
    max-height: 85vh;
    border-radius: 16px;
}

.log-sub-modal .modal-body {
    overflow-y: auto;
    max-height: calc(85vh - 120px);
    padding: 1.5rem;
}

/* Modern Table Styles */
.log-table {
    font-size: 0.875rem;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--monitoring-shadow);
}

.log-table th {
    font-size: 0.8rem;
    padding: 1rem;
    background: var(--monitoring-primary);
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
    border-color: var(--monitoring-divider);
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

/* Modal Log Container */
.modal-log-container {
    height: 100%;
    overflow-y: auto;
    border: 1px solid var(--monitoring-divider);
    border-radius: 12px;
    background: var(--monitoring-surface);
}

.modal-log-pagination {
    padding: 1rem;
    background: var(--monitoring-background);
    border-top: 1px solid var(--monitoring-divider);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 0 0 12px 12px;
}

.modal-log-pagination .pagination {
    margin: 0;
}

.modal-log-pagination .page-link {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 8px;
    margin: 0 0.125rem;
    border: 1px solid var(--monitoring-divider);
    color: var(--monitoring-text-primary);
    transition: all 0.2s ease;
}

.modal-log-pagination .page-item.active .page-link {
    background-color: var(--monitoring-primary);
    border-color: var(--monitoring-primary);
    color: white;
}

.modal-log-pagination .page-item.disabled .page-link {
    color: var(--monitoring-text-secondary);
    background-color: var(--monitoring-surface);
    border-color: var(--monitoring-divider);
}

.modal-log-info {
    font-size: 0.875rem;
    color: var(--monitoring-text-secondary);
    font-weight: 500;
}

/* Header Log Buttons */
.log-buttons {
    display: flex;
    gap: 0.75rem;
}

.log-buttons .btn {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
}

.log-buttons .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Responsive Design */
@media (max-width: 1400px) {
    .dashboard-container {
        gap: 1rem;
        padding: 1rem;
    }
    
    .sidebar-panel {
        width: 320px;
        min-width: 280px;
    }
}

@media (max-width: 1200px) {
    .dashboard-container {
        gap: 0.75rem;
        padding: 0.75rem;
    }
    
    .sidebar-panel {
        width: 300px;
        min-width: 260px;
    }
}

@media (max-width: 992px) {
    .dashboard-container {
        gap: 0.5rem;
        padding: 0.5rem;
    }
    
    .sidebar-panel {
        width: 280px;
        min-width: 240px;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        flex-direction: column;
        height: auto;
        gap: 1rem;
        padding: 1rem;
    }
    
    .sidebar-panel {
        width: 100%;
        min-width: auto;
        max-width: none;
        max-height: 300px;
    }
    
    .screenshot-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .main-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
        gap: 0.5rem;
    }
    
    .screenshot-grid {
        grid-template-columns: 1fr;
    }
    
    .log-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .log-buttons .btn {
        width: 100%;
    }
}

/* Checkbox Styles */
.monitor-checkbox {
    width: 1.25rem;
    height: 1.25rem;
    margin-right: 0.75rem;
    border-radius: 6px;
    border: 2px solid var(--monitoring-divider);
    transition: all 0.2s ease;
}

.monitor-checkbox:checked {
    background-color: var(--monitoring-primary);
    border-color: var(--monitoring-primary);
}

/* Badge Styles */
.badge {
    font-weight: 500;
    border-radius: 20px;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
}

.bg-success {
    background: var(--monitoring-success) !important;
}

.bg-warning {
    background: var(--monitoring-warning) !important;
}

.bg-danger {
    background: var(--monitoring-error) !important;
}

.bg-primary {
    background: var(--monitoring-primary) !important;
}

.bg-secondary {
    background: var(--monitoring-text-secondary) !important;
}

.bg-info {
    background: #17a2b8 !important;
}

/* Scrollbar Styling */
.sidebar-panel .sidebar-content::-webkit-scrollbar,
.main-content-area::-webkit-scrollbar,
.modal-log-container::-webkit-scrollbar {
    width: 6px;
}

.sidebar-panel .sidebar-content::-webkit-scrollbar-track,
.main-content-area::-webkit-scrollbar-track,
.modal-log-container::-webkit-scrollbar-track {
    background: var(--monitoring-background);
}

.sidebar-panel .sidebar-content::-webkit-scrollbar-thumb,
.main-content-area::-webkit-scrollbar-thumb,
.modal-log-container::-webkit-scrollbar-thumb {
    background: var(--monitoring-divider);
    border-radius: 3px;
}

.sidebar-panel .sidebar-content::-webkit-scrollbar-thumb:hover,
.main-content-area::-webkit-scrollbar-thumb:hover,
.modal-log-container::-webkit-scrollbar-thumb:hover {
    background: var(--monitoring-text-secondary);
}
</style>

<!-- Modern Dashboard Container -->
<div class="dashboard-container">
    <!-- Left Sidebar - Monitor List -->
    <div class="sidebar-panel">
        <div class="sidebar-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAllMonitors">
                    <label class="form-check-label" for="selectAllMonitors">
                        Select All
                    </label>
                </div>
            </div>
            <div class="filter-container">
                                 <input type="text" class="form-control" id="monitorFilter" placeholder="Filter by username, type, or IP...">
            </div>
        </div>
        <div class="sidebar-content" id="monitorList">
            <!-- Monitor items will be loaded here -->
        </div>
    </div>
    
    <!-- Right Main Content - Status Overview + Screenshots -->
    <div class="main-content" style="margin-left: 0 !important;">
        
        <!-- Modern Screenshots Section -->
        <div class="main-header">
            <h6>
                <i class="fas fa-image me-2"></i>
                Screenshots
                <span class="badge bg-secondary ms-2" id="screenshotCount">0</span>
            </h6>
            <div class="d-flex align-items-center gap-3">
                <div id="headerDateFilterContainer"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0">Per page:</label>
                    <select class="form-select" id="perPageSelect" style="width: auto;">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="150" selected>150</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="main-content-area">
            <div id="screenshotsContainer">
                <div class="loading-container">
                    <i class="fas fa-spinner fa-spin"></i>
                    <div>Loading monitors...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modern Screenshot Modal -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-image me-2"></i>
                    Screenshot Details
                </h5>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline-primary" id="browserLogBtn" title="View Browser Logs">
                        <i class="fas fa-globe me-1"></i>
                        Browser
                    </button>
                    <button type="button" class="btn btn-outline-warning" id="keyLogBtn" title="View Key Logs">
                        <i class="fas fa-keyboard me-1"></i>
                        Keys
                    </button>
                    <button type="button" class="btn btn-outline-info" id="usbLogBtn" title="View USB Logs">
                        <i class="fas fa-usb me-1"></i>
                        USB
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="modal-layout">
                    <!-- Full Screenshot Area -->
                    <div class="screenshot-section">
                        <div class="screenshot-container">
                            <img id="modalImage" src="" alt="Full Screenshot" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modern Log Sub-Modals -->
<div class="modal fade log-sub-modal" id="browserLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-globe text-primary me-2"></i>
                    Browser Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="browserLogsContent">
                    <div class="loading-container">
                        <i class="fas fa-spinner fa-spin"></i>
                        <div>Loading browser logs...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade log-sub-modal" id="keyLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-keyboard text-warning me-2"></i>
                    Keyboard Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="keyLogsContent">
                    <div class="loading-container">
                        <i class="fas fa-spinner fa-spin"></i>
                        <div>Loading keyboard logs...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade log-sub-modal" id="usbLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-usb text-info me-2"></i>
                    USB Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="usbLogsContent">
                    <div class="loading-container">
                        <i class="fas fa-spinner fa-spin"></i>
                        <div>Loading USB logs...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modern Log Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logModalTitle">
                    <i class="fas fa-file-alt me-2"></i>
                    Log File Content
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Modern Log View Tabs -->
                <ul class="nav nav-tabs" id="logViewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="table-tab" data-bs-toggle="tab" data-bs-target="#table-content" type="button" role="tab">
                            <i class="fas fa-table me-2"></i>
                            Table View
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw-content" type="button" role="tab">
                            <i class="fas fa-file-alt me-2"></i>
                            Raw Text
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content mt-4" id="logViewTabContent">
                    <!-- Table View Tab -->
                    <div class="tab-pane fade show active" id="table-content" role="tabpanel">
                        <div id="logContentContainer">
                            <div class="loading-container">
                                <i class="fas fa-spinner fa-spin"></i>
                                <div>Loading log content...</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Raw Text Tab -->
                    <div class="tab-pane fade" id="raw-content" role="tabpanel">
                        <div id="rawContentContainer">
                            <div class="loading-container">
                                <i class="fas fa-spinner fa-spin"></i>
                                <div>Loading raw content...</div>
                            </div>
                        </div>
                    </div>
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

let refreshInterval;
let isAutoRefresh = true;
let currentPage = 1;
let currentPerPage = 150;
let selectedMonitors = [];
let allMonitors = [];
let filteredMonitors = [];

// Initialize when jQuery is ready
waitForJQuery(function() {
    // Load initial data
    loadMonitors();
    loadScreenshots();
    
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        if (isAutoRefresh) {
            loadMonitors();
            // Preserve current view - if single monitor is selected, keep showing historical screenshots
            if (selectedMonitors.length === 1) {
                loadScreenshotsForMonitor(selectedMonitors[0], currentPage);
            } else {
                loadScreenshots(currentPage);
            }
        }
    }, 30000);
    
    // Event handlers
    setupEventHandlers();
    
    // Handle window resize to ensure proper layout
    $(window).on('resize', function() {
        // Force layout recalculation
        $('.dashboard-container').css('width', '100%');
    });
    
});


function getMonitorTypeIcon(type) {
    switch(type.toLowerCase()) {
        case 'laptop':
            return 'laptop';
        case 'desktop':
            return 'desktop';
        case 'server':
            return 'server';
        default:
            return 'desktop'; // Default fallback
    }
}

function setupEventHandlers() {
    // Select all monitors
    $('#selectAllMonitors').change(function() {
        const isChecked = $(this).is(':checked');
        $('.monitor-checkbox').prop('checked', isChecked);
        
        if (isChecked) {
            selectedMonitors = filteredMonitors.map(m => m.id);
            $('.monitor-item').addClass('selected');
        } else {
            selectedMonitors = [];
            $('.monitor-item').removeClass('selected');
        }
        
        loadScreenshots();
    });
    
    // Individual monitor selection
    $(document).on('change', '.monitor-checkbox', function() {
        const monitorId = parseInt($(this).val());
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            if (!selectedMonitors.includes(monitorId)) {
                selectedMonitors.push(monitorId);
            }
            $(this).closest('.monitor-item').addClass('selected');
        } else {
            selectedMonitors = selectedMonitors.filter(id => id !== monitorId);
            $(this).closest('.monitor-item').removeClass('selected');
        }
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        loadScreenshots();
    });
    
    // Click anywhere on monitor item to toggle checkbox
    $(document).on('click', '.monitor-item', function(e) {
        // Don't trigger if clicking directly on the checkbox
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label')) {
            return;
        }
        
        const checkbox = $(this).find('.monitor-checkbox');
        const monitorId = parseInt(checkbox.val());
        
        // Clear all selections first
        $('.monitor-checkbox').prop('checked', false);
        $('.monitor-item').removeClass('selected');
        selectedMonitors = [];
        
        // Select only this monitor
        checkbox.prop('checked', true);
        $(this).addClass('selected');
        selectedMonitors = [monitorId];
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        // Load screenshots for this specific monitor
        loadScreenshotsForMonitor(monitorId);
        
        // Also load logs for this monitor
        loadLogsForMonitor(monitorId);
    });
    
    // Monitor filter
    $('#monitorFilter').on('input', function() {
        filterMonitors();
    });
    
    // Per-page selector
    $('#perPageSelect').change(function() {
        currentPerPage = parseInt($(this).val());
        loadScreenshots(1);
    });
    
    // Refresh button
    $('#refresh-btn').click(function() {
        loadMonitors();
        // Preserve current view - if single monitor is selected, keep showing historical screenshots
        if (selectedMonitors.length === 1) {
            loadScreenshotsForMonitor(selectedMonitors[0], currentPage);
        } else {
            loadScreenshots(currentPage);
        }
    });
    
    // Auto-refresh toggle
    $('#status-indicator').click(function() {
        isAutoRefresh = !isAutoRefresh;
        if (isAutoRefresh) {
            $(this).removeClass("bg-secondary").addClass("bg-success").text("Auto-refresh: ON");
        } else {
            $(this).removeClass("bg-success").addClass("bg-secondary").text("Auto-refresh: OFF");
        }
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
            
            return 
                   username.includes(filterText) || 
                   type.includes(filterText) ||
                   ip.includes(filterText);
        });
    }
    
    updateMonitorList(filteredMonitors);
    updateSelectAllCheckbox();
}

function updateSelectAllCheckbox() {
    const totalMonitors = filteredMonitors.length;
    const selectedCount = selectedMonitors.length;
    
    if (selectedCount === 0) {
        $('#selectAllMonitors').prop('indeterminate', false).prop('checked', false);
    } else if (selectedCount === totalMonitors) {
        $('#selectAllMonitors').prop('indeterminate', false).prop('checked', true);
    } else {
        $('#selectAllMonitors').prop('indeterminate', true).prop('checked', false);
    }
}

function loadMonitors() {
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_live_status",
        method: "GET",
        success: function(data) {
            allMonitors = data;
            
            // Preserve current selection when refreshing
            if (selectedMonitors.length > 0) {
                // Keep only monitors that still exist
                selectedMonitors = selectedMonitors.filter(id => 
                    data.some(monitor => monitor.id === id)
                );
            }
            
            filteredMonitors = [...data];
            updateMonitorList(filteredMonitors);
            updateStatusStats(data);
        },
        error: function(xhr, status, error) {
            console.log("Failed to load monitors:", error);
        }
    });
}

// Enhanced time formatting function
function formatTimeAgo(seconds) {
    if (seconds < 60) {
        return `${seconds}s ago`;
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}m ${remainingSeconds}s ago`;
    } else if (seconds < 86400) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        return `${hours}h ${minutes}m ${remainingSeconds}s ago`;
    } else {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${days}d ${hours}h ${minutes}m ago`;
    }
}

function updateMonitorList(monitors) {
    let html = '';
    
    monitors.forEach(function(monitor) {
        const isSelected = selectedMonitors.includes(monitor.id);
        
        // Use the status calculated by the backend
        let statusText = monitor.status_text || 'Offline';
        let statusClass = monitor.live_status || 'offline';
        

        
        // Fallback calculation if backend status is not available
        if (!monitor.live_status && monitor.latest_activity) {
            const timeAgo = Math.floor((Date.now() / 1000) - monitor.latest_activity);
            if (timeAgo < 600) { // 10 minutes - match backend logic (2 Tic cycles)
                statusText = 'Online';
                statusClass = 'online';
            } else if (timeAgo < 1800) { // 30 minutes - match backend logic (6 Tic cycles)
                statusText = 'Online';
                statusClass = 'online';
            } else if (timeAgo < 3600) { // 1 hour - match backend logic (12 Tic cycles)
                statusText = 'Inactive';
                statusClass = 'inactive';
            } else {
                statusText = 'Offline';
                statusClass = 'offline';
            }
        } else if (!monitor.latest_activity) {
            statusText = 'Never';
            statusClass = 'offline';
        }
        
        html += `
            <div class="monitor-item ${isSelected ? 'selected' : ''}">
                <div class="monitor-item-header">
                    <input type="checkbox" class="monitor-checkbox" value="${monitor.id}" ${isSelected ? 'checked' : ''}>
                    <div class="monitor-item-name">${monitor.username}</div>
                    <div class="monitor-item-status">
                        <span class="live-status-badge status-${statusClass}">
                            <i class="fas fa-circle"></i>
                            <span>${statusText}</span>
                            ${statusClass === 'online' ? '<div class="pulse"></div>' : ''}
                        </span>
                    </div>
                </div>
                                 ${monitor.type && monitor.type !== 'Unknown' ? 
                     `<div class="monitor-item-username">
                         <i class="fas fa-${getMonitorTypeIcon(monitor.type)}"></i>
                         <span>${monitor.type}</span>
                     </div>` : ''}
            </div>
        `;
    });
    
    $('#monitorList').html(html);
    
    // Update select all checkbox after restoring selection
    updateSelectAllCheckbox();
}

function updateStatusStats(monitors) {
    const total = monitors.length;
    const online = monitors.filter(m => m.live_status === 'online').length;
    const inactive = monitors.filter(m => m.live_status === 'inactive').length;
    const offline = monitors.filter(m => m.live_status === 'offline').length;
    
    $('#total-monitors').text(total);
    $('#online-monitors').text(online);
    $('#active-monitors').text(inactive + online); // Active = online + inactive
    $('#total-screenshots').text(monitors.filter(m => m.live_status === 'online' || m.live_status === 'inactive').length);
}

function loadScreenshotsForMonitor(monitorId) {
    currentPage = 1;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: currentPerPage,
        monitor_id: monitorId
    });
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if (response.screenshots) {
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to load screenshots:", error);
            $('#screenshotsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Screenshots</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function loadScreenshots(page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: page,
        per_page: currentPerPage
    });
    
    if (selectedMonitors.length > 0) {
        params.append('monitors', selectedMonitors.join(','));
    }
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if (response.screenshots) {
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to load screenshots:", error);
            $('#screenshotsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Screenshots</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function displayScreenshots(screenshots, pagination = null) {
    const container = $('#screenshotsContainer');
    
    if (screenshots.length === 0) {
        container.html(`
            <div class="empty-state">
                <i class="fas fa-image"></i>
                <h5>No Screenshots Available</h5>
                <p>No screenshots have been captured for the selected monitors.</p>
            </div>
        `);
        $('#screenshotCount').text("0");
        return;
    }
    
    // Update screenshot count
    if (pagination) {
        $('#screenshotCount').text(pagination.total_screenshots);
    } else {
        $('#screenshotCount').text(screenshots.length);
    }
    
    // Check if this is a single monitor view
    const isSingleMonitor = selectedMonitors.length === 1;
    
    // Update header buttons based on view type
    updateHeaderButtons(isSingleMonitor);
    
    let html = '<div class="screenshot-grid">';
    screenshots.forEach(function(item) {
        const monitor = item.monitor;
        const thumbnailUrl = "<?php echo BASE_URL; ?>" + item.thumbnail;
        const fullUrl = "<?php echo BASE_URL; ?>" + item.screenshot;
        
        let timestamp;
        if (item.modified_time) {
            timestamp = new Date(item.modified_time).toLocaleString();
        } else {
            timestamp = new Date().toLocaleString();
        }
        
        // Calculate time ago
        const screenshotTime = new Date(item.modified_time);
        const timeDiff = Date.now() - screenshotTime.getTime();
        const timeDiffSeconds = Math.floor(timeDiff / 1000);
        let timeAgo = '';

        if (timeDiffSeconds <= 0) { // exactly 0 seconds or negative (future time)
            timeAgo = 'Just now';
        } else {
            timeAgo = formatTimeAgo(timeDiffSeconds);
        }
        
        if (isSingleMonitor) {
            // Single monitor view - show enhanced time ago and timestamp
            html += `
                <div class="screenshot-card" onclick="viewScreenshot('${fullUrl}', '${monitor.username}', '${monitor.ip_address}')">
                    <img src="${thumbnailUrl}" alt="Screenshot" loading="lazy">
                    <div class="screenshot-info">
                        <div class="screenshot-timestamp">
                            <i class="fas fa-${getMonitorTypeIcon(monitor.type)} me-1"></i>${monitor.type}
                        </div>
                        <div class="screenshot-timestamp">
                            <i class="fas fa-calendar me-1"></i>${timestamp}
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Multiple monitor view - show full info
            const usernameHtml = (monitor.username && monitor.username !== 'Unknown') 
                ? `<span class="badge bg-primary"><i class="fas fa-user me-1"></i>${monitor.username}</span>` 
                : '';
            
            html += `
                <div class="screenshot-card" onclick="viewScreenshot('${fullUrl}', '${monitor.username}', '${monitor.ip_address}')">
                    <img src="${thumbnailUrl}" alt="Screenshot" loading="lazy">
                    <div class="screenshot-info">
                        <div class="screenshot-badges">
                            ${usernameHtml}
                            <span class="badge bg-secondary"><i class="fas fa-globe me-1"></i>${monitor.ip_address}</span>
                            <!--<span class="badge bg-info">${timeAgo}</span>-->
                            <span class="badge bg-info"><i class="fas fa-${getMonitorTypeIcon(monitor.type)} me-1"></i></span>
                        </div>
                        <div class="screenshot-details">
                            <div><i class="fas fa-clock me-1"></i>${timestamp}</div>
                        </div>
                    </div>
                </div>
            `;
        }
    });
    html += '</div>';
    
    // Add pagination if needed
    if (pagination && pagination.total_pages > 1) {
        html += generatePagination(pagination);
    }
    
    container.html(html);
}

function updateHeaderButtons(isSingleMonitor) {
    const headerRight = $('.main-header .d-flex.justify-content-between.align-items-center > div:last-child');
    
    if (isSingleMonitor) {
        // Add log buttons to header
        if (headerRight.find('.log-buttons').length === 0) {
            headerRight.prepend(`
                <div class="log-buttons me-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="headerBrowserLogBtn" title="View Browser Logs">
                        <i class="fas fa-globe"></i> Browser
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="headerKeyLogBtn" title="View Key Logs">
                        <i class="fas fa-keyboard"></i> Keys
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" id="headerUsbLogBtn" title="View USB Logs">
                        <i class="fas fa-usb"></i> USB
                    </button>
                </div>
            `);
            
            // Add date filter
            const today = new Date().toISOString().split('T')[0];
            const dateFilterHtml = `
                <form id="headerDateFilterForm" class="d-flex flex-wrap align-items-center gap-2 mb-0">
                    <label for="headerStartDate" class="form-label mb-0">Start:</label>
                    <input type="date" id="headerStartDate" name="start_date" class="form-control form-control-sm" style="width: 140px;" value="${today}">
                    <label for="headerEndDate" class="form-label mb-0">End:</label>
                    <input type="date" id="headerEndDate" name="end_date" class="form-control form-control-sm" style="width: 140px;" value="${today}">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <button type="button" id="headerClearDateFilter" class="btn btn-sm btn-secondary">Clear</button>
                </form>
            `;
            $('#headerDateFilterContainer').html(dateFilterHtml);
            
            // Setup date filter handlers
            $('#headerDateFilterForm').off('submit').on('submit', function(e) {
                e.preventDefault();
                // Set global date filter values and reload screenshots
                window.headerStartDate = $('#headerStartDate').val();
                window.headerEndDate = $('#headerEndDate').val();
                console.log('Date filter applied:', window.headerStartDate, 'to', window.headerEndDate);
                loadScreenshotsForSelectedMonitor(1);
            });
            $('#headerClearDateFilter').off('click').on('click', function() {
                $('#headerStartDate').val('');
                $('#headerEndDate').val('');
                window.headerStartDate = '';
                window.headerEndDate = '';
                console.log('Date filter cleared');
                loadScreenshotsForSelectedMonitor(1);
            });
            
            // Setup header log button handlers
            setupHeaderLogButtonHandlers();
        }
    } else {
        // Remove log buttons and date filter from header
        headerRight.find('.log-buttons').remove();
        $('#headerDateFilterContainer').empty();
    }
}

function setupHeaderLogButtonHandlers() {
    // Browser log button
    $('#headerBrowserLogBtn').off('click').on('click', function() {
        if (currentLogData.browser.data.length > 0) {
            displayLogTableWithPagination('browser', $("#browserLogsContent"));
            $('#browserLogModal').modal('show');
        } else {
            alert('No browser logs available for this monitor.');
        }
    });
    
    // Key log button
    $('#headerKeyLogBtn').off('click').on('click', function() {
        if (currentLogData.key.data.length > 0) {
            displayLogTableWithPagination('key', $("#keyLogsContent"));
            $('#keyLogModal').modal('show');
        } else {
            alert('No key logs available for this monitor.');
        }
    });
    
    // USB log button
    $('#headerUsbLogBtn').off('click').on('click', function() {
        if (currentLogData.usb.data.length > 0) {
            displayLogTableWithPagination('usb', $("#usbLogsContent"));
            $('#usbLogModal').modal('show');
        } else {
            alert('No USB logs available for this monitor.');
        }
    });
}

function generatePagination(pagination) {
    return `
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted">
                Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total_screenshots)} of ${pagination.total_screenshots} screenshots
            </div>
            <nav aria-label="Screenshots Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${pagination.current_page - 1}); return false;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    ${generatePageNumbers(pagination.current_page, pagination.total_pages)}
                    
                    <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${pagination.current_page + 1}); return false;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    `;
}

function generatePageNumbers(currentPage, totalPages) {
    let html = '';
    const maxVisible = 5;
    
    if (totalPages <= maxVisible) {
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${i}); return false;">${i}</a>
                </li>
            `;
        }
    } else {
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        if (start > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(1); return false;">1</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
        
        for (let i = start; i <= end; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (end < totalPages) {
            html += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${totalPages}); return false;">${totalPages}</a>
                </li>
            `;
        }
    }
    
    return html;
}

function loadScreenshotsForSelectedMonitor(page) {
    if (selectedMonitors.length === 1) {
        loadScreenshotsForMonitor(selectedMonitors[0], page);
        // Also load logs for the selected monitor
        loadLogsForMonitor(selectedMonitors[0]);
    } else {
        loadScreenshots(page);
        // Clear log data when no single monitor is selected
        currentLogData = {
            browser: { data: [], headers: [], currentPage: 1, perPage: 20 },
            key: { data: [], headers: [], currentPage: 1, perPage: 20 },
            usb: { data: [], headers: [], currentPage: 1, perPage: 20 }
        };
    }
}

function loadScreenshotsForMonitor(monitorId, page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: currentPerPage,
        monitor_id: monitorId
    });
    
    // Add date filter parameters if they exist
    if (window.headerStartDate) {
        params.append('start_date', window.headerStartDate);
    }
    if (window.headerEndDate) {
        params.append('end_date', window.headerEndDate);
    }
    
    console.log('Loading screenshots with params:', params.toString());
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if (response.screenshots) {
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to load screenshots:", error);
            $('#screenshotsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Screenshots</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function viewScreenshot(imageUrl, monitorName, ipAddress) {
    $("#modalImage").attr("src", imageUrl);
    $("#modalTitle").text(`Screenshot - ${monitorName} (${ipAddress})`);
    $("#screenshotModal").modal("show");
    
    // Load logs for this IP
    loadLogsForScreenshot(ipAddress);
    
    // Setup log button event handlers
    setupLogButtonHandlers();
}

function setupLogButtonHandlers() {
    // Browser log button
    $('#browserLogBtn').off('click').on('click', function() {
        if (currentLogData.browser.data.length > 0) {
            displayLogTableWithPagination('browser', $("#browserLogsContent"));
            $('#browserLogModal').modal('show');
        } else {
            alert('No browser logs available for this monitor.');
        }
    });
    
    // Key log button
    $('#keyLogBtn').off('click').on('click', function() {
        if (currentLogData.key.data.length > 0) {
            displayLogTableWithPagination('key', $("#keyLogsContent"));
            $('#keyLogModal').modal('show');
        } else {
            alert('No key logs available for this monitor.');
        }
    });
    
    // USB log button
    $('#usbLogBtn').off('click').on('click', function() {
        if (currentLogData.usb.data.length > 0) {
            displayLogTableWithPagination('usb', $("#usbLogsContent"));
            $('#usbLogModal').modal('show');
        } else {
            alert('No USB logs available for this monitor.');
        }
    });
}

function loadLogsForMonitor(monitorId) {
    // Find monitor by ID
    const monitor = allMonitors.find(m => m.id === monitorId);
    
    if (monitor) {
        $.ajax({
            url: "<?php echo BASE_URL; ?>view/api_logs/" + monitor.id,
            method: "GET",
            success: function(data) {
                displayLogsInModal(data);
            },
            error: function(xhr, status, error) {
                console.log("Failed to load logs:", error);
                // Reset log data on error
                currentLogData = {
                    browser: { data: [], headers: [], currentPage: 1, perPage: 20 },
                    key: { data: [], headers: [], currentPage: 1, perPage: 20 },
                    usb: { data: [], headers: [], currentPage: 1, perPage: 20 }
                };
            }
        });
    }
}

function loadLogsForScreenshot(ipAddress) {
    // Show loading state
    $("#browserLogsContent, #keyLogsContent, #usbLogsContent").html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    
    // Find monitor by IP address
    const monitor = allMonitors.find(m => m.ip_address === ipAddress);
    
    if (monitor) {
        $.ajax({
            url: "<?php echo BASE_URL; ?>view/api_logs/" + monitor.id,
            method: "GET",
            success: function(data) {
                displayLogsInModal(data);
            },
            error: function(xhr, status, error) {
                console.log("Failed to load logs:", error);
                $("#browserLogsContent, #keyLogsContent, #usbLogsContent").html('<div class="text-danger">Failed to load logs</div>');
            }
        });
    }
}

// Global variables for log pagination
let currentLogData = {
    browser: { data: [], headers: [], currentPage: 1, perPage: 20 },
    key: { data: [], headers: [], currentPage: 1, perPage: 20 },
    usb: { data: [], headers: [], currentPage: 1, perPage: 20 }
};

function displayLogsInModal(logs) {
    // Display browser logs
    if (logs.browser_history && logs.browser_history.length > 0) {
        const latestBrowserLog = logs.browser_history[0];
        $.get("<?php echo BASE_URL; ?>" + latestBrowserLog.path, function(data) {
            const parsedData = parseLogData(data, 'browser_history');
            if (parsedData.data.length > 0) {
                currentLogData.browser.data = parsedData.data;
                currentLogData.browser.headers = parsedData.headers;
                currentLogData.browser.currentPage = 1;
                // Only update modal content if modal exists
                if ($("#browserLogsContent").length > 0) {
                    displayLogTableWithPagination('browser', $("#browserLogsContent"));
                }
            } else {
                if ($("#browserLogsContent").length > 0) {
                    $("#browserLogsContent").html('<div class="text-muted">No browser logs available</div>');
                }
            }
        }).fail(function() {
            if ($("#browserLogsContent").length > 0) {
                $("#browserLogsContent").html('<div class="text-muted">No browser logs available</div>');
            }
        });
    } else {
        if ($("#browserLogsContent").length > 0) {
            $("#browserLogsContent").html('<div class="text-muted">No browser logs available</div>');
        }
    }
    
    // Display key logs
    if (logs.key_logs && logs.key_logs.length > 0) {
        const latestKeyLog = logs.key_logs[0];
        $.get("<?php echo BASE_URL; ?>" + latestKeyLog.path, function(data) {
            const parsedData = parseLogData(data, 'key_logs');
            if (parsedData.data.length > 0) {
                currentLogData.key.data = parsedData.data;
                currentLogData.key.headers = parsedData.headers;
                currentLogData.key.currentPage = 1;
                // Only update modal content if modal exists
                if ($("#keyLogsContent").length > 0) {
                    displayLogTableWithPagination('key', $("#keyLogsContent"));
                }
            } else {
                if ($("#keyLogsContent").length > 0) {
                    $("#keyLogsContent").html('<div class="text-muted">No key logs available</div>');
                }
            }
        }).fail(function() {
            if ($("#keyLogsContent").length > 0) {
                $("#keyLogsContent").html('<div class="text-muted">No key logs available</div>');
            }
        });
    } else {
        if ($("#keyLogsContent").length > 0) {
            $("#keyLogsContent").html('<div class="text-muted">No key logs available</div>');
        }
    }
    
    // Display USB logs
    if (logs.usb_logs && logs.usb_logs.length > 0) {
        const latestUsbLog = logs.usb_logs[0];
        $.get("<?php echo BASE_URL; ?>" + latestUsbLog.path, function(data) {
            const parsedData = parseLogData(data, 'usb_logs');
            if (parsedData.data.length > 0) {
                currentLogData.usb.data = parsedData.data;
                currentLogData.usb.headers = parsedData.headers;
                currentLogData.usb.currentPage = 1;
                // Only update modal content if modal exists
                if ($("#usbLogsContent").length > 0) {
                    displayLogTableWithPagination('usb', $("#usbLogsContent"));
                }
            } else {
                if ($("#usbLogsContent").length > 0) {
                    $("#usbLogsContent").html('<div class="text-muted">No USB logs available</div>');
                }
            }
        }).fail(function() {
            if ($("#usbLogsContent").length > 0) {
                $("#usbLogsContent").html('<div class="text-muted">No USB logs available</div>');
            }
        });
    } else {
        if ($("#usbLogsContent").length > 0) {
            $("#usbLogsContent").html('<div class="text-muted">No USB logs available</div>');
        }
    }
    
    // Update timestamp
    // $("#logsTimestamp").text("Last updated: " + new Date().toLocaleString()); // This line is removed as per new_code
}

function displayLogTableWithPagination(logType, container) {
    const logData = currentLogData[logType];
    if (!logData.data || logData.data.length === 0) {
        container.html('<div class="text-muted">No log entries available</div>');
        return;
    }
    
    const totalEntries = logData.data.length;
    const totalPages = Math.ceil(totalEntries / logData.perPage);
    const startIndex = (logData.currentPage - 1) * logData.perPage;
    const endIndex = Math.min(startIndex + logData.perPage, totalEntries);
    const displayData = logData.data.slice(startIndex, endIndex);
    
    let html = `
        <div class="modal-log-container">
            <div class="table-responsive">
                <table class="table table-sm log-table mb-0">
                    <thead class="sticky-top bg-light">
                        <tr>
    `;
    
    logData.headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    displayData.forEach(row => {
        html += '<tr>';
        logData.headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            let displayValue = value;
            if (key === 'url' && value.length > 50) {
                displayValue = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 200px;">${value.substring(0, 47)}...</a>`;
            } else if (key === 'url') {
                displayValue = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 40) {
                displayValue = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 150px;">${value.substring(0, 37)}...</span>`;
            } else if (key === 'key') {
                displayValue = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                displayValue = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                displayValue = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                displayValue = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                displayValue = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    displayValue = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    displayValue = `<small class="text-muted">${value}</small>`;
                }
            } else if (key === 'content') {
                displayValue = `<code class="small">${value}</code>`;
            } else if (key === 'line') {
                displayValue = `<span class="badge bg-secondary">${value}</span>`;
            }
            
            html += `<td>${displayValue}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    // Add pagination
    if (totalPages > 1) {
        html += generateModalPagination(logType, logData.currentPage, totalPages, totalEntries, startIndex + 1, endIndex);
    } else {
        html += `
            <div class="modal-log-pagination">
                <div class="modal-log-info">
                    Showing ${totalEntries} of ${totalEntries} entries
                </div>
            </div>
        `;
    }
    
    container.html(html);
}

function generateModalPagination(logType, currentPage, totalPages, totalEntries, startEntry, endEntry) {
    let html = `
        <div class="modal-log-pagination">
            <div class="modal-log-info">
                Showing ${startEntry} to ${endEntry} of ${totalEntries} entries
            </div>
            <nav aria-label="Log Pagination">
                <ul class="pagination pagination-sm mb-0">
    `;
    
    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${currentPage - 1}); return false;">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Page numbers
    const maxVisible = 5;
    if (totalPages <= maxVisible) {
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${i}); return false;">${i}</a>
                </li>
            `;
        }
    } else {
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        if (start > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', 1); return false;">1</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
        
        for (let i = start; i <= end; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (end < totalPages) {
            html += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${totalPages}); return false;">${totalPages}</a>
                </li>
            `;
        }
    }
    
    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${currentPage + 1}); return false;">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    `;
    
    html += `
                </ul>
            </nav>
        </div>
    `;
    
    return html;
}

function changeLogPage(logType, page) {
    const logData = currentLogData[logType];
    if (page >= 1 && page <= Math.ceil(logData.data.length / logData.perPage)) {
        logData.currentPage = page;
        displayLogTableWithPagination(logType, $(`#${logType}LogsContent`)); // Changed to logTypeLogsContent
    }
}

function displayLogTable(data, headers, logType, container) {
    if (!data || data.length === 0) {
        container.html('<div class="text-muted">No log entries available</div>');
        return;
    }
    
    // Limit to first 10 entries for modal display
    const displayData = data.slice(0, 10);
    
    let html = `
        <div class="table-responsive">
            <table class="table table-sm log-table">
                <thead>
                    <tr>
    `;
    
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    displayData.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            let displayValue = value;
            if (key === 'url' && value.length > 50) {
                displayValue = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 200px;">${value.substring(0, 47)}...</a>`;
            } else if (key === 'url') {
                displayValue = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 40) {
                displayValue = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 150px;">${value.substring(0, 37)}...</span>`;
            } else if (key === 'key') {
                displayValue = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                displayValue = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                displayValue = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                displayValue = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                displayValue = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    displayValue = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    displayValue = `<small class="text-muted">${value}</small>`;
                }
            } else if (key === 'content') {
                displayValue = `<code class="small">${value}</code>`;
            } else if (key === 'line') {
                displayValue = `<span class="badge bg-secondary">${value}</span>`;
            }
            
            html += `<td>${displayValue}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    if (data.length > 10) {
        html += `<div class="text-muted text-center mt-2"><small>Showing first 10 of ${data.length} entries</small></div>`;
    }
    
    container.html(html);
}

// Log parsing functions (from logs_detail.php)
function parseLogData(data, logType) {
    if (!data || data.trim() === '') {
        return { data: [], headers: [] };
    }
    
    const lines = data.split('\n').filter(line => line.trim() !== '');
    
    switch (logType) {
        case 'browser_history':
            return parseBrowserHistory(lines);
        case 'key_logs':
            return parseKeyLogs(lines);
        case 'usb_logs':
            return parseUsbLogs(lines);
        default:
            return parseGenericLog(lines);
    }
}

function parseBrowserHistory(lines) {
    const headers = ['Date', 'Browser', 'URL', 'Title'];
    const data = [];
    
    lines.forEach(line => {
        const match = line.match(/Date:\s*(.+?)\s*\|\s*Browser:\s*(.+?)\s*\|\s*URL:\s*(.+?)\s*\|\s*Title:\s*(.+?)\s*(?:\||$)/);
        if (match) {
            data.push({
                date: match[1].trim(),
                browser: match[2].trim(),
                url: match[3].trim(),
                title: match[4].trim()
            });
        } else {
            const alMatch = line.match(/Date:\s*(.+?)\s*\|\s*URL:\s*(.+?)\s*\|\s*Title:\s*(.+?)\s*(?:\||$)/);
            if (altMatch) {
                data.push({
                    date: altMatch[1].trim(),
                    browser: 'Unknown',
                    url: altMatch[2].trim(),
                    title: altMatch[3].trim()
                });
            } else {
                data.push({
                    date: new Date().toLocaleString(),
                    browser: 'Unknown',
                    url: line,
                    title: 'Unknown'
                });
            }
        }
    });
    
    return { data, headers };
}

function parseKeyLogs(lines) {
    const headers = ['Date', 'Application', 'Key', 'Action'];
    const data = [];
    
    lines.forEach(line => {
        let match = line.match(/Date: (.+?) \| Application: (.+?) \| Key: (.+?) \| Action: (.+)/);
        
        if (!match) {
            match = line.match(/Date: (.+?) \| Key: (.+?) \| Action: (.+)/);
            if (match) {
                data.push({
                    date: match[1].trim(),
                    application: 'Unknown',
                    key: match[2].trim(),
                    action: match[3].trim()
                });
                return;
            }
        }
        
        if (!match) {
            match = line.match(/Date: (.+?) \| Application: (.+?) \| Key: (.+)/);
            if (match) {
                data.push({
                    date: match[1].trim(),
                    application: match[2].trim(),
                    key: match[3].trim(),
                    action: 'Unknown'
                });
                return;
            }
        }
        
        if (!match) {
            match = line.match(/Date: (.+?) \| Key: (.+)/);
            if (match) {
                data.push({
                    date: match[1].trim(),
                    application: 'Unknown',
                    key: match[2].trim(),
                    action: 'Unknown'
                });
                return;
            }
        }
        
        if (!match) {
            const dateMatch = line.match(/Date:\s*([^|]+)/);
            const keyMatch = line.match(/Key:\s*([^|]+)/);
            const appMatch = line.match(/Application:\s*([^|]+)/);
            const actionMatch = line.match(/Action:\s*([^|]+)/);
            
            if (dateMatch || keyMatch || appMatch || actionMatch) {
                data.push({
                    date: dateMatch ? dateMatch[1].trim() : new Date().toLocaleString(),
                    application: appMatch ? appMatch[1].trim() : 'Unknown',
                    key: keyMatch ? keyMatch[1].trim() : 'Unknown',
                    action: actionMatch ? actionMatch[1].trim() : 'Unknown'
                });
                return;
            }
        }
        
        if (match) {
            data.push({
                date: match[1].trim(),
                application: match[2].trim(),
                key: match[3].trim(),
                action: match[4].trim()
            });
        } else {
            data.push({
                date: new Date().toLocaleString(),
                application: 'Unknown',
                key: 'Unknown',
                action: line
            });
        }
    });
    
    return { data, headers };
}

function parseUsbLogs(lines) {
    const headers = ['Date', 'Device', 'Action', 'Details'];
    const data = [];
    
    lines.forEach(line => {
        const match = line.match(/Date: (.+?) \| Device: (.+?) \| Action: (.+?) \| Details: (.+)/);
        if (match) {
            data.push({
                date: match[1].trim(),
                device: match[2].trim(),
                action: match[3].trim(),
                details: match[4].trim()
            });
        } else {
            const altMatch = line.match(/Date: (.+?) \| Device: (.+?) \| Action: (.+)/);
            if (altMatch) {
                data.push({
                    date: altMatch[1].trim(),
                    device: altMatch[2].trim(),
                    action: altMatch[3].trim(),
                    details: 'No details'
                });
            } else {
                data.push({
                    date: new Date().toLocaleString(),
                    device: 'Unknown',
                    action: 'Unknown',
                    details: line
                });
            }
        }
    });
    
    return { data, headers };
}

function parseGenericLog(lines) {
    const headers = ['Line', 'Content'];
    const data = [];
    
    lines.forEach((line, index) => {
        data.push({
            line: index + 1,
            content: line
        });
    });
    
    return { data, headers };
}

function viewLogFile(filePath) {
    let logType = 'generic';
    if (filePath.includes('browser_history')) {
        logType = 'browser_history';
    } else if (filePath.includes('key_logs')) {
        logType = 'key_logs';
    } else if (filePath.includes('usb_logs')) {
        logType = 'usb_logs';
    }
    
    $("#logContentContainer").html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
            <p class="text-muted">Loading log content...</p>
        </div>
    `);
    
    $("#rawContentContainer").html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
            <p class="text-muted">Loading raw content...</p>
        </div>
    `);
    
    const fileUrl = "<?php echo BASE_URL; ?>" + filePath;
    $.get(fileUrl, function(data) {
        displayRawContent(data, filePath);
        const parsedData = parseLogData(data, logType);
        displayParsedLog(parsedData.data, parsedData.headers, logType, filePath);
    }).fail(function(xhr, status, error) {
        const errorHtml = `
            <div class="text-center py-4">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                <h5 class="text-danger">Failed to Load Content</h5>
                <p class="text-muted">Error: ${error} (Status: ${xhr.status})</p>
            </div>
        `;
        $("#logContentContainer").html(errorHtml);
        $("#rawContentContainer").html(errorHtml);
    });
    
    $("#logModalTitle").text("Log Content - " + filePath.split('/').pop());
    $("#logModal").modal("show");
}

function displayParsedLog(data, headers, logType, filePath) {
    if (!data || data.length === 0) {
        $("#logContentContainer").html(`
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Log Entries</h5>
                <p class="text-muted">This log file contains no parseable entries.</p>
            </div>
        `);
        return;
    }
    
    const icons = {
        'browser_history': 'fas fa-globe text-primary',
        'key_logs': 'fas fa-keyboard text-warning',
        'usb_logs': 'fas fa-usb text-info',
        'generic': 'fas fa-file-alt text-secondary'
    };
    
    const icon = icons[logType] || icons['generic'];
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="${icon} me-2"></i>
                <span class="fw-bold">${data.length} log entries</span>
            </div>
            <div class="btn-group" role="group">
                <input type="text" class="form-control form-control-sm me-2" id="logSearchInput" placeholder="Search logs..." style="width: 200px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadLogFile('${filePath}')">
                    <i class="fas fa-download me-1"></i>Download Raw
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="logDataTable">
                <thead class="table-light">
                    <tr>
    `;
    
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            let displayValue = value;
            if (key === 'url' && value.length > 80) {
                displayValue = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 300px;">${value.substring(0, 77)}...</a>`;
            } else if (key === 'url') {
                displayValue = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 60) {
                displayValue = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 250px;">${value.substring(0, 57)}...</span>`;
            } else if (key === 'key') {
                displayValue = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                displayValue = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                displayValue = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                displayValue = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                displayValue = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    displayValue = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    displayValue = `<small class="text-muted">${value}</small>`;
                }
            } else if (key === 'content') {
                displayValue = `<code class="small">${value}</code>`;
            } else if (key === 'line') {
                displayValue = `<span class="badge bg-secondary">${value}</span>`;
            }
            
            html += `<td>${displayValue}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    $("#logContentContainer").html(html);
    
    $("#logSearchInput").on("keyup", function() {
        const searchTerm = $(this).val().toLowerCase();
        $("#logDataTable tbody tr").each(function() {
            const rowText = $(this).text().toLowerCase();
            if (rowText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
}

function displayRawContent(data, filePath) {
    if (!data) {
        $("#rawContentContainer").html(`
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Raw Content</h5>
                <p class="text-muted">This log file is empty or could not be loaded.</p>
            </div>
        `);
        return;
    }

    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="fas fa-file-alt text-secondary me-2"></i>
                <span class="fw-bold">Raw Log Content (${data.split('\n').length} lines)</span>
            </div>
            <div class="btn-group" role="group">
                <input type="text" class="form-control form-control-sm me-2" id="rawSearchInput" placeholder="Search raw content..." style="width: 200px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadLogFile('${filePath}')">
                    <i class="fas fa-download me-1"></i>Download Raw
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="rawDataTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">Line</th>
                        <th>Content</th>
                    </tr>
                </thead>
                <tbody>
    `;

    const lines = data.split('\n');
    lines.forEach((line, index) => {
        if (line.trim() === '') {
            html += `
                <tr class="table-light">
                    <td class="text-muted">${index + 1}</td>
                    <td class="text-muted"><em>Empty line</em></td>
                </tr>
            `;
        } else {
            html += `
                <tr>
                    <td class="text-muted fw-bold">${index + 1}</td>
                    <td><code class="small">${line}</code></td>
                </tr>
            `;
        }
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    $("#rawContentContainer").html(html);
    
    $("#rawSearchInput").on("keyup", function() {
        const searchTerm = $(this).val().toLowerCase();
        $("#rawDataTable tbody tr").each(function() {
            const rowText = $(this).text().toLowerCase();
            if (rowText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
}

function downloadLogFile(filePath) {
    const fileUrl = "<?php echo BASE_URL; ?>" + filePath;
    window.open(fileUrl, '_blank');
}
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 