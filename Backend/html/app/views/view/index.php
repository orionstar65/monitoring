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



/* Compact monitoring workspace and CCTV-style screenshot inspector. */
.dashboard-container{padding:8px;gap:8px;min-height:calc(100vh - 130px)}
.sidebar-panel{width:300px;min-width:250px;max-width:330px;border-radius:9px}
.main-content{border-radius:9px}
.sidebar-panel .sidebar-header{padding:10px 12px}
.sidebar-panel .sidebar-header h6{margin-bottom:6px}
.sidebar-panel .filter-container{margin-top:5px}
.sidebar-panel .filter-container .form-control{padding:6px 10px;border-radius:6px}
.sidebar-panel .monitor-item{padding:9px 12px}
.sidebar-panel .monitor-item-header{margin-bottom:3px}
.main-header{padding:10px 12px}
.main-content-area{padding:10px}
.screenshot-grid{gap:10px}
.screenshot-card{border-radius:8px}
.screenshot-info{padding:7px 9px}
.screenshot-badges{gap:4px;margin-bottom:4px}
.screenshot-badges .badge{padding:3px 6px}
#liveSummary{padding:8px 12px!important;gap:8px!important}
#screenshotModal .modal-content{border-radius:0}
#screenshotModal .modal-header{padding:7px 12px;min-height:44px;border-radius:0}
#screenshotModal .modal-title{font-size:1rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
#screenshotModal .modal-actions .btn{padding:4px 9px;border-radius:5px}
.history-viewer{display:grid;grid-template-columns:minmax(0,1fr) minmax(290px,360px);grid-template-rows:minmax(0,1fr) auto;height:calc(100dvh - 45px);min-height:0}
.history-viewer.drawer-collapsed{grid-template-columns:minmax(0,1fr)}
.history-stage{background:#0c0f14;display:flex;align-items:center;justify-content:center;overflow:hidden;min-width:0;min-height:0;padding:3px;position:relative}
.history-stage img{max-width:100%;max-height:100%;object-fit:contain}
.history-stage .history-frame-time{position:absolute;left:9px;bottom:7px;background:rgba(0,0,0,.72);border-radius:4px;padding:3px 6px;color:#fff;font-size:.7rem;font-variant-numeric:tabular-nums}
.history-transcript{border-left:1px solid var(--bs-border-color);padding:9px 10px;overflow:auto;min-width:0;min-height:0}
.history-transcript.is-collapsed{display:none}
.history-transcript .key-transcript{grid-template-columns:64px 75px minmax(0,1fr);gap:3px 6px}
.history-transcript .key-transcript-time,.history-transcript .key-transcript-app{font-size:.68rem}
.history-transcript .key-transcript-content{font-size:.76rem}
.history-transcript .key-transcript-nav{gap:6px;margin-bottom:7px}
.history-transcript-info{font-size:.72rem;color:var(--bs-secondary-color);margin-bottom:5px}
.history-bottom{grid-column:1/-1;border-top:1px solid var(--bs-border-color);padding:5px 9px 7px;min-width:0;background:var(--bs-body-bg)}
.history-bottom .key-timeline-wrapper{padding:0}
.history-bottom .key-timeline-heading{margin:0 0 4px;gap:6px}
.history-bottom .key-timeline-heading small{font-size:.69rem}
.history-bottom .key-range-inputs{margin-top:4px;gap:7px}
.history-bottom .key-range-inputs input{padding:3px 5px}
.history-bottom .key-range-inputs label{font-size:.7rem}
.history-bottom .key-ruler-controls{gap:4px}
.history-bottom .key-ruler-controls select{max-width:104px;padding:3px 20px 3px 7px}
.history-bottom .key-ruler-selected{font-size:.68rem}
.history-bottom .key-timeline-scroller{border-radius:6px;overscroll-behavior-inline:contain}
.history-ruler.key-cctv-ruler{height:124px;user-select:none;-webkit-user-select:none;touch-action:none}
.history-ruler .key-cctv-baseline{top:51px}
.history-ruler .key-cctv-tick{top:27px;height:25px}
.history-ruler .key-cctv-tick.major{top:22px;height:30px}
.history-ruler .key-cctv-tick-label{top:-18px}
.history-ruler .key-cctv-activity{top:45px!important;height:7px!important}
.history-ruler .key-cctv-selection{top:7px;bottom:5px;pointer-events:none}
.history-ruler .key-cctv-selection::before,.history-ruler .key-cctv-selection::after{top:0;height:27px;width:8px}
.history-timeline-image{position:absolute;top:58px;width:97px;height:60px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;border:1px solid var(--bs-border-color);border-radius:4px;background:var(--bs-body-bg);cursor:pointer;z-index:3;padding:1px;box-sizing:border-box}
.history-timeline-image img{height:40px;width:100%;object-fit:contain;background:#0c0f14}
.history-timeline-image time{font-size:.62rem;white-space:nowrap;color:var(--bs-secondary-color)}
.history-timeline-image.is-active{border:2px solid var(--bs-danger,#e45858)}
.history-shot-marker{position:absolute;top:47px;width:5px;height:9px;transform:translateX(-50%);border-radius:4px;background:var(--bs-info,#2a9eca);border:0;padding:0;cursor:pointer;z-index:3}
.history-shot-marker.is-active{background:var(--bs-danger,#e45858)}
.history-ruler .key-cctv-playhead{height:116px;top:4px;z-index:6;cursor:ew-resize;pointer-events:auto;background:var(--bs-danger,#e45858);padding:0;border:0;outline-offset:3px;width:3px}
.history-ruler .key-cctv-playhead::before{top:0;border-top-color:var(--bs-danger,#e45858);pointer-events:none}
.history-ruler .key-cctv-playhead::after{content:"";position:absolute;inset:0 -9px;z-index:-1}
.history-ruler.is-dragging{cursor:grabbing!important}
.history-transport{display:flex;align-items:center;flex-wrap:wrap;gap:4px}
.history-transport .btn{padding:2px 8px;line-height:1.45}
.history-playhead-label{font-size:.76rem;min-width:70px;font-variant-numeric:tabular-nums;color:var(--bs-danger,#da4545)}
.history-ruler .history-track-legend{position:absolute;top:55px;left:9px;font-size:.65rem;color:var(--bs-secondary-color);pointer-events:none}
@media(max-width:850px){
 .dashboard-container{padding:5px;gap:6px}
 .sidebar-panel{width:250px;min-width:200px}
 .history-viewer{grid-template-columns:minmax(0,1fr);grid-template-rows:minmax(160px,1fr) minmax(100px,28vh) auto}
 .history-transcript{border-left:0;border-top:1px solid var(--bs-border-color)}
 .history-viewer.drawer-collapsed{grid-template-rows:minmax(160px,1fr) auto}
}
@media(max-width:600px){.dashboard-container{flex-direction:column}.sidebar-panel{width:100%;max-width:none;max-height:38vh}.history-bottom .key-ruler-selected{display:none}}
.monitoring-filter-bar{display:flex;flex-wrap:wrap;align-items:end;gap:6px;padding:7px 10px;border-bottom:1px solid var(--bs-border-color)}
.monitoring-filter-bar label{font-size:.7rem;min-width:125px;margin:0}.monitoring-filter-bar input{font-size:.78rem;padding:3px 5px}
.monitoring-statistics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;padding:6px 10px;border-bottom:1px solid var(--bs-border-color)}
.monitoring-statistics>div{border:1px solid var(--bs-border-color);border-radius:5px;padding:4px 8px;min-width:0;display:flex;gap:7px;align-items:baseline}
.monitoring-statistics span{font-weight:700;font-size:1.15rem;font-variant-numeric:tabular-nums}.monitoring-statistics small{font-size:.7rem;color:var(--bs-secondary-color)}
.monitoring-tabs{display:flex;overflow-x:auto;padding:0 8px;border-bottom:1px solid var(--bs-border-color);gap:3px}
.monitoring-tab{border:0;border-bottom:2px solid transparent;background:transparent;padding:8px 13px;font-size:.84rem;color:var(--bs-secondary-color)}
.monitoring-tab.active{color:var(--bs-primary);border-color:var(--bs-primary);font-weight:650}
#unifiedScreenshotPanel,#unifiedLogPanel,#unifiedSettingsPanel{min-height:0;flex:1;overflow:auto}
#unifiedScreenshotPanel[hidden],#unifiedLogPanel[hidden],#unifiedSettingsPanel[hidden]{display:none!important}
.sidebar-panel .sidebar-content{overflow-y:auto;min-height:0;flex:1;scrollbar-width:thin;overscroll-behavior:contain}
.sidebar-panel{max-height:calc(100dvh - 110px);height:calc(100dvh - 110px)}
.sidebar-panel .monitor-item{cursor:pointer;position:relative}
.sidebar-panel .monitor-item-name{font-size:.85rem;font-weight:650;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sidebar-panel .monitor-item-username{margin-left:0;font-size:.72rem}
.sidebar-panel .monitor-item-status .live-status-badge{font-size:.62rem;padding:2px 6px;border-radius:9px;gap:4px;box-shadow:none}
.sidebar-panel .monitor-item-status .live-status-badge i{font-size:.45rem}
.sidebar-panel .monitor-item-status .live-status-badge .pulse{display:none}
#unifiedSettingsPanel .card{border-radius:7px}
#unifiedSettingsPanel .form-label{font-size:.77rem;margin-bottom:3px}
#unifiedSettingsPanel .form-control,#unifiedSettingsPanel .form-select{font-size:.83rem}
@media(max-width:900px){.sidebar-panel{max-height:50dvh}.monitoring-statistics{grid-template-columns:repeat(2,minmax(0,1fr))}}
#headerDateFilterContainer{display:none}
#monitorList .monitor-item:focus-visible{outline:2px solid var(--bs-primary);outline-offset:-2px}
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

                <button type="button" class="btn btn-sm btn-outline-primary" id="refreshLiveNow"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
            </div>
        </div>

        <div class="monitoring-filter-bar" id="monitoringActionBar">
            <label>From <input type="date" id="unifiedStartDate" class="form-control form-control-sm"></label>
            <label>To <input type="date" id="unifiedEndDate" class="form-control form-control-sm"></label>
            <button type="button" class="btn btn-sm btn-primary" id="unifiedApplyFilters">Filter</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="unifiedClearFilters">Clear</button>
            <?php if(($_SESSION['role']??'')===ROLE_ADMIN): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" id="unifiedRemoveScreenshots"><i class="fas fa-trash-alt me-1"></i>Remove screenshots</button>
            <?php endif; ?>
            <span id="unifiedSelectionSummary" class="small text-muted ms-auto">All devices</span>
        </div>
        <div id="unifiedCleanupProgress" class="small px-2" role="status" aria-live="polite" hidden></div>
        <div class="monitoring-statistics" aria-label="Monitoring statistics">
            <div><span id="totalLogs">0</span><small>Total logs</small></div>
            <div><span id="browserLogs">0</span><small>Browser</small></div>
            <div><span id="keyLogs">0</span><small>Keyboard</small></div>
            <div><span id="usbLogs">0</span><small>USB</small></div>
        </div>
        <nav class="monitoring-tabs" id="monitoringTabs" role="tablist" aria-label="Monitor information">
            <button class="monitoring-tab active" role="tab" aria-selected="true" data-monitor-tab="screenshots">Screenshots</button>
            <button class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="key">Keyboard</button>
            <button class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="browser">Browser</button>
            <button class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="usb">USB</button>
            <button class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="settings">Settings</button>
        </nav>
        <section id="unifiedScreenshotPanel" role="tabpanel">
            <div class="main-header">
                <h6><i class="fas fa-image me-2"></i>Screenshots <span class="badge bg-secondary ms-2" id="screenshotCount">0</span>
                <small class="text-muted ms-2" id="timezoneIndicator"><i class="fas fa-clock me-1"></i><span id="userTimezone">Loading...</span></small></h6>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div id="headerDateFilterContainer"></div>
                    <label class="small mb-0" for="perPageSelect">Per page</label>
                    <select class="form-select form-select-sm" id="perPageSelect" style="width:auto">
                        <option value="10">10</option><option value="20">20</option><option value="30">30</option><option value="50" selected>50</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" id="fullscreenBtn" title="Full Screen View"><i class="fas fa-expand me-1"></i>Full Screen</button>
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
        </section>
        <section id="unifiedLogPanel" role="tabpanel" hidden>
            <?php $embeddedLogBrowse=true;include __DIR__.'/logbrowse.php';unset($embeddedLogBrowse); ?>
        </section>
        <section id="unifiedSettingsPanel" role="tabpanel" hidden>
            <div id="unifiedSettingsContent" class="p-2 small text-muted">Select a monitor to view its settings.</div>
        </section>
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
                        <img id="modalImage" src="" alt="Selected screenshot" class="img-fluid"><time id="historyFrameTime" class="history-frame-time" aria-live="off"></time>
                    </section>

                    <aside class="history-transcript" id="historyTranscriptPanel" aria-label="Keyboard transcript">
                        <div class="key-transcript-nav">
                            <strong>Keyboard transcript</strong>
                        </div>
                        <div class="key-transcript-nav">
                            <button id="historyPrevRange" class="btn btn-sm btn-outline-secondary" type="button">‹ Previous</button>
                            <strong id="historyTranscriptRange" class="small text-muted"></strong>
                            <button id="historyNextRange" class="btn btn-sm btn-outline-secondary" type="button">Next ›</button>
                        </div>
                        <div id="historyTranscriptInfo" class="history-transcript-info"></div>
                        <div id="historyTranscriptBody" aria-live="polite">Select a time range.</div>
                    </aside>
                    <section class="history-bottom" aria-label="Screenshot and keylog history timeline">
                        <div class="key-timeline-wrapper">
                            <div class="key-timeline-heading">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <strong class="small">History</strong>
                                    <span id="historyTimelineStatus" class="small text-muted" aria-live="polite">Drag red cursor to inspect screenshots.</span>
                                </div>
                                <div class="history-transport" role="toolbar" aria-label="History timeline controls">
                                    <button id="historyZoomOut" type="button" class="btn btn-sm btn-outline-secondary" title="Zoom out (−)" aria-label="Zoom timeline out">−</button>
                                    <button id="historyZoomIn" type="button" class="btn btn-sm btn-outline-secondary" title="Zoom in (+)" aria-label="Zoom timeline in">+</button>
                                    <button id="historyFit" type="button" class="btn btn-sm btn-outline-secondary" title="Show entire day">24h</button>
                                    <label for="historyScale" class="visually-hidden">Timeline scale</label>
                                    <select id="historyScale" class="form-select form-select-sm" aria-label="History timeline scale">
                                        <option value="near">15 min</option><option value="medium" selected>1 hour</option>
                                        <option value="wide">6 hours</option><option value="day">24 hours</option>
                                    </select>
                                    <time id="historyPlayheadTime" class="history-playhead-label" aria-label="Playhead timestamp"></time>
                                    <button id="historyDrawerToggle" class="btn btn-sm btn-outline-secondary" type="button" aria-controls="historyTranscriptPanel" aria-expanded="true">Hide keys</button>
                                </div>
                            </div>
                            <div class="key-timeline-scroller" id="historyTimelineViewport">
                                <div class="key-cctv-ruler history-ruler" id="historyTimelineRuler" tabindex="0" role="group"
                                     aria-label="CCTV timeline. Drag the red playhead to scrub; drag the blue range or its borders to pan or resize. Wheel zooms, Shift-wheel pans."></div>
                            </div>
                            <div class="key-range-inputs">
                                <label for="historyFrom">From <input id="historyFrom" class="form-control form-control-sm" type="datetime-local" step="1" aria-label="History range start"></label>
                                <label for="historyTo">To <input id="historyTo" class="form-control form-control-sm" type="datetime-local" step="1" aria-label="History range end"></label>
                            </div>
                        </div>
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

<?php // Shared key history assets are loaded by the embedded Log Browse view. ?>

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
        viewScreenshot(shot.url,shot.user,shot.ip,id,shot.time,shot.thumbnail);
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
                                      ip:monitor.ip_address||'—',time:String(item.modified||''),thumbnail:thumbnailUrl});
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
        historyState=null;
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
        $('.monitor-item').removeClass('selected');
        
        // Update UI
        updateSelectAllCheckbox();
        
        unifiedSelectMonitor(0);
    });
    
    // Entire monitor row is the selector; no radio input is necessary.
    $(document).on('click','.monitor-item',function(){
        const id=Number($(this).attr('data-monitor-id'));
        if(!Number.isInteger(id)||id<1)return;
        unifiedSelectMonitor(id);
    });
    $(document).on('keydown','.monitor-item',function(event){
        if(event.key==='Enter'||event.key===' '){
            event.preventDefault();
            unifiedSelectMonitor(Number($(this).attr('data-monitor-id')));
        }
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
            const userNo=String(monitor.user_no||'').toLowerCase();
            const type = (monitor.type || '').toLowerCase();
            const ip = String(monitor.last_seen_ip || monitor.ip_address || '').toLowerCase();
            
            return username.includes(filterText) ||
                   userNo.includes(filterText) ||
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
    const ordered=window.sortMonitorOptions(monitors);
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
        const title=number+': '+label;
        const ip=escapeViewerText(monitor.last_seen_ip||monitor.ip_address||'—');
        const platform=escapeViewerText(monitor.platform||'—');
        const machine=escapeViewerText(monitor.type||'—');
        return `
            <div class="monitor-item ${chosen?'selected':''}" role="button" tabindex="0" aria-pressed="${chosen?'true':'false'}" data-monitor-id="${Number(monitor.id)}">
                <div class="monitor-item-header">
                    <div class="monitor-item-name" title="${title}">${title}</div>
                    <div class="monitor-item-status">
                        <span class="live-status-badge status-${status}">
                            <i class="fas fa-circle"></i><span>${escapeViewerText(statusText)}</span>
                            ${status==='online'?'<div class="pulse"></div>':''}
                        </span>
                    </div>
                </div>
                <div class="monitor-item-username">
                    <i class="fas fa-desktop me-1"></i>${platform} · ${machine} · ${ip} · #${Number(monitor.id)}
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
    if(window.headerStartDate)params.append('start_date',window.headerStartDate);
    if(window.headerEndDate)params.append('end_date',window.headerEndDate);
    
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

function viewScreenshot(imageUrl, monitorName, ipAddress, screenshotId = null, timestamp = '', thumbnailUrl = '') {
    $("#modalImage").attr("src", imageUrl);
    $("#modalTitle").text(`Screenshot - ${monitorName} (${ipAddress})`);
    
    // Store current screenshot ID for navigation
    window.currentScreenshotId = screenshotId;
    startHistoryViewer(screenshotId, timestamp, imageUrl, thumbnailUrl);
    
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
            if ($(e.target).closest('input,select,textarea,[contenteditable],#historyTimelineRuler').length) return;
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
                historySelectExternalScreenshot(screenshot);
                
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
const HISTORY_SCALES = {near:55,medium:14,wide:2.5};
let historyState=null,historySeq=0,historyKeySeq=0;

function historyMillis(value){
    if(typeof value==='number')return value>100000000000?value:value*1000;
    const match=String(value||'').match(/^(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d):(\d\d)/);
    return match?new Date(+match[1],+match[2]-1,+match[3],+match[4],+match[5],+match[6]).getTime():NaN;
}
function historyClock(ms,withDate=false){
    const date=new Date(ms);
    if(!Number.isFinite(date.getTime()))return '—';
    const two=n=>String(n).padStart(2,'0');
    const time=[date.getHours(),date.getMinutes(),date.getSeconds()].map(two).join(':');
    return withDate?[date.getFullYear(),two(date.getMonth()+1),two(date.getDate())].join('-')+' '+time:time;
}
function historyImageUrl(path){
    const value=String(path||'');
    return /^https?:\/\//i.test(value)?value:historyBase+value;
}
function startHistoryViewer(id,timestamp,fullUrl='',thumbnailUrl=''){
    const match=/^screenshot_(\d+)_/.exec(String(id||''));
    if(!match)return;
    const center=historyMillis(timestamp);
    const ms=Number.isFinite(center)?center:Date.now();
    historyState={
        monitor:Number(match[1]),start:ms-12*3600000,end:ms+12*3600000,
        rangeStart:ms-150000,rangeEnd:ms+150000,
        scale:$('#historyScale').val()||'medium',width:0,
        keys:[],shots:[],drag:null,activeId:id,activeTime:ms,
        focus:{global_id:id,ms,full:fullUrl,thumb:thumbnailUrl||fullUrl}
    };
    $('#historyTranscriptInfo').text('');
    historyRenderRuler(true);
    historyLoadDomain();
}
function historySelectExternalScreenshot(shot){
    const id=String(shot.global_id||''),ms=historyMillis(shot.capture_datetime);
    if(!Number.isFinite(ms))return;
    const current=historyState;
    const monitor=Number((/^screenshot_(\d+)_/.exec(id)||[])[1]);
    const full=historyImageUrl(shot.screenshot||'');
    const thumb=historyImageUrl(shot.thumbnail||shot.screenshot||'');
    if(!current||current.monitor!==monitor){
        startHistoryViewer(id,shot.capture_datetime,full,thumb);return;
    }
    current.focus={global_id:id,ms,full,thumb};
    current.activeId=id;current.activeTime=ms;
    if(ms<current.start||ms>current.end){
        const duration=current.rangeEnd-current.rangeStart;
        current.start=ms-12*3600000;current.end=ms+12*3600000;
        current.rangeStart=ms-duration/2;current.rangeEnd=ms+duration/2;
        historyLoadDomain();
    }else{
        const half=(current.rangeEnd-current.rangeStart)/2;
        const from=Math.max(current.start,Math.min(current.end-2*half,ms-half));
        historySetRange(from,from+2*half,true);
    }
}
function historySetRange(from,to,load=true){
    const state=historyState;
    if(!state||!Number.isFinite(from)||!Number.isFinite(to))return;
    const minimum=1000;
    const start=Math.max(state.start,Math.min(state.end-minimum,from));
    const end=Math.max(start+minimum,Math.min(state.end,to));
    state.rangeStart=start;state.rangeEnd=end;
    historyUpdateVisual();
    if(load){
        historyChooseNearestShot();
        historyLoadTranscript();
    }
}
function historyUpdateVisual(){
    const state=historyState;if(!state)return;
    const span=state.end-state.start;
    const left=(state.rangeStart-state.start)/span*state.width;
    const right=(state.rangeEnd-state.start)/span*state.width;
    const selected=document.getElementById('historyRangeSelection');
    if(selected&&state.width){
        selected.style.left=Math.max(0,left)+'px';
        selected.style.width=Math.max(2,right-left)+'px';
    }
    const label=historyClock(state.rangeStart,true)+' – '+historyClock(state.rangeEnd,true);
    $('#historySelectedRange,#historyTranscriptRange').text(label);
    const from=document.getElementById('historyFrom'),to=document.getElementById('historyTo');
    if(from&&document.activeElement!==from)from.value=historyClock(state.rangeStart,true).replace(' ','T');
    if(to&&document.activeElement!==to)to.value=historyClock(state.rangeEnd,true).replace(' ','T');
    $('#historyPrevRange').prop('disabled',state.rangeStart<=state.start);
    $('#historyNextRange').prop('disabled',state.rangeEnd>=state.end);
}
function historyRenderRuler(center=false){
    const state=historyState,viewport=document.getElementById('historyTimelineViewport');
    const ruler=document.getElementById('historyTimelineRuler');
    if(!state||!viewport||!ruler)return;
    const span=state.end-state.start,minutes=span/60000;
    const width=Math.min(24000,Math.max(viewport.clientWidth||800,Math.round(minutes*HISTORY_SCALES[state.scale])));
    const pxPerMinute=width/minutes;
    const steps=[1,2,5,10,15,30,60,120,360,720,1440];
    const step=steps.find(value=>value*pxPerMinute>=90)||1440;
    const first=Math.ceil(state.start/60000/step)*step;
    state.width=width;
    const x=ms=>Math.min(width,Math.max(0,(ms-state.start)/span*width));
    const ticks=[];
    for(let minute=first;minute*60000<=state.end&&ticks.length<350;minute+=step){
        ticks.push('<div class="key-cctv-tick major" style="left:'+x(minute*60000)+'px"><span class="key-cctv-tick-label">'
            +escapeViewerText(historyClock(minute*60000))+'</span></div>');
    }
    const activity=state.keys.map(item=>{
        const n=Math.max(1,Number(item.event_count)||1);
        const height=Math.min(33,Math.max(10,8+Math.log2(n+1)*5));
        return '<div class="key-cctv-activity" style="left:'+x(item.ms)+'px;height:'+height+'px;top:'+(83-height)+'px" title="'
            +escapeViewerText(historyClock(item.ms,true)+' · '+n+' key events')+'" aria-hidden="true"></div>';
    }).join('');
    const all=historyAllShots(state),shotMarkers=all.map(shot=>
        '<button class="history-shot-marker'+(shot.global_id===state.activeId?' is-active':'')
        +'" type="button" data-history-shot="'+escapeViewerText(shot.global_id)+'" style="left:'+x(shot.ms)
        +'px" title="'+escapeViewerText('Screenshot: '+historyClock(shot.ms,true))+'"></button>').join('');
    let last=-Infinity;
    const thumbnails=[];
    if(pxPerMinute>=7){
        for(const shot of all){
            const position=x(shot.ms);
            if(position-last<116&&shot.global_id!==state.activeId)continue;
            last=position;
            thumbnails.push('<button class="history-timeline-image'+(shot.global_id===state.activeId?' is-active':'')
                +'" type="button" data-history-shot="'+escapeViewerText(shot.global_id)+'" style="left:'+position
                +'px" title="'+escapeViewerText(historyClock(shot.ms,true))+'"><img loading="lazy" src="'
                +escapeViewerText(shot.thumb)+'" alt="Screenshot thumbnail"><time>'
                +escapeViewerText(historyClock(shot.ms))+'</time></button>');
        }
    }
    const playhead=Number.isFinite(state.activeTime)?
        '<div class="key-cctv-playhead" style="left:'+x(state.activeTime)+'px" aria-hidden="true"></div>':'';
    ruler.style.width=width+'px';
    ruler.innerHTML='<div class="key-cctv-baseline"></div>'+ticks.join('')+activity+shotMarkers
        +thumbnails.join('')+'<div class="key-cctv-selection" id="historyRangeSelection"></div>'+playhead;
    historyUpdateVisual();
    if(center)historyCenterView();
}
function historyCenterView(){
    const state=historyState,viewport=document.getElementById('historyTimelineViewport');
    if(!state||!viewport||!state.width)return;
    const midpoint=(state.rangeStart+state.rangeEnd)/2;
    viewport.scrollLeft=Math.max(0,(midpoint-state.start)/(state.end-state.start)*state.width-viewport.clientWidth/2);
}
function historyAllShots(state){
    const map=new Map();
    state.shots.forEach(shot=>map.set(shot.global_id,shot));
    if(state.focus&&Number.isFinite(state.focus.ms)&&state.focus.ms>=state.start&&state.focus.ms<=state.end)
        map.set(state.focus.global_id,state.focus);
    return [...map.values()].sort((a,b)=>a.ms-b.ms);
}
function historyChooseNearestShot(){
    const state=historyState;if(!state)return;
    const shots=historyAllShots(state);
    if(!shots.length)return;
    const midpoint=(state.rangeStart+state.rangeEnd)/2;
    const nearest=shots.reduce((best,shot)=>Math.abs(shot.ms-midpoint)<Math.abs(best.ms-midpoint)?shot:best,shots[0]);
    historyShowShot(nearest);
}
function historyShowShot(shot){
    const state=historyState;if(!state||!shot)return;
    state.activeId=shot.global_id;state.activeTime=shot.ms;
    window.currentScreenshotId=shot.global_id;
    const image=document.getElementById('modalImage');
    if(image&&image.getAttribute('src')!==shot.full)image.src=shot.full;
    const ruler=document.getElementById('historyTimelineRuler');
    if(ruler){
        ruler.querySelectorAll('[data-history-shot]').forEach(node=>
            node.classList.toggle('is-active',node.dataset.historyShot===shot.global_id));
        const playhead=ruler.querySelector('.key-cctv-playhead');
        if(playhead&&state.width)playhead.style.left=(shot.ms-state.start)/(state.end-state.start)*state.width+'px';
    }
}
async function historyLoadDomain(){
    const state=historyState;if(!state)return;
    const seq=++historySeq,from=historyClock(state.start,true),to=historyClock(state.end,true);
    $('#historyTimelineStatus').text('Loading screenshot and keyboard activity…');
    const shotsParams=new URLSearchParams({monitor_id:String(state.monitor),from,to});
    const keysParams=new URLSearchParams({device_id:String(state.monitor),start_date:from.slice(0,10),end_date:to.slice(0,10)});
    const getJson=async url=>{
        const response=await fetch(url,{credentials:'same-origin'});
        if(!response.ok)throw new Error('HTTP '+response.status);
        return response.json();
    };
    const [shotsResult,keysResult]=await Promise.allSettled([
        getJson(historyBase+'view/api_screenshot_timeline?'+shotsParams),
        getJson(historyBase+'logbrowse/api_key_timeline?'+keysParams)
    ]);
    if(state!==historyState||seq!==historySeq)return;
    const notes=[];
    if(shotsResult.status==='fulfilled'&&shotsResult.value.success){
        state.shots=(shotsResult.value.screenshots||[]).map(shot=>({
            global_id:String(shot.global_id||''),ms:historyMillis(shot.capture_datetime),
            full:historyImageUrl(shot.screenshot),thumb:historyImageUrl(shot.thumbnail||shot.screenshot)
        })).filter(shot=>shot.global_id&&Number.isFinite(shot.ms));
        notes.push(state.shots.length+' sampled screenshots');
    }else{
        state.shots=[];
        notes.push('Screenshots unavailable');
    }
    if(keysResult.status==='fulfilled'&&keysResult.value.success){
        state.keys=(keysResult.value.events||[]).map(item=>({
            ms:historyMillis(item.date),event_count:Number(item.event_count)||1
        })).filter(item=>Number.isFinite(item.ms));
        notes.push(state.keys.length+' keyboard activity buckets');
        if(keysResult.value.limited)notes.push('activity buckets truncated');
    }else{
        state.keys=[];
        notes.push('Keyboard activity markers unavailable');
    }
    $('#historyTimelineStatus').text(notes.join(' · ')+' · Drag the middle to pan; drag either edge to resize.');
    historyRenderRuler(true);
    historyChooseNearestShot();
    historyLoadTranscript();
}
async function historyLoadTranscript(){
    const state=historyState;if(!state)return;
    const seq=++historyKeySeq;
    const panel=$('#historyTranscriptBody').text('Loading keyboard events for selected range…');
    $('#historyTranscriptInfo').text('');
    const from=historyClock(state.rangeStart,true),to=historyClock(state.rangeEnd,true);
    const params=new URLSearchParams({device_id:String(state.monitor),from,to});
    try{
        const response=await fetch(historyBase+'logbrowse/api_key_context?'+params,{credentials:'same-origin'});
        if(!response.ok)throw new Error('HTTP '+response.status);
        const data=await response.json();
        if(!data.success)throw new Error(data.error||'Unable to load keylogs');
        if(state!==historyState||seq!==historyKeySeq)return;
        const keys=data.logs||[];
        $('#historyTranscriptInfo').text(keys.length+' key events'+(data.limited?' · First 2,000 shown; narrow the range for more':''));
        panel.html(keys.length?monitorTranscriptKeyRows(keys,escapeViewerText)
            :'<div class="text-muted">No keys were recorded in this time range.</div>');
    }catch(error){
        if(state===historyState&&seq===historyKeySeq)panel.text('Cannot load keylogs: '+error.message);
    }
}
function historyPointerTime(event,ruler){
    const state=historyState;
    const rect=ruler.getBoundingClientRect();
    const ratio=Math.max(0,Math.min(1,(event.clientX-rect.left)/Math.max(1,rect.width)));
    return state.start+ratio*(state.end-state.start);
}
function historyHitMode(state,time,ruler){
    const perPixel=(state.end-state.start)/Math.max(1,ruler.getBoundingClientRect().width);
    const selectedPixels=(state.rangeEnd-state.rangeStart)/perPixel;
    const edgeTolerance=Math.min(14,Math.max(2,selectedPixels*.3))*perPixel;
    const fromDistance=Math.abs(time-state.rangeStart),toDistance=Math.abs(time-state.rangeEnd);
    const duration=state.rangeEnd-state.rangeStart;
    // Reserve the center third for panning even when selection is very narrow.
    if(time>state.rangeStart&&time<state.rangeEnd&&fromDistance>duration*.3&&toDistance>duration*.3)return 'pan';
    if(fromDistance<=edgeTolerance||toDistance<=edgeTolerance)
        return fromDistance<=toDistance?'start':'end';
    if(time>state.rangeStart&&time<state.rangeEnd)return 'pan';
    return fromDistance<=toDistance?'start':'end';
}
document.addEventListener('pointerdown',event=>{
    const ruler=event.target.closest?.('#historyTimelineRuler');
    const state=historyState;
    if(!ruler||!state||event.button!==0)return;
    // Thumbnail clicks are discrete navigation, not range drags.
    if(event.target.closest?.('[data-history-shot]'))return;
    const time=historyPointerTime(event,ruler);
    const mode=historyHitMode(state,time,ruler);
    if(time<state.rangeStart)historySetRange(Math.round(time/1000)*1000,state.rangeEnd,false);
    else if(time>state.rangeEnd)historySetRange(state.rangeStart,Math.round(time/1000)*1000,false);
    state.drag={pointerId:event.pointerId,mode,
        anchor:time,from:state.rangeStart,to:state.rangeEnd};
    ruler.setPointerCapture(event.pointerId);
    ruler.classList.add('is-dragging');
    event.preventDefault();
});
document.addEventListener('pointermove',event=>{
    const ruler=document.getElementById('historyTimelineRuler'),state=historyState;
    if(!ruler||!state)return;
    const time=historyPointerTime(event,ruler);
    if(!state.drag){
        const mode=historyHitMode(state,time,ruler);
        ruler.style.cursor=mode==='pan'?'grab':'ew-resize';
        return;
    }
    if(state.drag.pointerId!==event.pointerId||!ruler.hasPointerCapture(event.pointerId))return;
    const drag=state.drag,delta=time-drag.anchor,minimum=1000;
    if(drag.mode==='pan'){
        const duration=drag.to-drag.from;
        const start=Math.max(state.start,Math.min(state.end-duration,drag.from+delta));
        historySetRange(start,start+duration,false);
    }else if(drag.mode==='start'){
        historySetRange(Math.min(drag.to-minimum,Math.max(state.start,drag.from+delta)),drag.to,false);
    }else if(drag.mode==='end'){
        historySetRange(drag.from,Math.max(drag.from+minimum,Math.min(state.end,drag.to+delta)),false);
    }
});
function historyFinishDrag(event,commit){
    const state=historyState,ruler=document.getElementById('historyTimelineRuler');
    if(!state?.drag||!ruler||state.drag.pointerId!==event.pointerId)return;
    state.drag=null;
    ruler.classList.remove('is-dragging');
    if(ruler.hasPointerCapture(event.pointerId))ruler.releasePointerCapture(event.pointerId);
    if(commit){historyChooseNearestShot();historyLoadTranscript();}
}
document.addEventListener('pointerup',event=>historyFinishDrag(event,true));
document.addEventListener('pointercancel',event=>historyFinishDrag(event,false));
$(document).on('click','#historyTimelineRuler [data-history-shot]',function(){
    const state=historyState;if(!state)return;
    const shot=historyAllShots(state).find(item=>item.global_id===this.dataset.historyShot);
    if(!shot)return;
    historyShowShot(shot);
    const duration=state.rangeEnd-state.rangeStart;
    const from=Math.max(state.start,Math.min(state.end-duration,shot.ms-duration/2));
    historySetRange(from,from+duration,true);
    historyCenterView();
});
$(document).on('change','#historyFrom,#historyTo',function(){
    const state=historyState;if(!state)return;
    const from=historyMillis($('#historyFrom').val().replace('T',' '));
    const to=historyMillis($('#historyTo').val().replace('T',' '));
    if(!Number.isFinite(from)||!Number.isFinite(to)||from>=to||to-from>86400000){
        $('#historyTimelineStatus').text('Choose a valid time range no longer than 24 hours.');
        historyUpdateVisual();return;
    }
    if(from<state.start||to>state.end){
        const center=(from+to)/2;
        state.start=center-12*3600000;state.end=center+12*3600000;
        state.rangeStart=from;state.rangeEnd=to;
        historyRenderRuler(true);
        historyLoadDomain();
    }else historySetRange(from,to,true);
});
$(document).on('change','#historyScale',function(){
    if(!historyState)return;
    historyState.scale=HISTORY_SCALES[this.value]?this.value:'medium';
    historyRenderRuler(true);
});
$(document).on('click','#historyPrevRange,#historyNextRange',function(){
    const state=historyState;if(!state)return;
    const direction=this.id==='historyNextRange'?1:-1;
    const duration=state.rangeEnd-state.rangeStart;
    const start=Math.max(state.start,Math.min(state.end-duration,state.rangeStart+direction*duration));
    historySetRange(start,start+duration,true);historyCenterView();
});
$(document).on('click','#historyDrawerToggle',function(){
    const collapsed=$('.history-viewer').toggleClass('drawer-collapsed').hasClass('drawer-collapsed');
    $('.history-transcript').toggleClass('is-collapsed',collapsed);
    $(this).text(collapsed?'Show transcript':'Hide transcript').attr('aria-expanded',String(!collapsed));
    historyRenderRuler(false);
});
document.addEventListener('keydown',event=>{
    if(event.target?.id!=='historyTimelineRuler'||!historyState)return;
    if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;
    event.preventDefault();
    const state=historyState,dir=event.key==='ArrowRight'?1:-1;
    if(event.shiftKey)historySetRange(state.rangeStart,Math.max(state.rangeStart+1000,Math.min(state.end,state.rangeEnd+dir*60000)),true);
    else{
        const duration=state.rangeEnd-state.rangeStart;
        const start=Math.max(state.start,Math.min(state.end-duration,state.rangeStart+dir*60000));
        historySetRange(start,start+duration,true);
    }
});
$(document).on('shown.bs.modal','#screenshotModal',()=>historyRenderRuler(true));

</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 