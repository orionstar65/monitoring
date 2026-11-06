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
.history-transcript .key-transcript{grid-template-columns:64px minmax(0,1fr);gap:3px 6px}
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
.history-ruler .key-cctv-playhead{height:116px;top:4px;z-index:6;cursor:ew-resize;pointer-events:auto;background:linear-gradient(90deg,transparent calc(50% - 1.5px),var(--bs-danger,#e45858) calc(50% - 1.5px),var(--bs-danger,#e45858) calc(50% + 1.5px),transparent calc(50% + 1.5px));padding:0;border:0;outline-offset:3px;width:18px}
.history-ruler .key-cctv-playhead::before{left:calc(50% - 7px);top:0;border-top-color:var(--bs-danger,#e45858);pointer-events:none}
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
.monitoring-custom-range{display:flex;align-items:end;gap:6px;flex-wrap:wrap}
.monitoring-custom-range[hidden]{display:none!important}
.monitoring-custom-range label,.monitoring-preset-label{display:block}
.monitoring-preset-label select{min-width:135px;max-width:160px}
#unifiedDateDescription{align-self:center;font-variant-numeric:tabular-nums}
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
/* Compact single-toolbar CCTV viewer, aligned transcript panel and one status indicator. */
.sidebar-panel .live-status-badge::before,.sidebar-panel .live-status-badge::after{content:none!important;display:none!important}
.sidebar-panel .monitor-status-indicator{display:inline-block!important;flex:none;width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.9}
.history-viewer{grid-template-columns:minmax(0,1fr) minmax(310px,390px)}
.history-transcript{display:flex;flex-direction:column;gap:0;padding:0;overflow:hidden}
.history-transcript.is-collapsed{display:none}
.history-transcript-toolbar{display:flex;align-items:center;justify-content:space-between;gap:6px;padding:7px 9px 4px;border-bottom:1px solid var(--bs-border-color)}
.history-transcript-heading{display:flex;flex-direction:column;gap:1px;min-width:0;font-size:.83rem}
.history-transcript-heading small{font-size:.69rem;font-variant-numeric:tabular-nums}
.history-transcript-navigation{display:flex;gap:3px;flex:none}
.history-transcript-navigation .btn{padding:3px 8px;line-height:1.25}
.history-transcript-period{padding:4px 9px 5px;color:var(--bs-secondary-color);font-size:.69rem;border-bottom:1px solid var(--bs-border-color);font-variant-numeric:tabular-nums}
.history-transcript-body{flex:1;min-height:0;overflow-y:auto;padding:7px 9px 11px;scrollbar-width:thin;overscroll-behavior:contain}
.history-nearest-key{margin:2px 9px;padding:2px 0;font-size:.73rem;text-align:left}
.history-nearest-key[hidden]{display:none!important}
.history-transcript .key-transcript{display:grid;grid-template-columns:56px minmax(0,1fr);gap:3px 5px;align-items:start}
.history-transcript .key-transcript-content{overflow-wrap:anywhere;min-width:0;line-height:1.5}
.history-bottom{padding:5px 8px 6px;min-height:0}
.history-toolbar{display:flex;align-items:center;gap:5px;min-width:0;overflow-x:auto;scrollbar-width:thin;white-space:nowrap;padding-bottom:3px}
.history-toolbar-summary{display:flex;align-items:center;gap:6px;flex:1;min-width:100px;overflow:hidden;font-size:.73rem}
.history-toolbar-summary #historyTimelineStatus{overflow:hidden;text-overflow:ellipsis;font-size:.68rem}
.history-date-field,.history-scale-label{display:flex;gap:4px;align-items:center;font-size:.69rem;flex:none;margin:0}
.history-date-field input{width:160px;max-width:none;padding:2px 5px;font-size:.7rem;line-height:1.1;min-height:27px}
.history-scale-label select{width:74px;padding:3px 17px 3px 5px;font-size:.7rem;line-height:1.1;min-height:27px}
.history-toolbar .btn{padding:3px 6px;min-width:26px;line-height:1.2}
.history-toolbar .history-playhead-label{min-width:59px;font-size:.7rem;font-weight:600}
.history-ruler.key-cctv-ruler{height:93px}
.history-ruler .key-cctv-baseline{top:29px}
.history-ruler .key-cctv-tick{top:17px;height:13px}
.history-ruler .key-cctv-tick.major{top:15px;height:15px}
.history-ruler .key-cctv-tick-label{top:-13px;font-size:.58rem}
.history-ruler .key-cctv-activity{top:29px!important;height:5px!important}
.history-ruler .key-cctv-selection{top:3px;bottom:3px}
.history-ruler .key-cctv-selection::before,.history-ruler .key-cctv-selection::after{height:22px}
.history-ruler .key-cctv-playhead{height:85px;top:3px}
.history-shot-marker{top:32px;height:6px}
.history-timeline-image{top:39px;height:48px;width:87px}
.history-timeline-image img{height:30px}
.history-timeline-image time{font-size:.57rem}
@media(max-width:850px){.history-viewer{grid-template-columns:1fr}.history-toolbar-summary{min-width:60px}}

/* One-line sidebar search with contextual icon-only clear selection control. */
.sidebar-panel .sidebar-header{padding:7px 8px}
.monitor-search-row{display:flex;align-items:center;gap:5px;min-width:0}
.monitor-search-row .form-control{flex:1;min-width:0;border-radius:6px}
.monitor-search-row .btn{flex:none;min-width:29px;height:29px;padding:4px 6px}
.monitor-search-row [hidden]{display:none!important}
/* Same CCTV card, status, timestamp and 80% black overlay in every view. */
.screenshot-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px}
.screenshot-card,.cctv-screenshot-card{
    position:relative;aspect-ratio:16/10;overflow:hidden;border-radius:7px;
    background:#0e1319;border:1px solid rgba(110,130,150,.35);
    box-shadow:none;transition:border-color .15s ease,transform .15s ease;cursor:pointer
}
.screenshot-card:hover,.cctv-screenshot-card:hover{transform:translateY(-2px);border-color:var(--monitoring-primary)}
.screenshot-card img,.cctv-screenshot-card img{width:100%;height:100%;object-fit:contain;display:block;transition:none}
.screenshot-card:hover img{transform:none}
.screenshot-card .cctv-screenshot-info,.cctv-screenshot-card .cctv-screenshot-info{
    position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.80);padding:6px 8px;
    display:flex;align-items:center;justify-content:space-between;gap:7px;color:white;
    font-size:.72rem;line-height:1.35;pointer-events:none
}
.cctv-screenshot-info .cctv-username{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    font-weight:600;font-size:.72rem;color:#fff;display:block}
.cctv-screenshot-info .cctv-ip{font-size:.69rem;color:#dae3ec;white-space:nowrap;flex-shrink:0;display:block}
.screenshot-card .cctv-status-indicator,.cctv-screenshot-card .cctv-status-indicator{
    z-index:3;top:7px;left:7px;right:auto;width:9px;height:9px;border:1px solid rgba(255,255,255,.8)
}
.cctv-timestamp{position:absolute;right:5px;top:5px;z-index:3;font-size:.66rem;
    color:#fff;background:rgba(0,0,0,.8);padding:2px 5px;border-radius:4px;
    font-variant-numeric:tabular-nums;pointer-events:none}

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

/* Unified monitoring workspace: concise alignment, typography and spacing. */
#liveSummary.monitoring-overview{
    display:grid;grid-template-columns:minmax(145px,1fr) auto auto;
    align-items:center;gap:16px!important;
    padding:12px 16px!important;border-bottom:1px solid var(--bs-border-color);
    background:var(--bs-body-bg);min-width:0
}
.monitoring-overview-heading{min-width:0}
.monitoring-overview-heading small{display:block;color:var(--bs-secondary-color);
    font-size:.61rem;letter-spacing:.09em;font-weight:700}
.monitoring-overview-heading h2{font-size:1.02rem;font-weight:700;
    line-height:1.4;margin:2px 0 0;color:var(--bs-emphasis-color)}
.monitoring-device-summary{display:flex;gap:0;align-items:center;
    border:1px solid var(--bs-border-color);border-radius:9px;
    overflow:hidden;background:var(--bs-tertiary-bg)}
.monitoring-device-item{display:flex;align-items:baseline;gap:5px;
    padding:8px 10px;white-space:nowrap;border-right:1px solid var(--bs-border-color)}
.monitoring-device-item:last-child{border-right:0}
.monitoring-device-item strong{font-size:.96rem;line-height:1;
    font-weight:750;font-variant-numeric:tabular-nums;color:var(--bs-emphasis-color)}
.monitoring-device-item span{font-size:.7rem;color:var(--bs-secondary-color)}
.monitoring-device-item.online strong{color:var(--bs-success,#198754)}
.monitoring-device-item.idle strong{color:var(--bs-warning-text-emphasis,#946200)}
.monitoring-device-item.offline strong{color:var(--bs-danger,#dc3545)}
.monitoring-overview-actions{display:flex;align-items:center;gap:9px;justify-content:flex-end}
.monitoring-overview-actions small{font-size:.68rem;color:var(--bs-secondary-color);
    white-space:nowrap;font-variant-numeric:tabular-nums}
.monitoring-overview-actions .btn{white-space:nowrap}

.monitoring-filter-bar{display:flex;flex-wrap:wrap;align-items:center;
    gap:8px 12px;padding:10px 16px;border-bottom:1px solid var(--bs-border-color);
    background:var(--bs-tertiary-bg)}
.monitoring-filter-main,.monitoring-filter-context{display:flex;align-items:center;
    gap:8px;min-width:0;flex-wrap:wrap}
.monitoring-filter-main{flex:none}
.monitoring-filter-main .monitoring-preset-label{display:block;min-width:auto;
    font-weight:600;font-size:.73rem;margin:0;white-space:nowrap}
.monitoring-filter-main #unifiedDatePreset{width:148px;min-width:0;max-width:100%}
.monitoring-filter-main .btn,.monitoring-filter-context .btn{white-space:nowrap}
.monitoring-custom-range{align-items:center;gap:7px}
.monitoring-custom-range label{display:flex;align-items:center;gap:6px;
    font-size:.72rem;font-weight:600;white-space:nowrap}
.monitoring-custom-range input{width:134px;max-width:100%}
.monitoring-filter-context{flex:1;justify-content:flex-end}
#unifiedDateDescription{color:var(--bs-secondary-color);font-size:.71rem;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.monitoring-selection-chip{display:inline-block;max-width:240px;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap;border:1px solid var(--bs-border-color);
    background:var(--bs-body-bg);border-radius:6px;padding:4px 8px;
    color:var(--bs-body-color);font-size:.71rem;font-weight:600}
.monitoring-statistics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
    gap:9px;padding:12px 16px;background:var(--bs-body-bg);
    border-bottom:1px solid var(--bs-border-color)}
.monitoring-statistics>.monitoring-stat-card{display:flex;flex-direction:column;
    align-items:flex-start;justify-content:center;gap:5px;min-width:0;
    padding:10px 13px;border:1px solid var(--bs-border-color);
    background:var(--bs-tertiary-bg);border-radius:8px}
.monitoring-stat-card small{color:var(--bs-secondary-color);
    font-size:.7rem;font-weight:600;line-height:1.2}
.monitoring-stat-card strong{display:block;font-size:1.24rem;font-weight:750;
    line-height:1.2;font-variant-numeric:tabular-nums;
    color:var(--bs-emphasis-color);min-width:0}
.monitoring-tabs{display:flex;align-items:stretch;gap:3px;overflow-x:auto;
    padding:0 12px;border-bottom:1px solid var(--bs-border-color);
    background:var(--bs-body-bg);scrollbar-width:thin}
.monitoring-tab{display:inline-flex;align-items:center;gap:8px;flex:none;
    background:none;border:0;border-bottom:2px solid transparent;
    padding:11px 13px 9px;color:var(--bs-secondary-color);
    font-size:.79rem;font-weight:550;white-space:nowrap}
.monitoring-tab i{font-size:.78rem;opacity:.8}
.monitoring-tab:hover{color:var(--bs-emphasis-color);background:var(--bs-tertiary-bg)}
.monitoring-tab.active{color:var(--bs-primary);border-bottom-color:var(--bs-primary);
    background:transparent;font-weight:700}
.monitoring-tab:focus-visible{outline:2px solid var(--bs-primary);outline-offset:-3px}
.main-header.monitoring-screenshot-header{display:flex;flex-wrap:wrap;align-items:center;
    justify-content:space-between;gap:12px;padding:12px 16px;
    border-bottom:1px solid var(--bs-border-color)}
.monitoring-screenshot-heading{display:flex;align-items:center;flex-wrap:wrap;gap:9px}
.monitoring-screenshot-heading h3{font-size:.9rem;font-weight:700;margin:0}
.monitoring-screenshot-heading small{font-size:.7rem;color:var(--bs-secondary-color)}
.monitoring-count-chip{display:inline-block;padding:2px 7px;margin-left:4px;
    border-radius:6px;background:var(--bs-tertiary-bg);
    font-size:.7rem;font-weight:700;color:var(--bs-secondary-color);
    font-variant-numeric:tabular-nums}
.monitoring-screenshot-actions{display:flex;align-items:center;flex-wrap:wrap;gap:10px}
.monitoring-pagesize{display:flex;align-items:center;gap:7px;margin:0;
    font-size:.72rem;color:var(--bs-secondary-color);white-space:nowrap}
.monitoring-pagesize .form-select{width:78px;min-width:0;font-size:.77rem}
@media(max-width:1280px){
    #liveSummary.monitoring-overview{grid-template-columns:1fr auto}
    .monitoring-device-summary{grid-row:2;grid-column:1/-1;width:max-content;max-width:100%}
    .monitoring-overview-actions{grid-column:2;grid-row:1}
}
@media(max-width:760px){
    #liveSummary.monitoring-overview{gap:9px!important;padding:10px 12px!important}
    .monitoring-device-item{padding:7px 8px}
    .monitoring-filter-bar{padding:9px 12px;gap:8px}
    .monitoring-filter-context{justify-content:flex-start;flex-basis:100%}
    #unifiedDateDescription{white-space:normal}
    .monitoring-statistics{gap:6px;padding:8px 12px}
    .monitoring-statistics>.monitoring-stat-card{padding:9px}
    .monitoring-stat-card strong{font-size:1.05rem}
    .monitoring-tab{padding:10px 11px}
    .main-header.monitoring-screenshot-header{padding:10px 12px}
}
@media(max-width:430px){
    .monitoring-overview-actions small{display:none}
    .monitoring-statistics{grid-template-columns:repeat(2,minmax(0,1fr))}
}
</style>

<!-- Modern Dashboard Container -->
<div class="dashboard-container">
    <!-- Left Sidebar - Monitor List -->
    <div class="sidebar-panel">
        <div class="sidebar-header">
            <div class="monitor-search-row">
                <label class="visually-hidden" for="monitorFilter">Search monitors</label>
                <input type="search" class="form-control form-control-sm" id="monitorFilter" placeholder="Search user, machine or IP" autocomplete="off">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllMonitors"
                    aria-label="Clear monitor selection and show all devices" title="Show all devices" hidden>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="sidebar-content" id="monitorList">
            <!-- Monitor items will be loaded here -->
        </div>
    </div>
    
    <!-- Right Main Content - Status Overview + Screenshots -->
    <div class="main-content" style="margin-left: 0 !important;">
        <header id="liveSummary" class="monitoring-overview">
            <div class="monitoring-overview-heading">
                <small>MONITORING OVERVIEW</small>
                <h2>Live monitoring</h2>
            </div>
            <div class="monitoring-device-summary" aria-label="Device connection status">
                <div class="monitoring-device-item"><strong id="total-monitors">0</strong><span>Devices</span></div>
                <div class="monitoring-device-item online"><strong id="online-monitors">0</strong><span>Online</span></div>
                <div class="monitoring-device-item idle"><strong id="inactive-monitors">0</strong><span>Idle</span></div>
                <div class="monitoring-device-item offline"><strong id="offline-monitors">0</strong><span>Offline</span></div>
            </div>
            <div class="monitoring-overview-actions">
                <small id="lastLiveRefresh" aria-live="polite">Not updated</small>
                <button type="button" class="btn btn-sm btn-outline-primary" id="refreshLiveNow">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </header>

        <section class="monitoring-filter-bar" id="monitoringActionBar" aria-label="Date and monitor filters">
            <div class="monitoring-filter-main">
                <label class="monitoring-preset-label" for="unifiedDatePreset">Date range</label>
                <select id="unifiedDatePreset" class="form-select form-select-sm" aria-label="Log and screenshot date range">
                    <option value="today" selected>Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This week</option>
                    <option value="last_week">Last week</option>
                    <option value="this_month">This month</option>
                    <option value="last_month">Last month</option>
                    <option value="last_7_days">Last 7 days</option>
                    <option value="last_30_days">Last 30 days</option>
                    <option value="custom">Custom</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="unifiedClearFilters" title="Reset date range to today">Reset</button>
            </div>
            <div id="unifiedCustomRange" class="monitoring-custom-range" hidden>
                <label for="unifiedStartDate">From <input type="date" id="unifiedStartDate" class="form-control form-control-sm"></label>
                <label for="unifiedEndDate">To <input type="date" id="unifiedEndDate" class="form-control form-control-sm"></label>
                <button type="button" class="btn btn-sm btn-primary" id="unifiedApplyFilters">Apply</button>
            </div>
            <div class="monitoring-filter-context">
                <small id="unifiedDateDescription" aria-live="polite"></small>
                <span class="monitoring-selection-chip" id="unifiedSelectionSummary">All devices</span>
                <?php if(($_SESSION['role']??'')===ROLE_ADMIN): ?>
                <button type="button" class="btn btn-sm btn-outline-danger" id="unifiedRemoveScreenshots"><i class="fas fa-trash-alt me-1"></i>Remove screenshots</button>
                <?php endif; ?>
            </div>
        </section>
        <div id="unifiedCleanupProgress" class="small px-2" role="status" aria-live="polite" hidden></div>
        <section class="monitoring-statistics" aria-label="Activity statistics">
            <div class="monitoring-stat-card"><small>Total logs</small><strong id="totalLogs">0</strong></div>
            <div class="monitoring-stat-card"><small>Browser</small><strong id="browserLogs">0</strong></div>
            <div class="monitoring-stat-card"><small>Keyboard</small><strong id="keyLogs">0</strong></div>
            <div class="monitoring-stat-card"><small>USB</small><strong id="usbLogs">0</strong></div>
        </section>
        <nav class="monitoring-tabs" id="monitoringTabs" role="tablist" aria-label="Monitor information">
            <button type="button" class="monitoring-tab active" role="tab" aria-selected="true" data-monitor-tab="screenshots"><i class="fas fa-image" aria-hidden="true"></i>Screenshots</button>
            <button type="button" class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="key"><i class="fas fa-keyboard" aria-hidden="true"></i>Keyboard</button>
            <button type="button" class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="browser"><i class="fas fa-globe" aria-hidden="true"></i>Browser</button>
            <button type="button" class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="usb"><i class="fas fa-usb" aria-hidden="true"></i>USB</button>
            <?php if(in_array(($_SESSION['role']??''),[ROLE_SUPER_ADMIN,ROLE_SCOPED_ADMIN],true)): ?>
            <button type="button" class="monitoring-tab" role="tab" aria-selected="false" data-monitor-tab="settings"><i class="fas fa-cog" aria-hidden="true"></i>Settings</button>
            <?php endif; ?>
        </nav>
        <section id="unifiedScreenshotPanel" role="tabpanel">
            <div class="main-header monitoring-screenshot-header">
                <div class="monitoring-screenshot-heading">
                    <h3>Screenshots <span class="monitoring-count-chip" id="screenshotCount">0</span></h3>
                    <small id="timezoneIndicator"><i class="far fa-clock me-1"></i><span id="userTimezone">Loading...</span></small>
                </div>
                <div class="monitoring-screenshot-actions">
                    <div id="headerDateFilterContainer" hidden></div>
                    <label class="monitoring-pagesize" for="perPageSelect">
                        Per page
                        <select class="form-select form-select-sm" id="perPageSelect">
                            <option value="10">10</option><option value="20">20</option><option value="30">30</option><option value="50" selected>50</option>
                        </select>
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="fullscreenBtn" title="Full screen view">
                        <i class="fas fa-expand me-1"></i>Full screen
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
                        <div class="history-transcript-toolbar">
                            <div class="history-transcript-heading">
                                <strong><i class="fas fa-keyboard me-1"></i> Keyboard</strong>
                                <small id="historyTranscriptInfo" class="text-muted"></small>
                            </div>
                            <div class="history-transcript-navigation" role="group" aria-label="Navigate keyboard transcript range">
                                <button id="historyPrevRange" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Previous transcript range" title="Previous range"><i class="fas fa-chevron-left"></i></button>
                                <button id="historyNextRange" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Next transcript range" title="Next range"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div id="historyTranscriptRange" class="history-transcript-period"></div>
                        <button type="button" id="historyJumpToKeyboard" class="btn btn-sm btn-link history-nearest-key" hidden>Go to nearest keyboard activity</button>
                        <div id="historyTranscriptBody" class="history-transcript-body" role="region" aria-label="Recorded keyboard events" aria-live="polite">Choose a time range to inspect keyboard activity.</div>
                    </aside>
                    <section class="history-bottom" aria-label="Screenshot and keyboard history timeline">
                        <div class="history-toolbar" role="toolbar" aria-label="History time range and playback controls">
                            <div class="history-toolbar-summary"><strong>History</strong>
                                <span id="historyTimelineStatus" class="text-muted" aria-live="polite" title="History status">Drag the red playhead to inspect screenshots.</span>
                            </div>
                            <label class="history-date-field" for="historyFrom"><span>From</span>
                                <input id="historyFrom" class="form-control form-control-sm" type="datetime-local" step="1" aria-label="History range start"></label>
                            <label class="history-date-field" for="historyTo"><span>To</span>
                                <input id="historyTo" class="form-control form-control-sm" type="datetime-local" step="1" aria-label="History range end"></label>
                            <label class="history-scale-label" for="historyScale"><span>Scale</span>
                                <select id="historyScale" class="form-select form-select-sm" aria-label="History timeline scale">
                                    <option value="near">15m</option><option value="medium" selected>1h</option>
                                    <option value="wide">6h</option><option value="day">24h</option><option value="custom" disabled>Custom</option>
                                </select></label>
                            <button id="historyZoomOut" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Zoom timeline out" title="Zoom out"><i class="fas fa-minus"></i></button>
                            <button id="historyZoomIn" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Zoom timeline in" title="Zoom in"><i class="fas fa-plus"></i></button>
                            <button id="historyFit" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Fit 24-hour timeline" title="Fit day"><i class="fas fa-expand-arrows-alt"></i></button>
                            <time id="historyPlayheadTime" class="history-playhead-label" aria-label="Playhead timestamp"></time>
                            <button id="historyDrawerToggle" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Toggle keyboard transcript" title="Hide keyboard transcript" aria-controls="historyTranscriptPanel" aria-expanded="true"><i class="fas fa-keyboard"></i></button>
                        </div>
                        <div class="key-timeline-scroller" id="historyTimelineViewport">
                            <div class="key-cctv-ruler history-ruler" id="historyTimelineRuler" tabindex="0" role="group"
                                 aria-label="CCTV timeline. Drag red playhead to scrub; drag the highlighted range or its borders to pan or resize. Wheel to zoom."></div>
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
let screenshotRequestSeq=0;
let liveScreenshotSnapshot=[];
let liveScreenshotSelection='';
let liveScreenshotUpdatedAt=0;
const BASE_IMAGE_URL=<?php echo json_encode(BASE_URL); ?>;

// The portal's date inputs are calendar days in the viewer's own timezone.
// Convert each local midnight separately, so DST days span 23 or 25 hours.
function localCalendarMidnightUtc(day,exclusiveNext=false) {
    const match=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(day||''));
    if(!match)return null;
    const year=Number(match[1]),month=Number(match[2]),date=Number(match[3]);
    const local=new Date(year,month-1,date+(exclusiveNext?1:0));
    if(!Number.isFinite(local.getTime()) ||
       new Date(year,month-1,date).getMonth()!==month-1)return null;
    return local.toISOString();
}
function appendLocalCalendarUtcBounds(params,start=window.headerStartDate,end=window.headerEndDate) {
    const from=start?localCalendarMidnightUtc(start):null;
    const to=end?localCalendarMidnightUtc(end,true):null;
    if(from)params.set('start_utc',from);
    if(to)params.set('end_utc',to);
}

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
    
    
    // Monitor status can refresh less frequently than screenshot tiles.
    // After a manual Send All, the new stored screenshot should appear promptly
    // without a full portal reload or a 30-second wait.
    setInterval(function() {
        if(isAutoRefresh)loadMonitors();
    }, 30000);
    setInterval(function() {
        if(!isAutoRefresh)return;
        if(unifiedActiveTab==='screenshots'||$('#fullscreenModal').hasClass('show')){
            const page=$('#fullscreenModal').hasClass('show')?1:currentPage;
            loadScreenshotsForSelectedMonitor(page);
        }
    }, 10000);
    
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
    
    // Main monitoring and fullscreen share the same screenshot response.
    function openFullscreenView() {
        $('#fullscreenModal').modal('show');
        const selection=selectedMonitors.join(',');
        if(liveScreenshotSelection===selection&&liveScreenshotUpdatedAt
            &&Date.now()-liveScreenshotUpdatedAt<30000){
            displayFullscreenScreenshots(liveScreenshotSnapshot);
        }else{
            $('#fullscreenContainer').html('<div class="small text-white p-3">Loading screenshots…</div>');
            loadScreenshotsForSelectedMonitor(1);
        }
    }
    function loadFullscreenScreenshots() {
        loadScreenshotsForSelectedMonitor(1);
    }
    function displayFullscreenScreenshots(screenshots) {
        const list=Array.isArray(screenshots)?screenshots:[];
        fullscreenShotMap=new Map();
        if(!list.length){showFullscreenError('No screenshots available');return;}
        const html=list.map(item=>{
            const id=String(item.global_id||'');
            const monitor=item.monitor||{};
            const thumbnail=BASE_IMAGE_URL+(item.thumbnail||item.screenshot||'');
            const full=BASE_IMAGE_URL+(item.screenshot||'');
            fullscreenShotMap.set(id,{url:full,user:monitor.username||'Unknown User',
                ip:monitor.ip_address||'—',time:Number(item.modified_time)||0,thumbnail});
            return monitorScreenshotCard(item,'cctv-screenshot-card');
        }).join('');
        $('#fullscreenContainer').html(html);
        attachScreenshotImageFallback($('#fullscreenContainer'));
        $('#fullscreenCount').text(list.length+' screenshots · Updated '+new Date(liveScreenshotUpdatedAt||Date.now()).toLocaleTimeString());
    }
    function showFullscreenError(message) {
        $('#fullscreenContainer').empty().append(
            $('<div class="text-white p-3 small"></div>').text(message));
        $('#fullscreenCount').text('0 screenshots');
    }
    function refreshFullscreenView() {loadFullscreenScreenshots();}
    function stopFullscreenAutoRefresh() {
        // No per-modal timer. The shared monitoring timer refreshes both.
    }
    function getMonitorStatusClass(status) {
        return 'cctv-status-'+(['online','inactive','offline'].includes(status)?status:'offline');
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
        historyVisibleSeq++;
        if(historyVisibleTimer)clearTimeout(historyVisibleTimer);
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
        currentPerPage = parseInt($(this).val(),10);
        loadScreenshotsForSelectedMonitor(1);
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
    const selected=selectedMonitors.length>0;
    $('#selectAllMonitors').prop('hidden',!selected).attr('aria-hidden',String(!selected));
}
function loadMonitors() {
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_live_status",
        method: "GET",
        success: function(data) {
            allMonitors = Array.isArray(data) ? data.map(m=>({...m,id:Number(m.id)})) : [];
            
            // Assignment revocations are applied by the server on every
            // status request. Clear any previously selected monitor immediately
            // when it disappears from the authorized list.
            const previousSelection=selectedMonitors.join(',');
            if(selectedMonitors.length){
                selectedMonitors=selectedMonitors.filter(id=>
                    allMonitors.some(monitor=>monitor.id===id));
            }
            if(previousSelection!==selectedMonitors.join(',')){
                liveScreenshotSnapshot=[];liveScreenshotSelection='';liveScreenshotUpdatedAt=0;
                visibleShotMap.clear();fullscreenShotMap.clear();
                $('#screenshotsContainer').empty();
                $('#fullscreenContainer').empty();
                ++screenshotRequestSeq;
                if(unifiedActiveTab==='screenshots'||$('#fullscreenModal').hasClass('show')){
                    loadScreenshotsForSelectedMonitor(1);
                }
            }
            
            filteredMonitors = [...allMonitors];
            if(!unifiedInitialSelectionDone){
                unifiedInitialSelectionDone=true;
                const requested=Number(new URL(window.location.href).searchParams.get('monitor_id')||0);
                if(requested>0&&allMonitors.some(m=>m.id===requested))selectedMonitors=[requested];
            }
            updateMonitorList(filteredMonitors);
            const selected=allMonitors.find(m=>m.id===selectedMonitors[0]);
            $('#unifiedSelectionSummary').text(selected?String(selected.user_no||'—')+': '+String(selected.username||'Unassigned')+' · #'+selected.id:'All devices');
            if(window.unifiedLogs&&unifiedSyncedMonitor!==(selected?.id||0)){
                unifiedSyncedMonitor=selected?.id||0;
                window.unifiedLogs.selectMonitor(unifiedSyncedMonitor);
                if(unifiedActiveTab==='settings')unifiedLoadSettings();
            }
            updateStatusStats(selectedMonitors.length?allMonitors.filter(m=>selectedMonitors.includes(m.id)):allMonitors);
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
                            <span class="monitor-status-indicator" aria-hidden="true"></span><span>${escapeViewerText(statusText)}</span>
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

function loadScreenshots(page = 1) {
    currentPage = page;
    const request=++screenshotRequestSeq;
    
    const params = new URLSearchParams({
        page: page,
        per_page: currentPerPage
    });
    
    if (selectedMonitors.length > 0) {
        params.append('monitors', selectedMonitors.join(','));
    }
    if(window.headerStartDate)params.append('start_date',window.headerStartDate);
    if(window.headerEndDate)params.append('end_date',window.headerEndDate);
    appendLocalCalendarUtcBounds(params);
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if(request!==screenshotRequestSeq)return;
            if (response.screenshots) {
                acceptSharedScreenshotResponse(response.screenshots,page);
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            if(request!==screenshotRequestSeq)return;
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

// Shared CCTV tile markup used by both the normal grid and fullscreen mode.
function monitorScreenshotCard(item,cardClass='screenshot-card'){
    const monitor=item.monitor||{};
    const id=String(item.global_id||'');
    const thumbnail=BASE_IMAGE_URL+(item.thumbnail||item.screenshot||'');
    const full=BASE_IMAGE_URL+(item.screenshot||'');
    const live=allMonitors.find(m=>Number(m.id)===Number(monitor.id||monitor.monitor_ip_id));
    const status=['online','inactive','offline'].includes(live?.live_status)?live.live_status:'offline';
    const userNo=monitor.user_no||live?.user_no||'—';
    const name=monitor.username||live?.username||'Unassigned';
    const ip=monitor.last_seen_ip||monitor.ip_address||live?.last_seen_ip||live?.ip_address||'—';
    const stamp=new Date(Number(item.modified_time||0)*1000);
    const timestamp=Number(item.modified_time)>0 && Number.isFinite(stamp.getTime())
        ?stamp.toLocaleString(undefined,{month:'2-digit',day:'2-digit',year:'numeric',
          hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}):'Time unavailable';
    return `<div class="${escapeViewerText(cardClass)}" data-shot-id="${escapeViewerText(id)}"
                title="Open screenshot history · ${escapeViewerText(timestamp)}">
        <div class="cctv-status-indicator cctv-status-${status}" title="Status: ${status}" aria-label="${status}"></div>
        <time class="cctv-timestamp" title="Captured ${escapeViewerText(timestamp)}">${escapeViewerText(timestamp)}</time>
        <img src="${escapeViewerText(thumbnail)}" data-full-image="${escapeViewerText(full)}" alt="Screenshot for ${escapeViewerText(name)}" loading="lazy">
        <div class="cctv-screenshot-info">
            <strong class="cctv-username">${escapeViewerText(userNo)}: ${escapeViewerText(name)}</strong>
            <span class="cctv-ip">${escapeViewerText(ip)}</span>
        </div>
    </div>`;
}
function acceptSharedScreenshotResponse(screenshots,page){
    if(page!==1)return;
    // With one selected device the history API returns many captures.
    // The fullscreen live tile uses just its newest screenshot.
    liveScreenshotSnapshot=selectedMonitors.length===1?screenshots.slice(0,1):screenshots.slice();
    liveScreenshotSelection=selectedMonitors.join(',');
    liveScreenshotUpdatedAt=Date.now();
    if($('#fullscreenModal').hasClass('show'))displayFullscreenScreenshots(liveScreenshotSnapshot);
}
function displayScreenshots(screenshots,pagination=null){
    const container=$('#screenshotsContainer');
    const list=Array.isArray(screenshots)?screenshots:[];
    $('#screenshotCount').text(pagination?.total_screenshots??list.length);
    visibleShotMap=new Map();
    if(!list.length){
        container.html('<div class="empty-state"><i class="fas fa-image"></i><h5>No Screenshots Available</h5><p>No screenshots for this selection.</p></div>');
        return;
    }
    const cards=list.map(item=>{
        const m=item.monitor||{},id=String(item.global_id||'');
        visibleShotMap.set(id,{
            url:BASE_IMAGE_URL+(item.screenshot||''),
            thumbnail:BASE_IMAGE_URL+(item.thumbnail||item.screenshot||''),
            user:m.username||'Unassigned',ip:m.ip_address||'—',
            time:Number(item.modified_time)||0
        });
        return monitorScreenshotCard(item,'screenshot-card');
    }).join('');
    const paginationHtml=pagination&&pagination.total_pages>1?generatePagination(pagination):'';
    container.html('<div class="screenshot-grid">'+cards+'</div>'+paginationHtml);
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
    const request=++screenshotRequestSeq;
    
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
    appendLocalCalendarUtcBounds(params);

    console.log('Loading screenshots with params:', params.toString());
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if(request!==screenshotRequestSeq)return;
            if (response.screenshots) {
                acceptSharedScreenshotResponse(response.screenshots,page);
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            if(request!==screenshotRequestSeq)return;
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




/* Unified workspace: one monitor selection, one filter/action bar, all log tabs. */
let unifiedActiveTab='screenshots',unifiedSettingsRequest=0,unifiedInitialSelectionDone=false,unifiedSyncedMonitor=null;
const unifiedSuperAdmin = <?php echo json_encode(($_SESSION['role']??'')===ROLE_SUPER_ADMIN); ?>;
const unifiedManager = <?php echo json_encode(in_array(($_SESSION['role']??''),[ROLE_SUPER_ADMIN,ROLE_SCOPED_ADMIN],true)); ?>;
const unifiedBase = <?php echo json_encode(BASE_URL); ?>;
function unifiedSelectTab(tab,persist=true){
    if(!['screenshots','key','browser','usb','settings'].includes(tab))tab='screenshots';
    if(tab==='settings'&&!unifiedManager)tab='screenshots';
    unifiedActiveTab=tab;
    document.querySelectorAll('[data-monitor-tab]').forEach(button=>{
        const selected=button.dataset.monitorTab===tab;
        button.classList.toggle('active',selected);
        button.setAttribute('aria-selected',String(selected));
        button.tabIndex=selected?0:-1;
    });
    $('#unifiedScreenshotPanel').prop('hidden',tab!=='screenshots');
    $('#unifiedLogPanel').prop('hidden',!['key','browser','usb'].includes(tab));
    $('#unifiedSettingsPanel').prop('hidden',tab!=='settings');
    if(['key','browser','usb'].includes(tab)&&window.unifiedLogs){
        window.unifiedLogs.switchTab(tab);
    }
    if(tab==='settings')unifiedLoadSettings();
    if(tab==='screenshots')loadScreenshotsForSelectedMonitor(1);
    if(persist)unifiedUpdateUrl();
}
function unifiedUpdateUrl(){
    const url=new URL(window.location.href);
    if(unifiedActiveTab==='screenshots')url.searchParams.delete('tab');
    else url.searchParams.set('tab',unifiedActiveTab);
    if(selectedMonitors.length===1)url.searchParams.set('monitor_id',String(selectedMonitors[0]));
    else url.searchParams.delete('monitor_id');
    window.history.replaceState(null,'',url.pathname+url.search+url.hash);
}
function unifiedSelectMonitor(id,persist=true){
    selectedMonitors=Number.isInteger(id)&&id>0?[id]:[];
    updateMonitorList(filteredMonitors);
    updateStatusStats(selectedMonitors.length?allMonitors.filter(m=>selectedMonitors.includes(m.id)):allMonitors);
    const monitor=allMonitors.find(m=>m.id===id);
    $('#unifiedSelectionSummary').text(monitor?
        String(monitor.user_no||'—')+': '+String(monitor.username||'Unassigned')+' · #'+id:
        'All devices');
    unifiedSyncedMonitor=id||0;
    window.unifiedLogs?.selectMonitor(id||'');
    if(unifiedActiveTab==='screenshots')loadScreenshotsForSelectedMonitor(1);
    else if(unifiedActiveTab==='settings')unifiedLoadSettings();
    if(persist)unifiedUpdateUrl();
}
// Calendar-preserving ranges: local dates, Monday-start weeks, and inclusive days.
function unifiedPresetRange(preset,now=new Date()){
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    const offset=(days)=>{const d=new Date(today);d.setDate(d.getDate()+days);return d;};
    const weekStart=new Date(today);
    weekStart.setDate(today.getDate()-((today.getDay()+6)%7));
    let from=new Date(today),to=new Date(today);
    switch(preset){
        case 'yesterday':from=to=offset(-1);break;
        case 'this_week':from=weekStart;break;
        case 'last_week':from=new Date(weekStart);from.setDate(from.getDate()-7);
            to=new Date(weekStart);to.setDate(to.getDate()-1);break;
        case 'this_month':from=new Date(today.getFullYear(),today.getMonth(),1);break;
        case 'last_month':from=new Date(today.getFullYear(),today.getMonth()-1,1);
            to=new Date(today.getFullYear(),today.getMonth(),0);break;
        case 'last_7_days':from=offset(-6);break;
        case 'last_30_days':from=offset(-29);break;
        default:break;
    }
    return {start:unifiedFormatDay(from),end:unifiedFormatDay(to)};
}
function unifiedFormatDay(date){
    const pad=n=>String(n).padStart(2,'0');
    return date.getFullYear()+'-'+pad(date.getMonth()+1)+'-'+pad(date.getDate());
}
function unifiedApplyDates(){
    const from=$('#unifiedStartDate').val(),to=$('#unifiedEndDate').val();
    if(!from||!to||from>to){
        $('#unifiedDateDescription').text('Choose a valid start and end date.');
        return;
    }
    window.headerStartDate=from;window.headerEndDate=to;
    $('#unifiedDateDescription').text(from===to?from:from+' – '+to);
    // The embedded Log Browse API already paginates rows in MySQL.
    window.unifiedLogs?.setDates(from,to);
    if(unifiedActiveTab==='screenshots')loadScreenshotsForSelectedMonitor(1);
}
function unifiedSelectDatePreset(preset,apply=true){
    const custom=preset==='custom';
    $('#unifiedCustomRange').prop('hidden',!custom);
    if(!custom){
        const range=unifiedPresetRange(preset);
        $('#unifiedStartDate').val(range.start);
        $('#unifiedEndDate').val(range.end);
        if(apply)unifiedApplyDates();
    }else if(apply){
        $('#unifiedDateDescription').text('Choose dates, then Apply.');
    }
}
function unifiedToday(){return unifiedFormatDay(new Date());}
function unifiedField(label,input){
    return '<label class="form-label">'+label+'</label>'+input;
}
function unifiedLoadSettings(){
    const panel=$('#unifiedSettingsContent');
    const id=selectedMonitors.length===1?selectedMonitors[0]:0;
    const request=++unifiedSettingsRequest;
    if(!id){panel.text('Choose a monitor from the left list to edit its settings.');return;}
    panel.text('Loading monitor settings…');
    fetch(unifiedBase+'view/api_monitor_settings?'+new URLSearchParams({monitor_id:String(id)}),{credentials:'same-origin'})
    .then(async response=>{
        const data=await response.json();
        if(!response.ok||!data.success)throw Error(data.error||'Cannot load settings');
        return data;
    }).then(data=>{
        if(request!==unifiedSettingsRequest||selectedMonitors[0]!==id)return;
        const m=data.monitor,escape=escapeViewerText;
        const selected=(a,b)=>String(a??'')===String(b??'')?' selected':'';
        const chosen=(value)=>String(value)==='1'?' checked':'';
        const users=(data.users||[]).map(u=>'<option value="'+Number(u.id)+'"'
            +selected(m.owner_user_id,u.id)+'>'+escape((u.user_no||'—')+': '+(u.name||'')+' ('+Number(u.device_count||0)+' devices)')+'</option>').join('');
        const os=['','Windows','Mac','Ubuntu'].map(v=>'<option value="'+escape(v)+'"'+selected(m.platform||'',v)+'>'+escape(v||'Not set')+'</option>').join('');
        const types=['desktop','laptop','server'].map(v=>'<option value="'+v+'"'+selected(m.type||'desktop',v)+'>'+escape(v)+'</option>').join('');
        const locked=!!(Number(m.settings_locked)||Number(m.assignment_locked));
        const targets=(data.merge_targets||[]).map(t=>'<option value="'+Number(t.id)+'">'
            +escape((t.platform||'—')+' · '+(t.type||'Desktop')+' · '+(t.last_seen_ip||t.ip_address||'—')+' #'+Number(t.id))+'</option>').join('');
        panel.html('<div class="row g-2">'
            +'<div class="col-12"><div class="d-flex align-items-center gap-2 justify-content-between"><h6 class="mb-0">Monitor settings · #'+id+'</h6><span class="small text-muted">'
            +escape((m.user_no||'—')+': '+(m.username||'Unassigned'))+'</span></div></div>'
            +'<div class="col-xl-8"><div class="card"><div class="card-body p-2">'
            +'<form method="post" action="'+unifiedBase+'monitors/edit/'+id+'" id="unifiedMonitorSettingsForm">'
            +'<input type="hidden" name="return_to" value="monitoring">'
            +'<div class="row g-2">'
            +'<div class="col-12">'+unifiedField('Assigned user',
                (data.can_reassign?'':'<input type="hidden" name="owner_user_id" value="'+Number(m.owner_user_id)+'">')+
                '<select class="form-select" name="owner_user_id"'+(data.can_reassign?'':' disabled')+'><option value="">Unassigned</option>'+users+'</select>')+'</div>'
            +'<div class="col-sm-6">'+unifiedField('OS','<select class="form-select" name="platform">'+os+'</select>')+'</div>'
            +'<div class="col-sm-6">'+unifiedField('Machine','<select class="form-select" name="type">'+types+'</select>')+'</div>'
            +'<div class="col-12">'+unifiedField('Remark','<textarea class="form-control" maxlength="500" name="remark" rows="2">'+escape(m.remark||'')+'</textarea>')+'</div>'
            +'<div class="col-12">'+unifiedField('Slack ID','<input class="form-control" maxlength="255" name="slack_id" value="'+escape(m.slack_id||'')+'">')+'</div>'
            +'<div class="col-12"><label><input type="checkbox" name="settings_locked" value="1"'+(locked?' checked':'')+'> Lock monitor settings and assignment</label></div>'
            +'<div class="col-12 text-end"><button type="submit" class="btn btn-sm btn-primary">Save changes</button></div>'
            +'</div></form></div></div>'
            +(data.can_merge&&unifiedSuperAdmin?'<div class="card mt-2"><div class="card-body p-2"><strong>Merge this monitor into another</strong>'
            +'<p class="small text-muted mb-2">Only monitors assigned to the same user are eligible. The source is deleted only when every log and statistics record has been transferred.</p>'
            +'<div class="d-flex flex-wrap gap-2"><select id="unifiedMergeTarget" class="form-select form-select-sm flex-fill"><option value="">Select target monitor</option>'+targets+'</select>'
            +'<button id="unifiedMergeButton" class="btn btn-sm btn-outline-danger" type="button"'+(targets?'':' disabled')+'>Merge into target</button></div>'
            +'<div class="small mt-2" id="unifiedMergeResult" role="status" aria-live="polite"></div>'
            +'</div></div>':'')+'</div>'
            +'<div class="col-xl-4"><div class="card"><div class="card-body p-2">'
            +'<strong>Device information</strong><dl class="small mt-2 mb-0">'
            +'<dt>Monitor ID</dt><dd>#'+id+'</dd>'
            +'<dt>User No</dt><dd>'+escape(m.user_no||'—')+'</dd>'
            +'<dt>Configured IP</dt><dd>'+escape(m.ip_address||'—')+'</dd>'
            +'<dt>Observed IP</dt><dd>'+escape(m.last_seen_ip||'—')+'</dd>'
            +'<dt>MAC</dt><dd>'+escape(m.mac_address||'—')+'</dd>'
            +'<dt>Last activity</dt><dd>'+escape(m.last_monitor_tic||'Never')+'</dd>'
            +'<dt>Created</dt><dd>'+escape(m.created_at||'—')+'</dd>'
            +'<dt>Updated</dt><dd>'+escape(m.updated_at||'—')+'</dd>'
            +'</dl></div></div></div></div>');
    }).catch(error=>{if(request===unifiedSettingsRequest)panel.text('Cannot load monitor settings: '+error.message);});
}
$(document).on('click','#unifiedMergeButton',async function(){
    if(!unifiedSuperAdmin)return;
    const source=selectedMonitors.length===1?selectedMonitors[0]:0;
    const target=Number($('#unifiedMergeTarget').val());
    if(!source||!target||source===target)return;
    if(!confirm('Merge monitor #'+source+' into #'+target+'? This moves records and may remove the source monitor. This cannot be undone.'))return;
    const button=$(this).prop('disabled',true);
    const status=$('#unifiedMergeResult').text('Merging records…');
    try{
        const body=new URLSearchParams({source_id:String(source),target_id:String(target)});
        const response=await fetch(unifiedBase+'merge/api_merge',{method:'POST',credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
        const data=await response.json();
        if(!response.ok||!data.success)throw Error(data.error||'Merge failed');
        if(data.partial)status.text('Merge partially completed: '+(data.errors||[]).join('; '));
        else status.text('Merge complete. Source '+(data.source_deleted?'removed.':'retained.'));
        loadMonitors();
        if(data.source_deleted)unifiedSelectMonitor(target);
        else if(!data.partial)unifiedLoadSettings();
    }catch(error){status.text('Merge failed: '+error.message);}
    finally{button.prop('disabled',false);}
});
$(document).on('click','[data-monitor-tab]',function(){unifiedSelectTab(this.dataset.monitorTab);});
$(document).on('keydown','[data-monitor-tab]',function(event){
    if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
    event.preventDefault();
    const buttons=[...document.querySelectorAll('[data-monitor-tab]')];
    const current=buttons.indexOf(this);
    const next=event.key==='Home'?0:event.key==='End'?buttons.length-1:
        (current+(event.key==='ArrowRight'?1:-1)+buttons.length)%buttons.length;
    unifiedSelectTab(buttons[next].dataset.monitorTab);
    buttons[next].focus();
});
$(document).on('change','#unifiedDatePreset',function(){unifiedSelectDatePreset(this.value);});
$(document).on('click','#unifiedApplyFilters',unifiedApplyDates);
$(document).on('click','#unifiedClearFilters',function(){
    $('#unifiedDatePreset').val('today');
    unifiedSelectDatePreset('today');
});
$(document).on('click','#unifiedRemoveScreenshots',function(){
    if(window.unifiedLogs)window.unifiedLogs.removeScreenshots();
});
$(document).on('click','#refreshLiveNow',function(){window.unifiedLogs?.refresh();});
window.reloadUnifiedScreenshots = ()=>loadScreenshotsForSelectedMonitor(1);
$(document).ready(function(){
    const url=new URL(window.location.href);
    const requestedTab=url.searchParams.get('tab')||'screenshots';
    unifiedSelectDatePreset('today',false);
    window.headerStartDate=unifiedToday();window.headerEndDate=unifiedToday();
    $('#unifiedDateDescription').text(unifiedToday());
    unifiedSelectTab(requestedTab,false);
});

const historyBase = <?php echo json_encode(BASE_URL); ?>;
const HISTORY_SCALES = {near:55,medium:14,wide:2.5,day:0.5};
let historyState=null,historySeq=0,historyKeySeq=0,historyVisibleSeq=0,historyVisibleTimer=null;

function historyMillis(value){
    if(typeof value==='number')return value>100000000000?value:value*1000;
    const raw=String(value||'');
    const match=raw.match(/^(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d):(\d\d)$/);
    // A bare DATETIME from MySQL is UTC, not a browser-local timestamp.
    if(match)return Date.UTC(+match[1],+match[2]-1,+match[3],+match[4],+match[5],+match[6]);
    const absolute=Date.parse(raw);
    return Number.isFinite(absolute)?absolute:NaN;
}
function historyUtcClock(ms){
    return new Date(ms).toISOString().slice(0,19).replace('T',' ');
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
        rangeStart:ms-900000,rangeEnd:ms+900000,
        scale:$('#historyScale').val()||'medium',pxPerMinute:HISTORY_SCALES[$('#historyScale').val()]||14,width:0,
        keys:[],shots:[],drag:null,activeId:id,activeTime:ms,cursorTime:ms,
        focus:{global_id:id,ms,full:fullUrl,thumb:thumbnailUrl||fullUrl}
    };
    $('#historyTranscriptInfo').text('');
    $('#historyJumpToKeyboard').prop('hidden',true);
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
    current.activeId=id;current.activeTime=ms;current.cursorTime=ms;
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
        historySeek((state.rangeStart+state.rangeEnd)/2,false);
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
    $('#historyPlayheadTime').text(historyClock(state.cursorTime));
    $('#historyPrevRange').prop('disabled',state.rangeStart<=state.start);
    $('#historyNextRange').prop('disabled',state.rangeEnd>=state.end);
}
function historyRenderRuler(center=false){
    const state=historyState,viewport=document.getElementById('historyTimelineViewport');
    const ruler=document.getElementById('historyTimelineRuler');
    if(!state||!viewport||!ruler)return;
    const span=state.end-state.start,minutes=span/60000;
    const width=Math.min(240000,Math.max(viewport.clientWidth||800,Math.round(minutes*state.pxPerMinute)));
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
    const playhead=Number.isFinite(state.cursorTime)?
        '<button type="button" class="key-cctv-playhead" style="left:'+x(state.cursorTime)+'px" title="Drag to scrub" aria-label="Screenshot playhead: '+escapeViewerText(historyClock(state.cursorTime,true))+'"></button>':'';
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
    $('#historyFrameTime').text('Captured '+historyClock(shot.ms,true));
    const ruler=document.getElementById('historyTimelineRuler');
    if(ruler){
        ruler.querySelectorAll('[data-history-shot]').forEach(node=>
            node.classList.toggle('is-active',node.dataset.historyShot===shot.global_id));
        const playhead=ruler.querySelector('.key-cctv-playhead');
        if(playhead&&state.width)playhead.style.left=(state.cursorTime-state.start)/(state.end-state.start)*state.width+'px';
    }
}
function historySeek(time,load=true){
    const state=historyState;if(!state||!Number.isFinite(time))return;
    state.cursorTime=MonitorCctvRange.clamp(Math.round(time/1000)*1000,state.start,state.end);
    const duration=state.rangeEnd-state.rangeStart;
    if(state.cursorTime<state.rangeStart||state.cursorTime>state.rangeEnd){
        const from=MonitorCctvRange.clamp(state.cursorTime-duration/2,state.start,state.end-duration);
        state.rangeStart=from;state.rangeEnd=from+duration;
    }
    const shots=historyAllShots(state);
    if(shots.length){
        const nearest=shots.reduce((best,shot)=>Math.abs(shot.ms-state.cursorTime)<Math.abs(best.ms-state.cursorTime)?shot:best,shots[0]);
        historyShowShot(nearest);
    }
    historyUpdateVisual();
    const ruler=document.getElementById('historyTimelineRuler');
    const head=ruler?.querySelector('.key-cctv-playhead');
    if(head&&state.width){
        head.style.left=(state.cursorTime-state.start)/(state.end-state.start)*state.width+'px';
        head.setAttribute('aria-label','Screenshot playhead: '+historyClock(state.cursorTime,true));
    }
    if(load)historyLoadTranscript();
}
async function historyLoadDomain(){
    const state=historyState;if(!state)return;
    const seq=++historySeq,from=historyUtcClock(state.start),to=historyUtcClock(state.end);
    $('#historyTimelineStatus').text('Loading screenshot and keyboard activity…');
    const shotsParams=new URLSearchParams({monitor_id:String(state.monitor),from,to});
    const keysParams=new URLSearchParams({device_id:String(state.monitor),
        start_date:historyClock(state.start,true).slice(0,10),
        end_date:historyClock(state.end,true).slice(0,10),
        start_utc:new Date(state.start).toISOString(),
        end_utc:new Date(state.end).toISOString()});
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
    historySeek(state.cursorTime,false);
    historyLoadTranscript();
    historyScheduleVisibleShots();
}

function historyScheduleVisibleShots(){
    if(historyVisibleTimer)clearTimeout(historyVisibleTimer);
    historyVisibleTimer=setTimeout(historyLoadVisibleShots,180);
}
async function historyLoadVisibleShots(){
    const state=historyState,viewport=document.getElementById('historyTimelineViewport');
    if(!state||!viewport||!state.width||!viewport.clientWidth||!$('#screenshotModal').hasClass('show'))return;
    const span=state.end-state.start;
    const left=Math.max(0,viewport.scrollLeft-viewport.clientWidth*.2);
    const right=Math.min(state.width,viewport.scrollLeft+viewport.clientWidth*1.2);
    const from=state.start+left/state.width*span;
    const to=state.start+right/state.width*span;
    if(to<=from)return;
    const key=String(state.monitor)+'|'+historyUtcClock(from)+'|'+historyUtcClock(to);
    if(state.visibleCacheKey===key)return;
    const sequence=++historyVisibleSeq;
    const params=new URLSearchParams({monitor_id:String(state.monitor),from:historyUtcClock(from),to:historyUtcClock(to)});
    try{
        const response=await fetch(historyBase+'view/api_screenshot_timeline?'+params,{credentials:'same-origin'});
        if(!response.ok)throw new Error('HTTP '+response.status);
        const result=await response.json();
        if(sequence!==historyVisibleSeq||historyState!==state||!result.success)return;
        const incoming=(result.screenshots||[]).map(shot=>({
            global_id:String(shot.global_id||''),ms:historyMillis(shot.capture_datetime),
            full:historyImageUrl(shot.screenshot),thumb:historyImageUrl(shot.thumbnail||shot.screenshot)
        })).filter(shot=>shot.global_id&&Number.isFinite(shot.ms));
        const combined=new Map(state.shots.map(shot=>[shot.global_id,shot]));
        incoming.forEach(shot=>combined.set(shot.global_id,shot));
        // Retain surrounding samples without growing indefinitely during long scrubbing sessions.
        const center=(from+to)/2;
        state.shots=[...combined.values()].sort((a,b)=>
            Math.abs(a.ms-center)-Math.abs(b.ms-center)).slice(0,480);
        state.visibleCacheKey=key;
        historyRenderRuler(false);
        historySeek(state.cursorTime,false);
    }catch(error){
        if(sequence===historyVisibleSeq)$('#historyTimelineStatus').text('Visible screenshot samples unavailable: '+error.message);
    }
}

function historyNearestKeyboardBucket(){
    const state=historyState;if(!state||!state.keys?.length)return null;
    const center=(state.rangeStart+state.rangeEnd)/2;
    return state.keys.reduce((nearest,item)=>
        Math.abs(item.ms-center)<Math.abs(nearest.ms-center)?item:nearest,state.keys[0]);
}
async function historyLoadTranscript(){
    const state=historyState;if(!state)return;
    const seq=++historyKeySeq;
    const panel=$('#historyTranscriptBody').text('Loading recorded keys…');
    const jump=$('#historyJumpToKeyboard').prop('hidden',true);
    $('#historyTranscriptInfo').text('');
    const from=historyUtcClock(state.rangeStart),to=historyUtcClock(state.rangeEnd);
    const params=new URLSearchParams({device_id:String(state.monitor),from,to});
    try{
        const response=await fetch(historyBase+'logbrowse/api_key_context?'+params,{credentials:'same-origin'});
        if(!response.ok)throw new Error('HTTP '+response.status);
        const data=await response.json();
        if(!data.success)throw new Error(data.error||'Unable to load keylogs');
        if(state!==historyState||seq!==historyKeySeq)return;
        const keys=Array.isArray(data.logs)?data.logs:[];
        $('#historyTranscriptInfo').text(keys.length+' recorded key events'+(data.limited?' · showing first 2,000':''));
        if(keys.length){
            if(typeof monitorTranscriptKeyRows!=='function')throw Error('Transcript renderer failed to load');
            panel.html(monitorTranscriptKeyRows(keys,escapeViewerText));
        }else{
            panel.html('<div class="text-muted p-2">No keyboard events were recorded within this selected range. Drag or resize the selection on the timeline to inspect another period.</div>');
            const nearest=historyNearestKeyboardBucket();
            if(nearest){
                jump.prop('hidden',false).attr('title','Nearest activity: '+historyClock(nearest.ms,true));
                $('#historyTranscriptInfo').text('No keys in selected range');
            }
        }
    }catch(error){
        if(state===historyState&&seq===historyKeySeq){
            $('#historyTranscriptInfo').text('Transcript unavailable');
            panel.text('Could not load keyboard transcript: '+error.message);
        }
    }
}
$(document).on('click','#historyJumpToKeyboard',function(){
    const state=historyState,nearest=historyNearestKeyboardBucket();
    if(!state||!nearest)return;
    const duration=state.rangeEnd-state.rangeStart;
    const from=MonitorCctvRange.clamp(nearest.ms-duration/2,state.start,state.end-duration);
    historySetRange(from,from+duration,true);
    historySeek(nearest.ms,false);
    historyCenterView();
});
function historyPointerTime(event,ruler){
    return MonitorCctvRange.point(event,ruler,historyState);
}
function historyHitMode(state,time,ruler,playhead=false){
    return MonitorCctvRange.hit(state,time,ruler.getBoundingClientRect().width,{
        seek:true,cursor:state.cursorTime,playhead
    });
}
document.addEventListener('pointerdown',event=>{
    const ruler=event.target.closest?.('#historyTimelineRuler');
    const state=historyState;
    if(!ruler||!state||event.button!==0||event.target.closest?.('[data-history-shot]'))return;
    const time=historyPointerTime(event,ruler);
    const mode=historyHitMode(state,time,ruler,!!event.target.closest('.key-cctv-playhead'));
    if(mode==='seek')historySeek(time,false);
    state.drag={pointerId:event.pointerId,mode,anchor:time,from:state.rangeStart,to:state.rangeEnd};
    ruler.setPointerCapture(event.pointerId);
    ruler.classList.add('is-dragging');
    event.preventDefault();
});
document.addEventListener('pointermove',event=>{
    const ruler=document.getElementById('historyTimelineRuler'),state=historyState;
    if(!ruler||!state)return;
    const time=historyPointerTime(event,ruler);
    if(!state.drag){
        const mode=historyHitMode(state,time,ruler,!!event.target.closest?.('.key-cctv-playhead'));
        ruler.style.cursor=mode==='pan'?'grab':mode==='seek'?'crosshair':'ew-resize';
        return;
    }
    if(state.drag.pointerId!==event.pointerId||!ruler.hasPointerCapture(event.pointerId))return;
    const drag=state.drag;
    if(drag.mode==='seek')historySeek(time,false);
    else{
        const [from,to]=MonitorCctvRange.drag(state,drag.mode,drag.from,drag.to,drag.anchor,time);
        historySetRange(from,to,false);
    }
});
function historyFinishDrag(event,commit){
    const state=historyState,ruler=document.getElementById('historyTimelineRuler');
    if(!state?.drag||!ruler||state.drag.pointerId!==event.pointerId)return;
    const mode=state.drag.mode;
    state.drag=null;ruler.classList.remove('is-dragging');
    if(ruler.hasPointerCapture(event.pointerId))ruler.releasePointerCapture(event.pointerId);
    if(commit){
        historySeek(mode==='seek'?state.cursorTime:(state.rangeStart+state.rangeEnd)/2,true);
        historyScheduleVisibleShots();
    }
}
document.addEventListener('pointerup',event=>historyFinishDrag(event,true));
document.addEventListener('pointercancel',event=>historyFinishDrag(event,false));
$(document).on('click','#historyTimelineRuler [data-history-shot]',function(){
    const state=historyState;if(!state)return;
    const shot=historyAllShots(state).find(item=>item.global_id===this.dataset.historyShot);
    if(!shot)return;
    historySeek(shot.ms,true);
    historyCenterView();
});
$(document).on('change','#historyFrom,#historyTo',function(){
    const state=historyState;if(!state)return;
    const from=new Date($('#historyFrom').val()).getTime();
    const to=new Date($('#historyTo').val()).getTime();
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

function historyZoom(factor,clientX=null){
    const state=historyState,viewport=document.getElementById('historyTimelineViewport');
    if(!state||!viewport)return;
    const rectangle=viewport.getBoundingClientRect();
    const anchorX=clientX===null?viewport.clientWidth/2:MonitorCctvRange.clamp(clientX-rectangle.left,0,viewport.clientWidth);
    const anchorTime=state.start+(viewport.scrollLeft+anchorX)/Math.max(1,state.width)*(state.end-state.start);
    state.pxPerMinute=MonitorCctvRange.clamp(state.pxPerMinute*factor,0.3,160);
    $('#historyScale').val('custom');
    historyRenderRuler(false);
    viewport.scrollLeft=MonitorCctvRange.clamp(
        (anchorTime-state.start)/(state.end-state.start)*state.width-anchorX,
        0,Math.max(0,state.width-viewport.clientWidth));
    historyScheduleVisibleShots();
}
function historyFitDay(){
    const state=historyState,viewport=document.getElementById('historyTimelineViewport');
    if(!state||!viewport)return;
    state.scale='day';
    state.pxPerMinute=Math.max(0.3,viewport.clientWidth/((state.end-state.start)/60000));
    $('#historyScale').val('day');
    historyRenderRuler(true);
    historyScheduleVisibleShots();
}
$(document).on('change','#historyScale',function(){
    if(!historyState||!HISTORY_SCALES[this.value])return;
    historyState.scale=this.value;
    historyState.pxPerMinute=HISTORY_SCALES[this.value];
    historyRenderRuler(true);
    historyScheduleVisibleShots();
});
$(document).on('click','#historyZoomIn',()=>historyZoom(1.6));
$(document).on('click','#historyZoomOut',()=>historyZoom(1/1.6));
$(document).on('click','#historyFit',historyFitDay);
document.addEventListener('wheel',event=>{
    const viewport=event.target.closest?.('#historyTimelineViewport');
    if(!viewport||!historyState||!$('#screenshotModal').hasClass('show'))return;
    event.preventDefault();
    if(event.shiftKey){
        viewport.scrollLeft+=event.deltaY||event.deltaX;
        historyScheduleVisibleShots();
    }else historyZoom(event.deltaY<0?1.22:1/1.22,event.clientX);
},{passive:false});
$('#historyTimelineViewport').on('scroll',historyScheduleVisibleShots);
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
    $(this).attr('aria-expanded',String(!collapsed))
        .attr('title',collapsed?'Show keyboard transcript':'Hide keyboard transcript');
    historyRenderRuler(false);
});

document.addEventListener('keydown',event=>{
    if(event.target?.id!=='historyTimelineRuler'||!historyState)return;
    const state=historyState;
    if(['+','=','Add'].includes(event.key)){event.preventDefault();historyZoom(1.5);return;}
    if(['-','Subtract'].includes(event.key)){event.preventDefault();historyZoom(1/1.5);return;}
    if(event.key==='Home'){event.preventDefault();historySeek(state.start,true);historyCenterView();return;}
    if(event.key==='End'){event.preventDefault();historySeek(state.end,true);historyCenterView();return;}
    if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;
    event.preventDefault();
    const dir=event.key==='ArrowRight'?1:-1;
    if(event.ctrlKey||event.metaKey)historySetRange(state.rangeStart,Math.max(state.rangeStart+1000,Math.min(state.end,state.rangeEnd+dir*60000)),true);
    else if(event.shiftKey){
        const duration=state.rangeEnd-state.rangeStart;
        const from=MonitorCctvRange.clamp(state.rangeStart+dir*duration,state.start,state.end-duration);
        historySetRange(from,from+duration,true);historyCenterView();
    }else{
        historySeek(state.cursorTime+dir*(event.altKey?1000:60000),true);
        historyCenterView();
    }
});
$(document).on('shown.bs.modal','#screenshotModal',()=>{
    historyRenderRuler(true);
    historyScheduleVisibleShots();
});
$(window).on('resize',()=>{
    if(historyState&&$('#screenshotModal').hasClass('show')){
        historyRenderRuler(false);historyScheduleVisibleShots();
    }
});

</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 