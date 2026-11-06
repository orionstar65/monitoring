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
    min-height: calc(100vh - 200px); /* Use min-height instead of fixed height */
    gap: 1.5rem;
    padding: 1.5rem;
    padding-bottom: 2rem; /* Extra padding at bottom */
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

/* Ensure screenshot modal appears above CCTV modal */
#screenshotModal.modal {
    z-index: 9999 !important;
}

#screenshotModal.modal .modal-backdrop {
    z-index: 9998 !important;
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

.history-viewer{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,340px);grid-template-rows:minmax(0,1fr) auto;height:calc(100vh - 75px);min-height:0}
.history-stage{background:#111;display:flex;justify-content:center;align-items:center;min-width:0;min-height:0;overflow:auto;padding:12px}
.history-stage img{max-width:100%;max-height:100%;object-fit:contain}
.history-transcript{border-left:1px solid var(--bs-border-color);padding:16px;overflow:auto;min-height:0}
.history-transcript.is-collapsed{display:none}
.history-viewer:has(.history-transcript.is-collapsed){grid-template-columns:minmax(0,1fr)}
.history-bottom{grid-column:1/-1;border-top:1px solid var(--bs-border-color);padding:12px 16px;min-width:0;background:var(--bs-body-bg)}
.history-range-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
.history-range-controls label{font-size:.8rem;flex:1;min-width:180px}
.history-strip{display:flex;gap:6px;overflow-x:auto;min-height:64px;padding:4px 0;scrollbar-width:thin}
.history-range-track{height:30px;position:relative;background:var(--bs-tertiary-bg);border-radius:8px;display:flex;align-items:center;gap:10px;padding:0 12px}
.history-range-track label{flex:1;font-size:.7rem;margin:0}
.history-range-track input{width:100%;accent-color:var(--bs-primary);display:block}
.history-thumb{position:relative;flex:none;border:2px solid transparent;background:var(--bs-tertiary-bg);border-radius:5px;padding:3px;cursor:pointer;font-size:.72rem;min-width:110px}
.history-thumb.selected{border-color:var(--bs-primary)}
.history-thumb img{height:64px;width:112px;object-fit:contain;display:block;background:#171717}
.history-thumb time{display:block;text-align:center}
.history-keyline{display:grid;grid-template-columns:72px minmax(0,1fr);gap:6px;border-bottom:1px solid var(--bs-border-color);padding:6px 0}
.history-keyline span:last-child{white-space:pre-wrap;overflow-wrap:anywhere;font-family:ui-monospace,monospace}
@media(max-width:800px){.history-viewer{grid-template-columns:minmax(0,1fr);grid-template-rows:minmax(180px,1fr) minmax(120px,30vh) auto}.history-transcript{border-left:0;border-top:1px solid var(--bs-border-color)}}
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

/* Log button styles removed - screenshots only */

/* Responsive Design */
@media (max-width: 1400px) {
    .dashboard-container {
        gap: 1rem;
        padding: 1rem;
        padding-bottom: 2rem; /* Extra padding at bottom */
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
        padding-bottom: 2rem; /* Extra padding at bottom */
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

/* Radio Button Styles */
.monitor-radio {
    width: 1.25rem;
    height: 1.25rem;
    margin-right: 0.75rem;
    border-radius: 50%;
    border: 2px solid var(--monitoring-divider);
    transition: all 0.2s ease;
}

.monitor-radio:checked {
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

/* Full Screen CCTV View Styles */
.fullscreen-cctv-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
    padding: 1rem;
    background: #1a1a1a;
    min-height: 100vh;
    overflow-y: auto;
}

/* Full Screen Control Buttons */
.btn-close-fullscreen {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.8);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-close-fullscreen:hover {
    background: rgba(244, 67, 54, 0.9);
    border-color: rgba(244, 67, 54, 0.8);
    transform: scale(1.1);
    color: white;
}

.btn-refresh-fullscreen {
    position: fixed;
    top: 20px;
    right: 80px;
    z-index: 1000;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.8);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-refresh-fullscreen:hover {
    background: rgba(25, 118, 210, 0.9);
    border-color: rgba(25, 118, 210, 0.8);
    transform: scale(1.1);
    color: white;
}

.fullscreen-count {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
    user-select: none;
}

.cctv-screenshot-card {
    background: #2a2a2a;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #333;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    aspect-ratio: 16/10;
}

.cctv-screenshot-card:hover {
    border-color: var(--monitoring-primary);
    transform: scale(1.02);
    box-shadow: 0 8px 25px rgba(25, 118, 210, 0.3);
    z-index: 10;
}

.cctv-screenshot-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.cctv-screenshot-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    color: white;
    padding: 1rem 0.5rem 0.5rem;
    opacity: 1;
    pointer-events: none;
}

.cctv-user-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.cctv-username {
    font-weight: 600;
    color: #4fc3f7;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
    font-size: 0.75rem;
}

.cctv-ip {
    color: #81c784;
    font-family: monospace;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

.cctv-status-indicator {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 1px solid white;
}

.cctv-status-online {
    background: var(--monitoring-success);
    box-shadow: 0 0 8px var(--monitoring-success);
}

.cctv-status-inactive {
    background: var(--monitoring-warning);
    box-shadow: 0 0 8px var(--monitoring-warning);
}

.cctv-status-offline {
    background: var(--monitoring-error);
    box-shadow: 0 0 8px var(--monitoring-error);
}

/* Responsive CCTV Grid */
@media (max-width: 1920px) {
    .fullscreen-cctv-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

@media (max-width: 1600px) {
    .fullscreen-cctv-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1200px) {
    .fullscreen-cctv-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .fullscreen-cctv-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        padding: 0.5rem;
    }
}

@media (max-width: 480px) {
    .fullscreen-cctv-grid {
        grid-template-columns: 1fr;
    }
}
/* Compact responsive workstation layout */
.dashboard-container{gap:12px;padding:12px;min-height:calc(100dvh - 130px);background:var(--bs-body-bg,#f8fafc)}
.sidebar-panel{width:clamp(235px,24vw,300px);min-width:220px;max-width:320px;border-radius:10px}
.main-content{border-radius:10px}
.status-overview{padding:14px 16px;border-radius:10px 10px 0 0}
.status-overview h5{font-size:1.1rem;margin-bottom:3px}
.status-overview p{font-size:.8rem}
.status-stats{gap:8px;margin-top:10px}
.stat-card{padding:10px 8px;border-radius:8px}
.stat-number{font-size:1.45rem}
.stat-label{font-size:.73rem}
.sidebar-panel .sidebar-header{padding:12px}
.sidebar-panel .sidebar-header h6{font-size:1rem;margin-bottom:8px}
.sidebar-panel .filter-container{margin-top:6px}
.sidebar-panel .filter-container .form-control{padding:8px 10px;border-width:1px;border-radius:8px}
.sidebar-panel .monitor-item{padding:9px 12px}
.sidebar-panel .monitor-item:hover{transform:none}
@media(max-width:900px){.dashboard-container{gap:8px;padding:8px}.sidebar-panel{width:220px;min-width:195px}.status-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.dashboard-container{flex-direction:column;min-height:0}.sidebar-panel{width:100%;min-width:0;max-width:none;max-height:35dvh}.main-content{min-height:55dvh}.status-overview{padding:12px}}
</style>

<!-- Modern Dashboard Container -->
<div class="dashboard-container">
    <!-- Left Sidebar - Monitor List -->
    <div class="sidebar-panel">
        <div class="sidebar-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllMonitors">
                        <i class="fas fa-globe me-1"></i>
                        Show All Devices
                    </button>
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
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 px-3 py-3 border-bottom" id="liveSummary">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge text-bg-light border"><i class="fas fa-desktop me-1"></i><span id="total-monitors">0</span> Devices</span>
                <span class="badge text-bg-success"><span id="online-monitors">0</span> Online</span>
                <span class="badge text-bg-warning"><span id="inactive-monitors">0</span> Idle</span>
                <span class="badge text-bg-secondary"><span id="offline-monitors">0</span> Offline</span>
                <span class="small text-muted" id="lastLiveRefresh" aria-live="polite">Not updated</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>view/logs" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list me-1"></i>Activity Logs</a>
                <button type="button" class="btn btn-sm btn-outline-primary" id="refreshLiveNow"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
            </div>
        </div>

        <!-- Modern Screenshots Section -->
        <div class="main-header">
            <h6>
                <i class="fas fa-image me-2"></i>
                Screenshots
                <span class="badge bg-secondary ms-2" id="screenshotCount">0</span>
                <small class="text-muted ms-2" id="timezoneIndicator">
                    <i class="fas fa-clock me-1"></i>
                    <span id="userTimezone">Loading...</span>
                </small>
            </h6>
            <div class="d-flex align-items-center gap-3">
                <div id="headerDateFilterContainer"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0">Per page:</label>
                    <select class="form-select" id="perPageSelect" style="width: auto;">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50" selected>50</option>
                    </select>
                </div>
                <button class="btn btn-outline-primary" id="fullscreenBtn" title="Full Screen View">
                    <i class="fas fa-expand"></i>
                    Full Screen
                </button>
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
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="prevScreenshotBtn" title="Previous Screenshot" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="screenshotCounter" class="text-muted small">1 / 1</span>
                        <button type="button" class="btn btn-outline-secondary" id="nextScreenshotBtn" title="Next Screenshot" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="history-viewer">
                    <section class="history-stage" aria-label="Selected screenshot">
                        <img id="modalImage" src="" alt="Selected screenshot" class="img-fluid">
                    </section>
                    <aside class="history-transcript" aria-label="Keyboard transcript">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <strong>Keyboard transcript</strong>
                            <button id="historyDrawerToggle" class="btn btn-sm btn-outline-secondary" type="button" aria-expanded="true">Hide</button>
                        </div>
                        <div class="d-flex gap-1 mt-2 align-items-center justify-content-between">
                            <button id="historyPrevRange" class="btn btn-sm btn-outline-secondary" type="button">‹ Previous</button>
                            <span id="historyTranscriptRange" class="small text-muted"></span>
                            <button id="historyNextRange" class="btn btn-sm btn-outline-secondary" type="button">Next ›</button>
                        </div>
                        <div id="historyTranscriptBody" class="mt-3 small" aria-live="polite">Select a time range.</div>
                    </aside>
                    <section class="history-bottom" aria-label="Screenshot history timeline">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <strong>History timeline</strong>
                            <label class="small">Scale
                                <select id="historyScale" class="form-select form-select-sm d-inline-block w-auto">
                                    <option value="5">5 minutes</option><option value="15" selected>15 minutes</option>
                                    <option value="60">1 hour</option><option value="360">6 hours</option><option value="1440">24 hours</option>
                                </select>
                            </label>
                        </div>
                        <div class="history-range-controls">
                            <label>From <input id="historyFrom" class="form-control form-control-sm" type="datetime-local" step="1"></label>
                            <label>To <input id="historyTo" class="form-control form-control-sm" type="datetime-local" step="1"></label>
                            <button id="historyApplyRange" class="btn btn-sm btn-primary" type="button">Apply range</button>
                        </div>
                        <div class="history-range-track mt-2" aria-label="Drag the time-range handles"><label>Start<input id="historyStartHandle" aria-label="Range start" type="range" min="0" max="1000" value="250"></label><label>End<input id="historyEndHandle" aria-label="Range end" type="range" min="0" max="1000" value="750"></label></div>
                        <div class="history-strip mt-2" id="historyStrip" aria-label="Click a screenshot thumbnail to navigate"></div>
                        <div class="small text-muted mt-1" id="historyTimelineStatus"></div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full Screen CCTV View Modal -->
<div class="modal fade" id="fullscreenModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body p-0 position-relative">
                <!-- Exit Button -->
                <button type="button" class="btn btn-close-fullscreen" data-bs-dismiss="modal" aria-label="Close" title="Exit Full Screen">
                    <i class="fas fa-times"></i>
                </button>
                
                <!-- Refresh Button -->
                <button type="button" class="btn btn-refresh-fullscreen" id="refreshFullscreenBtn" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
                
                <!-- Screenshot Count -->
                <div class="fullscreen-count" id="fullscreenCount">0 screenshots</div>
                
                <div id="fullscreenContainer" class="fullscreen-cctv-grid">
                    <!-- CCTV grid will be populated here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log modals removed - screenshots only -->

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
let currentPerPage = 50;
let selectedMonitors = [];
let allMonitors = [];
let filteredMonitors = [];
let fullscreenShotMap = new Map();
let visibleShotMap = new Map();
function attachScreenshotImageFallback(scope) {
    scope.find('img[data-full-image]').on('error', function() {
        const full = this.getAttribute('data-full-image');
        if (!this.dataset.triedOriginal && full && this.src !== new URL(full, document.baseURI).href) {
            this.dataset.triedOriginal = '1';
            this.src = full;
            return;
        }
        // An explicit failure is more useful than a broken-image icon.
        this.style.display = 'none';
        const message = document.createElement('div');
        message.className = 'p-3 text-center text-muted small';
        message.textContent = 'Screenshot unavailable';
        this.parentNode.insertBefore(message, this.nextSibling);
    });
}
function escapeViewerText(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[char]);
}
// Initialize when jQuery is ready
waitForJQuery(function() {
    $(document).on('click','.screenshot-card[data-shot-id],.cctv-screenshot-card[data-shot-id]',function(){
        const id=$(this).attr('data-shot-id');
        const shot=($(this).hasClass('cctv-screenshot-card')?fullscreenShotMap:visibleShotMap).get(id);
        if(!shot)return;
        viewScreenshot(shot.url,shot.user,shot.ip,id,shot.time);
    });
    // Detect and display user's timezone
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    $('#userTimezone').text(userTimezone);
    
    // Load initial data
    loadMonitors();
    loadScreenshots();
    $('#refreshLiveNow').on('click',function(){
        loadMonitors();
        if(selectedMonitors.length===1)loadScreenshotsForMonitor(selectedMonitors[0],1);
        else loadScreenshots(1);
    });
    
    
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
    
    // Setup full-screen button handler
    $('#fullscreenBtn').on('click', function() {
        openFullscreenView();
    });
    
    // Setup refresh button for fullscreen
    $('#refreshFullscreenBtn').on('click', function() {
        refreshFullscreenView();
    });
    
    // Full Screen CCTV View Functions
    let fullscreenRefreshInterval;
    
    function openFullscreenView() {
        // Show loading state
        $('#fullscreenContainer').html(`
            <div class="d-flex justify-content-center align-items-center" style="height: 400px; grid-column: 1 / -1;">
                <div class="text-center text-white">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <div>Loading CCTV View...</div>
                </div>
            </div>
        `);
        
        $('#fullscreenModal').modal('show');
        
        // Load screenshots for fullscreen view
        loadFullscreenScreenshots();
        
        // Start auto-refresh every 30 seconds
        startFullscreenAutoRefresh();
    }

    function loadFullscreenScreenshots() {
        const params = new URLSearchParams({
            page: 1,
            per_page: 50, // Backend caps pagination at 50 records
            format: 'fullscreen'
        });
        
        // Add device filter if single monitor is selected
        if (selectedMonitors.length === 1) {
            params.append('monitor_id', selectedMonitors[0]);
        }
        
        // Show loading indicator on refresh button
        const refreshBtn = $('#refreshFullscreenBtn');
        const originalIcon = refreshBtn.html();
        refreshBtn.html('<i class="fas fa-spinner fa-spin"></i>');
        
        fetch(`<?php echo BASE_URL; ?>view/api_screenshots?${params}`)
            .then(response => response.json())
            .then(data => {
                console.log('Fullscreen API response:', data); // Debug log
                if (data.screenshots && data.screenshots.length > 0) {
                    displayFullscreenScreenshots(data.screenshots);
                    const now = new Date();
                    const timeStr = now.toLocaleTimeString('en-US', { 
                        hour12: false, 
                        hour: '2-digit', 
                        minute: '2-digit', 
                        second: '2-digit' 
                    });
                    $('#fullscreenCount').text(`${data.screenshots.length} screenshots • Updated ${timeStr}`);
                } else {
                    showFullscreenError('No screenshots available');
                }
            })
            .catch(error => {
                console.error('Error loading fullscreen screenshots:', error);
                showFullscreenError('Error loading screenshots');
            })
            .finally(() => {
                // Restore refresh button icon
                refreshBtn.html(originalIcon);
            });
    }

    function displayFullscreenScreenshots(screenshots) {
        if (screenshots.length === 0) {
            showFullscreenError('No screenshots available');
            return;
        }
        
        let html = '';
        fullscreenShotMap = new Map();
        screenshots.forEach(function(item) {
            const monitor = item.monitor || {};
            const thumbnailUrl = "<?php echo BASE_URL; ?>" + (item.thumbnail||item.screenshot||'');
            const fullUrl = "<?php echo BASE_URL; ?>" + (item.screenshot||'');
            const id = String(item.global_id||'');
            fullscreenShotMap.set(id,{url:fullUrl,user:monitor.username||'Unknown User',
                                      ip:monitor.ip_address||'—',time:Number(item.modified_time)||0});
            const live = allMonitors.find(m=>m.id===Number(monitor.id||monitor.monitor_id));
            const state = live?.live_status || 'offline';
            const statusClass = 'cctv-status-' + (['online','inactive','offline'].includes(state)?state:'offline');
            const statusText = live?.status_text || 'Offline';
            
            html += `
                <div class="cctv-screenshot-card" data-shot-id="${escapeViewerText(id)}"
                     title="View screenshot">
                    <div class="cctv-status-indicator ${statusClass}" title="Status: ${escapeViewerText(statusText)}"></div>
                    <img src="${escapeViewerText(thumbnailUrl)}" data-full-image="${escapeViewerText(fullUrl)}" alt="Screenshot" loading="lazy">
                    <div class="cctv-screenshot-info">
                        <div class="cctv-user-info">
                            <div class="cctv-username">
                                <i class="fas fa-user"></i>
                                ${escapeViewerText(monitor.username || 'Unknown User')}
                            </div>
                            <div class="cctv-ip">
                                <i class="fas fa-desktop"></i>
                                ${escapeViewerText(monitor.ip_address||'—')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#fullscreenContainer').html(html);
        attachScreenshotImageFallback($('#fullscreenContainer'));
    }

    function showFullscreenError(message) {
        $('#fullscreenContainer').html(`
            <div class="d-flex justify-content-center align-items-center" style="height: 400px; grid-column: 1 / -1;">
                <div class="text-center text-white">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <div>${message}</div>
                </div>
            </div>
        `);
        $('#fullscreenCount').text('0 screenshots');
    }

    function refreshFullscreenView() {
        loadFullscreenScreenshots();
    }
    
    function startFullscreenAutoRefresh() {
        // Clear any existing interval
        if (fullscreenRefreshInterval) {
            clearInterval(fullscreenRefreshInterval);
        }
        
        // Set up auto-refresh every 30 seconds
        fullscreenRefreshInterval = setInterval(function() {
            if ($('#fullscreenModal').hasClass('show')) {
                loadFullscreenScreenshots();
            }
        }, 30000); // 30 seconds
    }
    
    function stopFullscreenAutoRefresh() {
        if (fullscreenRefreshInterval) {
            clearInterval(fullscreenRefreshInterval);
            fullscreenRefreshInterval = null;
        }
    }

    function getMonitorStatusClass(status) {
        switch(status) {
            case 'online': return 'cctv-status-online';
            case 'inactive': return 'cctv-status-inactive';
            case 'offline': return 'cctv-status-offline';
            default: return 'cctv-status-offline';
        }
    }

    function getMonitorStatusText(status) {
        switch(status) {
            case 'online': return 'Online';
            case 'inactive': return 'Inactive';
            case 'offline': return 'Offline';
            default: return 'Unknown';
        }
    }

    // Make fullscreen functions globally available
    window.openFullscreenView = openFullscreenView;
    window.refreshFullscreenView = refreshFullscreenView;
    
    // Handle window resize to ensure proper layout
    $(window).on('resize', function() {
        // Force layout recalculation
        $('.dashboard-container').css('width', '100%');
    });
    
    // Clean up keyboard events when modal is closed
    $('#screenshotModal').on('hidden.bs.modal', function() {
        $(document).off('keydown.screenshotNavigation');
        window.currentScreenshotId = null;
        historySeq++;
        historyKeySeq++;
        // Reset z-index when modal is closed
        $('#screenshotModal').css('z-index', '');
    });
    
    // Stop auto-refresh when fullscreen modal is closed
    $('#fullscreenModal').on('hidden.bs.modal', function() {
        stopFullscreenAutoRefresh();
    });
    
    // Make functions global so they can be called from onclick attributes
    window.getMonitorTypeIcon = getMonitorTypeIcon;
    window.loadScreenshotsForSelectedMonitor = loadScreenshotsForSelectedMonitor;
    window.viewScreenshot = viewScreenshot;
    window.setupNavigationButtons = setupNavigationButtons;
    window.navigateScreenshot = navigateScreenshot;
    window.initializeNavigationState = initializeNavigationState;
    
});


function getMonitorTypeIcon(type) {
    // Handle undefined, null, or empty type
    if (!type || typeof type !== 'string') {
        return 'desktop'; // Default fallback
    }
    
    switch(type.toLowerCase()) {
        case 'laptop':
            return 'laptop';
        case 'desktop':
            return 'desktop';
        case 'server':
            return 'server';
        case 'user':
            return 'desktop'; // Default for 'user' type
        default:
            return 'desktop'; // Default fallback
    }
}

function setupEventHandlers() {
    // Show all devices button
    $('#selectAllMonitors').click(function() {
        // Clear all selections to show all devices
        selectedMonitors = [];
        $('.monitor-radio').prop('checked', false);
        $('.monitor-item').removeClass('selected');
        
        // Update UI
        updateSelectAllCheckbox();
        
        loadScreenshots();
    });
    
    // Individual monitor selection (radio button mode)
    $(document).on('change', '.monitor-radio', function() {
        const monitorId = parseInt($(this).val());
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            // Radio button selection - only one monitor can be selected
            selectedMonitors = [monitorId];
            
            // Remove selected class from all items and add to current
            $('.monitor-item').removeClass('selected');
            $(this).closest('.monitor-item').addClass('selected');
        } else {
            // If unchecked, clear selection (show all devices)
            selectedMonitors = [];
            $('.monitor-item').removeClass('selected');
        }
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        loadScreenshots();
    });
    
    // Click anywhere on monitor item to select radio button
    $(document).on('click', '.monitor-item', function(e) {
        // Don't trigger if clicking directly on the radio button
        if ($(e.target).is('input[type="radio"]') || $(e.target).is('label')) {
            return;
        }
        
        const radio = $(this).find('.monitor-radio');
        const monitorId = parseInt(radio.val());
        
        // Clear all selections first
        $('.monitor-radio').prop('checked', false);
        $('.monitor-item').removeClass('selected');
        selectedMonitors = [];
        
        // Select only this monitor
        radio.prop('checked', true);
        $(this).addClass('selected');
        selectedMonitors = [monitorId];
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        // Load screenshots for this specific monitor
        loadScreenshotsForMonitor(monitorId);
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
            const ip = String(monitor.last_seen_ip || monitor.ip_address || '').toLowerCase();
            
            return username.includes(filterText) ||
                   type.includes(filterText) ||
                   ip.includes(filterText);
        });
    }
    
    updateMonitorList(filteredMonitors);
    updateSelectAllCheckbox();
}

function updateSelectAllCheckbox() {
    const selectedCount = selectedMonitors.length;
    
    if (selectedCount === 0) {
        $('#selectAllMonitors').removeClass('btn-primary').addClass('btn-outline-primary');
        $('#selectAllMonitors').html('<i class="fas fa-globe me-1"></i>Show All Devices');
    } else {
        $('#selectAllMonitors').removeClass('btn-outline-primary').addClass('btn-primary');
        $('#selectAllMonitors').html('<i class="fas fa-check me-1"></i>All Devices Selected');
    }
}

function loadMonitors() {
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_live_status",
        method: "GET",
        success: function(data) {
            allMonitors = Array.isArray(data) ? data.map(m=>({...m,id:Number(m.id)})) : [];
            
            // Preserve current selection when refreshing
            if (selectedMonitors.length > 0) {
                // Keep only monitors that still exist
                selectedMonitors = selectedMonitors.filter(id => 
                    allMonitors.some(monitor => monitor.id === id)
                );
            }
            
            filteredMonitors = [...allMonitors];
            updateMonitorList(filteredMonitors);
            updateStatusStats(allMonitors);
            $('#lastLiveRefresh').text('Updated '+new Date().toLocaleTimeString());
        },
        error: function(xhr, status, error) {
            console.error('Failed to load monitors:', xhr.status, error);
            if (!allMonitors.length) {
                $('#monitorList').html('<div class="p-3 text-danger">Unable to load devices. Please refresh the page.</div>');
            }
            $('#lastLiveRefresh').text('Status unavailable');
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
    const ordered=[...monitors].sort((a,b)=>String(a.name||a.username||'')
        .localeCompare(String(b.name||b.username||''),undefined,{sensitivity:'base'}));
    if(!ordered.length){
        $('#monitorList').html('<div class="p-4 text-center text-muted">No monitors found</div>');
        return;
    }
    const html=ordered.map(monitor=>{
        const chosen=selectedMonitors.includes(Number(monitor.id));
        const status=['online','inactive','offline'].includes(monitor.live_status)
            ?monitor.live_status:'offline';
        const statusText=monitor.status_text||'Offline';
        const label=escapeViewerText(monitor.username||'Unassigned');
        const number=escapeViewerText(monitor.user_no||'—');
        const ip=escapeViewerText(monitor.last_seen_ip||monitor.ip_address||'—');
        const platform=escapeViewerText(monitor.platform||'—');
        const machine=escapeViewerText(monitor.type||'—');
        return `
            <div class="monitor-item ${chosen?'selected':''}" data-monitor-id="${Number(monitor.id)}">
                <div class="monitor-item-header">
                    <input type="radio" class="monitor-radio" name="monitor_selection"
                           value="${Number(monitor.id)}" ${chosen?'checked':''}>
                    <div class="monitor-item-name" title="${label}">${label}</div>
                    <div class="monitor-item-status">
                        <span class="live-status-badge status-${status}">
                            <i class="fas fa-circle"></i><span>${escapeViewerText(statusText)}</span>
                            ${status==='online'?'<div class="pulse"></div>':''}
                        </span>
                    </div>
                </div>
                <div class="monitor-item-username" title="${label}">
                    <i class="fas fa-desktop me-1"></i>Device #${Number(monitor.id)} · User No ${number}
                </div>
                <div class="monitor-item-username">
                    <i class="fas fa-desktop me-1"></i>${platform} · ${machine} · ${ip}
                </div>
            </div>`;
    }).join('');
    $('#monitorList').html(html);
    updateSelectAllCheckbox();
}

function updateStatusStats(monitors) {
    const total = monitors.length;
    const online = monitors.filter(m => m.live_status === 'online').length;
    const inactive = monitors.filter(m => m.live_status === 'inactive').length;
    const offline = monitors.filter(m => m.live_status === 'offline').length;
    
    $('#total-monitors').text(total);
    $('#online-monitors').text(online);
    $('#inactive-monitors').text(inactive);
    $('#offline-monitors').text(offline);
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
    visibleShotMap = new Map();
    screenshots.forEach(function(item) {
        const monitor = item.monitor || {};
        const thumbnailUrl = "<?php echo BASE_URL; ?>" + (item.thumbnail||item.screenshot||'');
        const fullUrl = "<?php echo BASE_URL; ?>" + (item.screenshot||'');
        const shotId = String(item.global_id||'');
        visibleShotMap.set(shotId,{url:fullUrl,user:monitor.username||'Unknown User',
                                   ip:monitor.ip_address||'—',time:Number(item.modified_time)||0});
        
        let timestamp;
        if (item.modified_time) {
            // Convert UTC timestamp to user's local timezone
            timestamp = new Date(item.modified_time * 1000).toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
        } else {
            timestamp = new Date().toLocaleString();
        }
        
        // Calculate time ago
        const screenshotTime = new Date(item.modified_time * 1000);
        const timeDiff = Date.now() - screenshotTime.getTime();
        const timeDiffSeconds = Math.floor(timeDiff / 1000);
        let timeAgo = '';

        if (timeDiffSeconds <= 0) { // exactly 0 seconds or negative (future time)
            timeAgo = 'Just now';
        } else {
            timeAgo = formatTimeAgo(timeDiffSeconds);
        }
        
        // Get user's timezone for display
        const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        
        if (isSingleMonitor) {
            // Single monitor view - show enhanced time ago and timestamp
            html += `
                <div class="screenshot-card" data-shot-id="${escapeViewerText(shotId)}">
                    <img src="${escapeViewerText(thumbnailUrl)}" data-full-image="${escapeViewerText(fullUrl)}" alt="Screenshot" loading="lazy">
                    <div class="screenshot-info">
                        <div class="screenshot-timestamp">
                            <i class="fas fa-${getMonitorTypeIcon(monitor.type)} me-1"></i>${monitor.type || 'Desktop'}
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
                ? `<span class="badge bg-primary"><i class="fas fa-user me-1"></i> ${escapeViewerText(monitor.username)}</span>` 
                : '';
            
            html += `
                <div class="screenshot-card" data-shot-id="${escapeViewerText(shotId)}">
                    <img src="${escapeViewerText(thumbnailUrl)}" data-full-image="${escapeViewerText(fullUrl)}" alt="Screenshot" loading="lazy">
                    <div class="screenshot-info">
                        <div class="screenshot-badges">
                            ${usernameHtml}
                            <span class="badge bg-info"><i class="fas fa-${getMonitorTypeIcon(monitor.type)} me-1"></i>${escapeViewerText(monitor.ip_address||'—')}</span>
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
    attachScreenshotImageFallback(container);
}

function updateHeaderButtons(isSingleMonitor) {
    const filter=$('#headerDateFilterContainer');
    if(!isSingleMonitor){
        filter.empty();
        window.headerStartDate='';
        window.headerEndDate='';
        return;
    }
    if(filter.find('#headerDateFilterForm').length)return;
    filter.html(`
        <form id="headerDateFilterForm" class="d-flex flex-wrap align-items-center gap-2 mb-0">
            <label class="small mb-0" for="headerStartDate">From</label>
            <input type="date" id="headerStartDate" class="form-control form-control-sm" style="width:145px">
            <label class="small mb-0" for="headerEndDate">To</label>
            <input type="date" id="headerEndDate" class="form-control form-control-sm" style="width:145px">
            <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            <button type="button" id="headerClearDateFilter" class="btn btn-sm btn-outline-secondary">Clear</button>
        </form>`);
    filter.off('submit','#headerDateFilterForm').on('submit','#headerDateFilterForm',function(e){
        e.preventDefault();
        window.headerStartDate=$('#headerStartDate').val();
        window.headerEndDate=$('#headerEndDate').val();
        loadScreenshotsForSelectedMonitor(1);
    });
    filter.off('click','#headerClearDateFilter').on('click','#headerClearDateFilter',function(){
        window.headerStartDate='';
        window.headerEndDate='';
        $('#headerStartDate,#headerEndDate').val('');
        loadScreenshotsForSelectedMonitor(1);
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
    if(selectedMonitors.length===1)loadScreenshotsForMonitor(selectedMonitors[0],page);
    else loadScreenshots(page);
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

function viewScreenshot(imageUrl, monitorName, ipAddress, screenshotId = null, timestamp = 0) {
    $("#modalImage").attr("src", imageUrl);
    $("#modalTitle").text(`Screenshot - ${monitorName} (${ipAddress})`);
    
    // Store current screenshot ID for navigation
    window.currentScreenshotId = screenshotId;
    startHistoryViewer(screenshotId, timestamp);
    
    // Check if CCTV modal is open and set higher z-index
    if ($('#fullscreenModal').hasClass('show')) {
        $('#screenshotModal').css('z-index', '9999');
    } else {
        $('#screenshotModal').css('z-index', '');
    }
    
    $("#screenshotModal").modal("show");
    
    // Setup navigation buttons
    setupNavigationButtons();
    
    // Initialize navigation state
    if (screenshotId) {
        initializeNavigationState();
    }
}

// Log button handlers removed - screenshots only

// Log loading functions removed - screenshots only

// Log-related variables removed - screenshots only

// Log display functions removed - screenshots only

// Log table display functions removed - screenshots only

// Log pagination functions removed - screenshots only

// Remaining log display functions removed - screenshots only

// All log parsing functions removed - screenshots only

// All remaining log functions removed - screenshots only

// Navigation functionality for screenshot modal
function setupNavigationButtons() {
    // Previous button
    $('#prevScreenshotBtn').off('click').on('click', function() {
        if (window.currentScreenshotId) {
            navigateScreenshot('prev');
        }
    });
    
    // Next button
    $('#nextScreenshotBtn').off('click').on('click', function() {
        if (window.currentScreenshotId) {
            navigateScreenshot('next');
        }
    });
    
    // Keyboard navigation
    $(document).off('keydown.screenshotNavigation').on('keydown.screenshotNavigation', function(e) {
        if ($('#screenshotModal').hasClass('show')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                $('#prevScreenshotBtn').click();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                $('#nextScreenshotBtn').click();
            }
        }
    });
}

function initializeNavigationState() {
    if (!window.currentScreenshotId) return;
    
    // Get the monitor ID from the screenshot ID
    const monitorId = window.currentScreenshotId.split('_')[1];
    
    // Get total screenshots for this monitor
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?monitor_id=" + monitorId + "&per_page=1",
        method: "GET",
        success: function(data) {
            if (data.pagination) {
                const totalScreenshots = data.pagination.total_screenshots;
                $('#screenshotCounter').text(`1 / ${totalScreenshots}`);
                
                // Enable/disable navigation buttons based on total count
                $('#prevScreenshotBtn').prop('disabled', totalScreenshots <= 1);
                $('#nextScreenshotBtn').prop('disabled', totalScreenshots <= 1);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to get total screenshots:", error);
            $('#screenshotCounter').text('1 / ?');
        }
    });
}

function navigateScreenshot(direction) {
    if (!window.currentScreenshotId) return;
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshot_navigation?cur_id=" + window.currentScreenshotId + "&action=" + direction,
        method: "GET",
        success: function(response) {
            if (response.success) {
                const screenshot = response.screenshot;
                
                // Update modal content
                $("#modalImage").attr("src", "<?php echo BASE_URL; ?>" + (screenshot.screenshot || ""));
                $("#modalTitle").text(`Screenshot - ${screenshot.monitor.username} (${screenshot.monitor.ip_address})`);
                
                // Update current screenshot ID
                window.currentScreenshotId = screenshot.global_id;
                startHistoryViewer(screenshot.global_id, Number(new Date(screenshot.capture_datetime+"Z"))/1000);
                
                // Update navigation buttons
                $('#prevScreenshotBtn').prop('disabled', !response.has_prev);
                $('#nextScreenshotBtn').prop('disabled', !response.has_next);
                
                // Update counter
                $('#screenshotCounter').text(`${response.current_position} / ${response.total_screenshots}`);
            } else {
                console.log('Navigation failed:', response.error);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to navigate screenshot:", error);
        }
    });
}


const historyBase = <?php echo json_encode(BASE_URL); ?>;
let historySeq=0,historyKeySeq=0,historyMonitor=0,historyStart=0,historyEnd=0,historyActive=0;
let historyDomainStart=0,historyDomainEnd=0;
function historyUtc(ms){
    const d=new Date(ms);
    return d.toISOString().slice(0,19).replace('T',' ');
}
function historyInput(ms){
    const d=new Date(ms-new Date(ms).getTimezoneOffset()*60000);
    return d.toISOString().slice(0,19);
}
function historyFromInput(value){
    const ms=new Date(value).getTime();
    return Number.isFinite(ms)?ms:NaN;
}
function historyRange(start,end){
    if(!Number.isFinite(start)||!Number.isFinite(end)||end<=start||end-start>86400000){
        $('#historyTimelineStatus').text('Select a valid time range up to 24 hours.');return;
    }
    historyStart=start;historyEnd=end;
    if(!historyDomainStart||start<historyDomainStart||end>historyDomainEnd){
        const extent=Math.max(60*60000,end-start);
        historyDomainStart=start-extent;historyDomainEnd=end+extent;
    }
    $('#historyStartHandle').val(Math.round(1000*(start-historyDomainStart)/(historyDomainEnd-historyDomainStart)));
    $('#historyEndHandle').val(Math.round(1000*(end-historyDomainStart)/(historyDomainEnd-historyDomainStart)));
    $('#historyFrom').val(historyInput(start));$('#historyTo').val(historyInput(end));
    fetchHistoryShots();
    fetchHistoryKeys();
}
function startHistoryViewer(id,timestamp){
    const found=/^screenshot_(\d+)_/.exec(id||'');
    if(!found)return;
    historyMonitor=Number(found[1]);
    historyDomainStart=0;historyDomainEnd=0;
    historyActive=id;
    const center=timestamp>0?timestamp*1000:Date.now();
    const span=Number($('#historyScale').val()||15)*60000;
    historyRange(center-span/2,center+span/2);
}
async function fetchHistoryShots(){
    const seq=++historySeq;
    const params=new URLSearchParams({monitor_id:String(historyMonitor),from:historyUtc(historyStart),to:historyUtc(historyEnd)});
    $('#historyTimelineStatus').text('Loading screenshots…');
    try{
        const response=await fetch(historyBase+'view/api_screenshot_timeline?'+params,{credentials:'same-origin'});
        if(!response.ok)throw Error('HTTP '+response.status);
        const data=await response.json();
        if(!data.success)throw Error(data.error||'History unavailable');
        if(seq!==historySeq)return;
        const shots=data.screenshots||[];
        const strip=$('#historyStrip').empty();
        // Avoid dozens of tiny thumbnails at wide scales; server already samples to at most 120.
        const step=Math.max(1,Math.ceil(shots.length/Math.max(1,Math.floor(strip.width()/124))));
        shots.forEach((shot,i)=>{
            if(i%step!==0 && shot.global_id!==historyActive)return;
            const button=$('<button type="button" class="history-thumb"/>')
                .toggleClass('selected',shot.global_id===historyActive)
                .attr('title',new Date(shot.modified_time*1000).toLocaleString());
            const img=$('<img loading="lazy" alt="Screenshot thumbnail"/>')
                .attr('src',historyBase+(shot.thumbnail||shot.screenshot||''))
                .on('error',function(){if(!this.dataset.fallback){this.dataset.fallback='1';this.src=historyBase+(shot.screenshot||'');}});
            button.append(img,$('<time/>').text(new Date(shot.modified_time*1000).toLocaleTimeString()));
            button.on('click',()=>{
                historyActive=shot.global_id;
                window.currentScreenshotId=shot.global_id;
                $('#modalImage').attr('src',historyBase+shot.screenshot);
                $('#historyStrip .history-thumb').removeClass('selected');button.addClass('selected');
                const stamp=shot.modified_time*1000;
                // Move the inspection range to the chosen image without changing the current scale.
                const half=(historyEnd-historyStart)/2;
                historyStart=stamp-half;historyEnd=stamp+half;
                $('#historyFrom').val(historyInput(historyStart));$('#historyTo').val(historyInput(historyEnd));
                fetchHistoryKeys();
            });
            strip.append(button);
        });
        $('#historyTimelineStatus').text(shots.length+' sampled screenshot(s) in this range'+(shots.length===120?' (limit reached)':''));
    }catch(e){if(seq===historySeq)$('#historyTimelineStatus').text('Cannot load screenshots: '+e.message);}
}
async function fetchHistoryKeys(){
    const seq=++historyKeySeq;
    $('#historyTranscriptRange').text(new Date(historyStart).toLocaleTimeString()+' – '+new Date(historyEnd).toLocaleTimeString());
    $('#historyTranscriptBody').text('Loading keyboard activity…');
    const params=new URLSearchParams({device_id:String(historyMonitor),from:historyUtc(historyStart),to:historyUtc(historyEnd)});
    try{
        const response=await fetch(historyBase+'logbrowse/api_key_context?'+params,{credentials:'same-origin'});
        if(!response.ok)throw Error('HTTP '+response.status);
        const data=await response.json();
        if(!data.success)throw Error(data.error||'Keylog unavailable');
        if(seq!==historyKeySeq)return;
        const body=$('#historyTranscriptBody').empty();
        if(!data.logs?.length){body.text('No keyboard activity in the selected time range.');return;}
        let lastApp='';
        for(const log of data.logs){
            const app=String(log.application||'Unknown app');
            if(app!==lastApp){body.append($('<div class="mt-2 fw-semibold"/>').text(app));lastApp=app;}
            const row=$('<div class="history-keyline"/>');
            row.append($('<span class="text-muted"/>').text(String(log.key_date||'').slice(-8)));
            row.append($('<span/>').text(String(log.key_pressed||'')));
            body.append(row);
        }
        if(data.limited)body.append($('<div class="text-warning mt-2"/>').text('Showing first 2,000 events. Narrow the range for more.'));
    }catch(e){if(seq===historyKeySeq)$('#historyTranscriptBody').text('Cannot load keyboard activity: '+e.message);}
}
$(document).on('change','#historyStartHandle,#historyEndHandle',function(){
    let a=Number($('#historyStartHandle').val()),b=Number($('#historyEndHandle').val());
    if(a>=b){if(this.id==='historyStartHandle')a=Math.max(0,b-1);else b=Math.min(1000,a+1);}
    const width=historyDomainEnd-historyDomainStart;
    historyRange(historyDomainStart+a*width/1000,historyDomainStart+b*width/1000);
});
$(document).on('click','#historyApplyRange',()=>historyRange(historyFromInput($('#historyFrom').val()),historyFromInput($('#historyTo').val())));
$(document).on('change','#historyScale',()=>{
    if(!historyMonitor)return;
    const center=(historyStart+historyEnd)/2,half=Number($('#historyScale').val())*30000;
    historyRange(center-half,center+half);
});
$(document).on('click','#historyPrevRange,#historyNextRange',function(){
    const duration=historyEnd-historyStart,dir=this.id==='historyNextRange'?1:-1;
    historyRange(historyStart+dir*duration,historyEnd+dir*duration);
});
$(document).on('click','#historyDrawerToggle',function(){
    const drawer=$('.history-transcript').toggleClass('is-collapsed');
    const closed=drawer.hasClass('is-collapsed');
    $(this).text(closed?'Show':'Hide').attr('aria-expanded',String(!closed));
    if(closed){
        $(this).appendTo('.history-bottom .d-flex:first');
    }else{
        $(this).appendTo('.history-transcript > .d-flex:first');
    }
});

</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 