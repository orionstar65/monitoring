<?php
$title = 'Merge Logs - ' . APP_NAME;
$active_page = 'merge';
$page_title = 'Merge Logs';
ob_start();
?>
<style>
.merge-container {
    max-width: 1400px;
    margin: 0 auto;
}

.merge-cards {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 2rem;
    align-items: start;
    margin-bottom: 2rem;
}

.merge-card {
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 2rem;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.merge-card.selected {
    border-color: #1976d2;
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.2);
}

.merge-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e0e0e0;
}

.merge-card-header h4 {
    margin: 0;
    font-weight: 600;
    color: #212121;
}

.merge-card-header .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
}

.merge-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #757575;
    margin-top: 3rem;
}

.merge-button-container {
    display: flex;
    justify-content: center;
    margin: 2rem 0;
}

.merge-button {
    padding: 1rem 3rem;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
}

.merge-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.monitor-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    font-size: 1rem;
    margin-bottom: 1.5rem;
    transition: border-color 0.2s ease;
}

.monitor-select:focus {
    border-color: #1976d2;
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(25, 118, 210, 0.25);
}

.monitor-details {
    background: #f8f9fa;
    border-radius: 0.375rem;
    padding: 1.5rem;
    margin-top: 1rem;
}

.monitor-details.hidden {
    display: none;
}

.monitor-details-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e0e0e0;
}

.monitor-details-item:last-child {
    border-bottom: none;
}

.monitor-details-label {
    font-weight: 600;
    color: #757575;
    font-size: 0.9rem;
}

.monitor-details-value {
    color: #212121;
    font-size: 0.9rem;
    text-align: right;
}

.alert-container {
    margin-top: 2rem;
}

.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #1976d2;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 0.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .merge-cards {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .merge-arrow {
        transform: rotate(90deg);
        margin: 0;
    }
}
</style>

<div class="merge-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Merge Logs</h3>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Instructions:</strong> Select a source monitor (left) and target monitor (right) to merge logs. 
        All logs, files, and database records from the source will be merged into the target, and the source monitor will be removed.
    </div>
    
    <div class="merge-cards">
        <!-- Source Card -->
        <div class="merge-card" id="source-card">
            <div class="merge-card-header">
                <h4><i class="fas fa-arrow-left me-2"></i>Source Monitor</h4>
                <span class="badge bg-secondary" id="source-badge">Not Selected</span>
            </div>
            
            <select class="monitor-select" id="source-select">
                <option value="">-- Select Source Monitor --</option>
            </select>
            
            <div class="monitor-details hidden" id="source-details">
                <div class="monitor-details-item">
                    <span class="monitor-details-label">IP Address:</span>
                    <span class="monitor-details-value" id="source-ip">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">Username:</span>
                    <span class="monitor-details-value" id="source-username">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">MAC Address:</span>
                    <span class="monitor-details-value" id="source-mac">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">Status:</span>
                    <span class="monitor-details-value" id="source-status">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">Last Activity:</span>
                    <span class="monitor-details-value" id="source-activity">-</span>
                </div>
            </div>
        </div>
        
        <!-- Arrow -->
        <div class="merge-arrow">
            <i class="fas fa-arrow-right"></i>
        </div>
        
        <!-- Target Card -->
        <div class="merge-card" id="target-card">
            <div class="merge-card-header">
                <h4><i class="fas fa-arrow-right me-2"></i>Target Monitor</h4>
                <span class="badge bg-secondary" id="target-badge">Not Selected</span>
            </div>
            
            <select class="monitor-select" id="target-select">
                <option value="">-- Select Target Monitor --</option>
            </select>
            
            <div class="monitor-details hidden" id="target-details">
                <div class="monitor-details-item">
                    <span class="monitor-details-label">IP Address:</span>
                    <span class="monitor-details-value" id="target-ip">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">Username:</span>
                    <span class="monitor-details-value" id="target-username">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">MAC Address:</span>
                    <span class="monitor-details-value" id="target-mac">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">Status:</span>
                    <span class="monitor-details-value" id="target-status">-</span>
                </div>
                <div class="monitor-details-item">
                    <span class="monitor-details-label">Last Activity:</span>
                    <span class="monitor-details-value" id="target-activity">-</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Merge Button -->
    <div class="merge-button-container">
        <button class="btn btn-primary btn-lg merge-button" id="merge-btn" disabled>
            <i class="fas fa-code-branch me-2"></i>Merge Logs
        </button>
    </div>
    
    <!-- Fix Filenames Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h5><i class="fas fa-wrench me-2"></i>Fix Mismatched Filenames</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Fix files that have wrong IP addresses in their filenames (e.g., files with IP 52, 98, or 215 in IP 59 folder).</p>
            <div class="mb-3">
                <label for="fix-monitor-select" class="form-label">Select Monitor to Fix:</label>
                <select class="form-select" id="fix-monitor-select">
                    <option value="">-- Select Monitor --</option>
                </select>
            </div>
            <button class="btn btn-warning" id="fix-filenames-btn" disabled>
                <i class="fas fa-tools me-2"></i>Fix Filenames
            </button>
            <div id="fix-results" class="mt-3"></div>
        </div>
    </div>
    
    <!-- Alert Container -->
    <div class="alert-container" id="alert-container"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sourceSelect = document.getElementById('source-select');
    const targetSelect = document.getElementById('target-select');
    const sourceCard = document.getElementById('source-card');
    const targetCard = document.getElementById('target-card');
    const mergeBtn = document.getElementById('merge-btn');
    const alertContainer = document.getElementById('alert-container');
    
    let monitors = [];
    let sourceMonitor = null;
    let targetMonitor = null;
    
    // Load monitors list
    function loadMonitors() {
        fetch('<?php echo BASE_URL; ?>merge/api_list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    monitors = window.sortMonitorOptions(data.monitors);
                    populateSelects();
                } else {
                    showAlert('Failed to load monitors', 'danger');
                }
            })
            .catch(error => {
                console.error('Error loading monitors:', error);
                showAlert('Error loading monitors: ' + error.message, 'danger');
            });
    }
    
    function formatMergeMonitorLabel(monitor) {
        const number = monitor.user_no || '—';
        const name = monitor.username || 'Unassigned';
        const ip = monitor.last_seen_ip || monitor.ip_address || '—';
        const os = monitor.platform || 'Unknown OS';
        const machine = monitor.type || 'Device';
        return `${number}(${name}): ${ip} : ${os}-${machine} #${Number(monitor.id)}`;
    }

    function populateSelects() {
        // Clear existing options (except first)
        sourceSelect.innerHTML = '<option value="">-- Select Source Monitor --</option>';
        targetSelect.innerHTML = '<option value="">-- Select Target Monitor --</option>';
        
        monitors.forEach(monitor => {
            const sourceOption = document.createElement('option');
            sourceOption.value = monitor.id;
            sourceOption.textContent = formatMergeMonitorLabel(monitor);
            sourceSelect.appendChild(sourceOption);
            

        });
    }
    
    function populateTargetOptions(source) {
        const previous=Number(targetSelect.value||0);
        targetSelect.innerHTML = '<option value="">-- Select Target Monitor --</option>';
        if(source && Number(source.owner_user_id)>0){
            monitors.filter(m=>Number(m.owner_user_id)===Number(source.owner_user_id)
                &&Number(m.id)!==Number(source.id)).forEach(m=>{
                    const option=document.createElement('option');
                    option.value=m.id;
                    option.textContent=formatMergeMonitorLabel(m);
                    targetSelect.appendChild(option);
                });
        }
        if([...targetSelect.options].some(option=>Number(option.value)===previous)){
            targetSelect.value=String(previous);
        }else updateTargetDetails(null);
    }

    function updateSourceDetails(monitor) {
        if (!monitor) {
            document.getElementById('source-details').classList.add('hidden');
            document.getElementById('source-badge').textContent = 'Not Selected';
            document.getElementById('source-badge').className = 'badge bg-secondary';
            sourceCard.classList.remove('selected');
            sourceMonitor = null;
            return;
        }
        
        sourceMonitor = monitor;
        document.getElementById('source-ip').textContent = monitor.ip_address || '-';
        document.getElementById('source-username').textContent = monitor.username || 'No username';
        document.getElementById('source-mac').textContent = monitor.mac_address || 'No MAC address';
        
        const statusBadge = `<span class="badge bg-${monitor.status_class}">${monitor.status_text}</span>`;
        document.getElementById('source-status').innerHTML = statusBadge;
        
        const activityDate = monitor.updated_at ? new Date(monitor.updated_at).toLocaleString() : 'Never';
        document.getElementById('source-activity').textContent = activityDate;
        
        document.getElementById('source-details').classList.remove('hidden');
        document.getElementById('source-badge').textContent = monitor.ip_address;
        document.getElementById('source-badge').className = 'badge bg-primary';
        sourceCard.classList.add('selected');
        
        checkMergeButton();
    }
    
    function updateTargetDetails(monitor) {
        if (!monitor) {
            document.getElementById('target-details').classList.add('hidden');
            document.getElementById('target-badge').textContent = 'Not Selected';
            document.getElementById('target-badge').className = 'badge bg-secondary';
            targetCard.classList.remove('selected');
            targetMonitor = null;
            return;
        }
        
        targetMonitor = monitor;
        document.getElementById('target-ip').textContent = monitor.ip_address || '-';
        document.getElementById('target-username').textContent = monitor.username || 'No username';
        document.getElementById('target-mac').textContent = monitor.mac_address || 'No MAC address';
        
        const statusBadge = `<span class="badge bg-${monitor.status_class}">${monitor.status_text}</span>`;
        document.getElementById('target-status').innerHTML = statusBadge;
        
        const activityDate = monitor.updated_at ? new Date(monitor.updated_at).toLocaleString() : 'Never';
        document.getElementById('target-activity').textContent = activityDate;
        
        document.getElementById('target-details').classList.remove('hidden');
        document.getElementById('target-badge').textContent = monitor.ip_address;
        document.getElementById('target-badge').className = 'badge bg-success';
        targetCard.classList.add('selected');
        
        checkMergeButton();
    }
    
    function checkMergeButton() {
        if (sourceMonitor && targetMonitor && sourceMonitor.id !== targetMonitor.id &&
            Number(sourceMonitor.owner_user_id)>0 &&
            Number(sourceMonitor.owner_user_id)===Number(targetMonitor.owner_user_id)) {
            mergeBtn.disabled = false;
        } else {
            mergeBtn.disabled = true;
        }
    }
    
    function showAlert(message, type = 'info') {
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }
    
    function clearAlert() {
        alertContainer.innerHTML = '';
    }
    
    // Event listeners
    sourceSelect.addEventListener('change', function() {
        const monitorId = parseInt(this.value);
        if (monitorId) {
            const monitor = monitors.find(m => m.id === monitorId);
            updateSourceDetails(monitor);
        } else {
            updateSourceDetails(null);
        }
    });
    
    targetSelect.addEventListener('change', function() {
        const monitorId = parseInt(this.value);
        if (monitorId) {
            const monitor = monitors.find(m => m.id === monitorId);
            updateTargetDetails(monitor);
        } else {
            updateTargetDetails(null);
        }
    });
    
    mergeBtn.addEventListener('click', function() {
        if (!sourceMonitor || !targetMonitor || sourceMonitor.id === targetMonitor.id) {
            showAlert('Please select different source and target monitors', 'warning');
            return;
        }
        
        if (!confirm(`Are you sure you want to merge logs from ${sourceMonitor.ip_address} into ${targetMonitor.ip_address}?\n\nThis will:\n- Continue across log tables even if some rows fail\n- Preserve original screenshot files and paths\n- Retain the source monitor if records remain\n\nReview warnings afterward; merged records cannot be automatically undone.`)) {
            return;
        }
        
        // Disable button and show loading
        mergeBtn.disabled = true;
        mergeBtn.innerHTML = '<span class="loading-spinner"></span>Merging...';
        clearAlert();
        
        // Perform merge
        const formData = new FormData();
        formData.append('source_id', sourceMonitor.id);
        formData.append('target_id', targetMonitor.id);
        
        fetch('<?php echo BASE_URL; ?>merge/api_merge', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats=data.stats||{};
                const errors=Array.isArray(data.errors)?data.errors:[];
                const escapeText=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
                const warnings=errors.length
                    ? '<hr><strong>Warnings ('+errors.length+'):</strong><ul>'+
                        errors.map(error=>'<li>'+escapeText(error)+'</li>').join('')+'</ul>'
                    : '';
                showAlert(
                    '<strong>'+escapeText(data.message||'Merge completed')+'</strong><br>'+
                    'Browser History: '+(stats.browser_history_logs||0)+'<br>'+
                    'Key Logs: '+(stats.key_logs||0)+'<br>'+
                    'USB Logs: '+(stats.usb_device_logs||0)+'<br>'+
                    'Screenshots: '+(stats.screenshot_metadata||0)+'<br>'+
                    'Storage Statistics: '+(stats.storage_statistics||0)+'<br>'+
                    'Source monitor: '+(data.source_deleted?'Removed':'Preserved')+'<br>'+
                    'Original image/log files: Preserved in their current locations.'+
                    warnings,
                    errors.length?'warning':'success'
                );

                // Reset button to normal state
                mergeBtn.disabled = false;
                mergeBtn.innerHTML = '<i class="fas fa-code-branch me-2"></i>Merge Logs';
                
                // Reload monitors list
                setTimeout(() => {
                    loadMonitors();
                    sourceSelect.value = '';
                    targetSelect.value = '';
                    updateSourceDetails(null);
                    updateTargetDetails(null);
                }, 2000);
            } else {
                showAlert('Merge failed: ' + (data.error || 'Unknown error'), 'danger');
                mergeBtn.disabled = false;
                mergeBtn.innerHTML = '<i class="fas fa-code-branch me-2"></i>Merge Logs';
            }
        })
        .catch(error => {
            console.error('Error merging logs:', error);
            showAlert('Error merging logs: ' + error.message, 'danger');
            mergeBtn.disabled = false;
            mergeBtn.innerHTML = '<i class="fas fa-code-branch me-2"></i>Merge Logs';
        });
    });
    
    // Fix filenames functionality
    const fixMonitorSelect = document.getElementById('fix-monitor-select');
    const fixFilenamesBtn = document.getElementById('fix-filenames-btn');
    const fixResults = document.getElementById('fix-results');
    
    // Populate fix monitor select
    function populateFixSelect() {
        fixMonitorSelect.innerHTML = '<option value="">-- Select Monitor --</option>';
        monitors.forEach(monitor => {
            const option = document.createElement('option');
            option.value = monitor.id;
            option.textContent = formatMergeMonitorLabel(monitor);
            fixMonitorSelect.appendChild(option);
        });
    }
    
    fixMonitorSelect.addEventListener('change', function() {
        fixFilenamesBtn.disabled = !this.value;
    });
    
    fixFilenamesBtn.addEventListener('click', function() {
        const monitorId = parseInt(fixMonitorSelect.value);
        if (!monitorId) {
            showAlert('Please select a monitor', 'warning');
            return;
        }
        
        if (!confirm('This will rename all files with wrong IP addresses in their filenames. Continue?')) {
            return;
        }
        
        fixFilenamesBtn.disabled = true;
        fixFilenamesBtn.innerHTML = '<span class="loading-spinner"></span>Fixing...';
        fixResults.innerHTML = '';
        
        const formData = new FormData();
        formData.append('monitor_id', monitorId);
        formData.append('source_ips', '192.168.1.52,192.168.1.98,192.168.1.215');
        
        fetch('<?php echo BASE_URL; ?>merge/api_fix_filenames', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let resultHtml = '<div class="alert alert-success">';
                resultHtml += '<strong>✓ Filename fix completed!</strong><br>';
                resultHtml += `Files renamed by type:<br>`;
                resultHtml += `- Logs: ${data.files_renamed_by_type.logs || 0}<br>`;
                resultHtml += `- Screens: ${data.files_renamed_by_type.screens || 0}<br>`;
                resultHtml += `- Thumbnails: ${data.files_renamed_by_type.thumbnails || 0}<br>`;
                resultHtml += `- Total: ${data.files_renamed || 0}<br>`;
                resultHtml += `Target IP: ${data.target_ip}`;
                
                if (data.errors && data.errors.length > 0) {
                    resultHtml += `<br><br><strong>Errors (${data.errors.length}):</strong><br>`;
                    resultHtml += '<small>' + data.errors.slice(0, 10).join('<br>') + '</small>';
                    if (data.errors.length > 10) {
                        resultHtml += `<br><small>... and ${data.errors.length - 10} more errors</small>`;
                    }
                }
                resultHtml += '</div>';
                fixResults.innerHTML = resultHtml;
            } else {
                fixResults.innerHTML = `<div class="alert alert-danger">Error: ${data.error || 'Unknown error'}</div>`;
            }
            
            fixFilenamesBtn.disabled = false;
            fixFilenamesBtn.innerHTML = '<i class="fas fa-tools me-2"></i>Fix Filenames';
        })
        .catch(error => {
            console.error('Error fixing filenames:', error);
            fixResults.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            fixFilenamesBtn.disabled = false;
            fixFilenamesBtn.innerHTML = '<i class="fas fa-tools me-2"></i>Fix Filenames';
        });
    });
    
    // Update populateSelects to also populate fix select
    const originalPopulateSelects = populateSelects;
    populateSelects = function() {
        originalPopulateSelects();
        populateFixSelect();
    };
    
    // Initial load
    loadMonitors();
});
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?>

