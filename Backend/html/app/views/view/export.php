<?php
$title = 'Export Data - ' . APP_NAME;
$active_page = 'export';
$page_title = 'Export Monitoring Data';
ob_start();
?>
<style>
.export-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.filter-panel {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
}

.filter-panel h5 {
    color: var(--monitoring-primary);
    margin-bottom: 20px;
    font-weight: 600;
}

.date-range-group {
    display: flex;
    gap: 20px;
    align-items: end;
    margin-bottom: 20px;
}

.date-input-group {
    flex: 1;
}

.date-input-group label {
    font-weight: 500;
    margin-bottom: 8px;
    color: #333;
}

.date-input-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.date-input-group input:focus {
    outline: none;
    border-color: var(--monitoring-primary);
}

.export-button-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-export {
    background: linear-gradient(135deg, var(--monitoring-success), #45a049) !important;
    color: white !important;
    border: 2px solid #45a049 !important;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    min-width: 120px;
    justify-content: center;
}

#exportBtn {
    background: linear-gradient(135deg, #4CAF50, #45a049) !important;
    color: white !important;
    border: 2px solid #45a049 !important;
}

.btn-export:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

.btn-export:disabled {
    background: #cccccc !important;
    color: #666666 !important;
    border: 2px solid #cccccc !important;
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

#exportBtn:disabled {
    background: #cccccc !important;
    color: #666666 !important;
    border: 2px solid #cccccc !important;
}

.btn-cancel {
    background: #f5f5f5;
    color: #666;
    border: 2px solid #e0e0e0;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-cancel:hover {
    background: #eeeeee;
    border-color: #ccc;
}

.validation-message {
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
    display: none;
}

.validation-error {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}

.validation-success {
    background: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.progress-panel {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    min-height: 400px;
}

.progress-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 20px;
}

.progress-header h5 {
    color: var(--monitoring-primary);
    margin: 0;
    font-weight: 600;
}

.progress-status {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-waiting {
    background: #f5f5f5;
    color: #666;
}

.status-exporting {
    background: #fff3e0;
    color: #ef6c00;
}

.status-completed {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-error {
    background: #ffebee;
    color: #c62828;
}

.progress-bar-container {
    margin: 20px 0;
}

.progress-bar-wrapper {
    width: 100%;
    height: 24px;
    background-color: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #45a049);
    border-radius: 12px;
    transition: width 0.3s ease;
    position: relative;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: progress-shimmer 2s infinite;
}

@keyframes progress-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.progress-text {
    text-align: center;
    margin-top: 8px;
    font-weight: 600;
    color: var(--monitoring-primary);
    font-size: 14px;
}

.progress-log {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    height: 300px;
    overflow-y: auto;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    line-height: 1.5;
    white-space: pre-wrap;
}

.progress-log::-webkit-scrollbar {
    width: 8px;
}

.progress-log::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.progress-log::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.progress-log::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.download-section {
    margin-top: 20px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid var(--monitoring-primary);
    display: none;
}

.download-section h6 {
    margin: 0 0 12px 0;
    color: var(--monitoring-primary);
    font-weight: 600;
}

.btn-download {
    background: var(--monitoring-success);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-download:hover {
    background: #45a049;
    transform: translateY(-1px);
}

.export-info {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}

.export-info h6 {
    margin: 0 0 8px 0;
    color: #1976d2;
    font-weight: 600;
}

.export-info p {
    margin: 0;
    color: #1565c0;
    font-size: 14px;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .date-range-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .export-button-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-export, .btn-cancel {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="export-container">
    <!-- Filter Panel -->
    <div class="filter-panel">
        <h5><i class="fas fa-download me-2"></i>Export Monitoring Data</h5>
        
        <div class="export-info">
            <h6><i class="fas fa-info-circle me-1"></i>Export Information</h6>
            <p>
                Export monitoring data including browser history, key logs, USB device logs, and screenshots. 
                Data will be organized by device and date range. Maximum export period is 1 month (31 days).
            </p>
        </div>
        
        <div class="date-range-group">
            <div class="date-input-group">
                <label for="startDate">Start Date</label>
                <input type="date" id="startDate" name="start_date" required>
            </div>
            <div class="date-input-group">
                <label for="endDate">End Date</label>
                <input type="date" id="endDate" name="end_date" required>
            </div>
            <div class="export-button-group">
                <button type="button" class="btn-export" id="exportBtn" onclick="startExport()">
                    <i class="fas fa-download"></i>
                    Start Export
                </button>
                <button type="button" class="btn-cancel" id="cancelBtn" onclick="cancelExport()" style="display: none;">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
            </div>
        </div>
        
        <div class="validation-message" id="validationMessage"></div>
    </div>
    
    <!-- Progress Panel -->
    <div class="progress-panel">
        <div class="progress-header">
            <h5><i class="fas fa-cogs me-2"></i>Export Progress</h5>
            <div class="progress-status status-waiting" id="progressStatus">Waiting</div>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress-bar-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="progress-text" id="progressText">0% Complete</div>
        </div>
        
        <div class="progress-log" id="progressLog">
Export is ready to start. Please select a date range and click "Start Export" to begin.
        </div>
        
        <div class="download-section" id="downloadSection">
            <h6><i class="fas fa-check-circle me-1"></i>Export Completed</h6>
            <p>Your export has been completed successfully. You can now download the exported files.</p>
            <button type="button" class="btn-download" id="downloadBtn" onclick="downloadExport()">
                <i class="fas fa-download"></i>
                Download Export Files
            </button>
        </div>
    </div>
</div>

<script>
let exportInterval = null;
let currentExportDir = null;
let currentProgressFile = null;

// Initialize date inputs with default values (last 7 days)
function initializeDateInputs() {
    const today = new Date();
    const lastWeek = new Date(today);
    lastWeek.setDate(today.getDate() - 7);
    
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
    document.getElementById('startDate').value = lastWeek.toISOString().split('T')[0];
    
    // Validate on change
    document.getElementById('startDate').addEventListener('change', validateDateRange);
    document.getElementById('endDate').addEventListener('change', validateDateRange);
}

function validateDateRange() {
    const startDate = new Date(document.getElementById('startDate').value);
    const endDate = new Date(document.getElementById('endDate').value);
    const validationMessage = document.getElementById('validationMessage');
    
    // Reset validation message
    validationMessage.style.display = 'none';
    validationMessage.className = 'validation-message';
    
    if (!startDate || !endDate) {
        return false;
    }
    
    if (startDate > endDate) {
        showValidationMessage('Start date must be before end date.', 'error');
        return false;
    }
    
    // Calculate difference in days
    const timeDiff = endDate.getTime() - startDate.getTime();
    const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
    
    if (daysDiff > 31) {
        showValidationMessage('Date range cannot exceed 1 month (31 days).', 'error');
        return false;
    }
    
    showValidationMessage(`Selected range: ${daysDiff} days. Ready to export.`, 'success');
    return true;
}

function showValidationMessage(message, type) {
    const validationMessage = document.getElementById('validationMessage');
    validationMessage.textContent = message;
    validationMessage.className = `validation-message validation-${type}`;
    validationMessage.style.display = 'block';
}

function startExport() {
    if (!validateDateRange()) {
        return;
    }
    
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    // Disable export button and show cancel button
    document.getElementById('exportBtn').disabled = true;
    document.getElementById('cancelBtn').style.display = 'inline-flex';
    
    // Update status
    updateProgressStatus('exporting', 'Exporting...');
    updateProgressLog('Starting export process...\n', false); // Clear previous log
    updateProgressBar(0); // Reset progress bar
    
    // Start export
    fetch('<?php echo BASE_URL; ?>export/api_start_export', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`
    })
    .then(response => {
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response. Check server logs for PHP errors.');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            currentExportDir = data.export_dir;
            currentProgressFile = data.progress_file;
            updateProgressLog(`Export initialized successfully!\nFound ${data.total_devices} devices to export.\n`);
            
            // Start exporting devices one by one
            exportDevicesSequentially(data.devices);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        updateProgressLog(`Error: ${error.message}\n`, true);
        updateProgressStatus('error', 'Error');
        resetExportButtons();
    });
}

function exportDevicesSequentially(devices) {
    let currentDeviceIndex = 0;
    const totalDevices = devices.length;
    
    function exportNextDevice() {
        if (currentDeviceIndex >= totalDevices) {
            // All devices exported
            updateProgressStatus('completed', 'Completed');
            updateProgressBar(100);
            updateProgressLog('\n✅ All devices exported successfully!\n', true);
            showDownloadSection();
            resetExportButtons();
            return;
        }
        
        const device = devices[currentDeviceIndex];
        const progressPercentage = ((currentDeviceIndex) / totalDevices) * 100;
        updateProgressBar(progressPercentage);
        
        updateProgressLog(`\n📱 Exporting device ${currentDeviceIndex + 1}/${totalDevices}: ${device.username} (${device.ip_address})\n`, true);
        
        // Export this device
        fetch('<?php echo BASE_URL; ?>export/api_export_device', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `device_id=${device.id}&start_date=${encodeURIComponent(document.getElementById('startDate').value)}&end_date=${encodeURIComponent(document.getElementById('endDate').value)}&export_dir=${currentExportDir}`
        })
        .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response. This device may have no data to export.');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateProgressLog(`✅ Completed: ${device.username} (${device.ip_address})\n`, true);
                currentDeviceIndex++;
                // Export next device after a short delay
                setTimeout(exportNextDevice, 500);
            } else {
                updateProgressLog(`❌ Failed: ${device.username} - ${data.message}\n`, true);
                currentDeviceIndex++;
                // Continue with next device even if this one failed
                setTimeout(exportNextDevice, 500);
            }
        })
        .catch(error => {
            updateProgressLog(`❌ Error exporting ${device.username}: ${error.message}\n`, true);
            currentDeviceIndex++;
            // Continue with next device even if this one failed
            setTimeout(exportNextDevice, 500);
        });
    }
    
    // Start exporting the first device
    exportNextDevice();
}

// Old progress polling functions removed - now using per-device export approach

function cancelExport() {
    // Note: Per-device exports can't be easily cancelled mid-process
    // The current device will complete, but we can prevent further devices
    updateProgressLog('Export cancellation requested. Current device will complete.\n', true);
    updateProgressStatus('waiting', 'Cancelled');
    resetExportButtons();
}

function resetExportButtons() {
    document.getElementById('exportBtn').disabled = false;
    document.getElementById('cancelBtn').style.display = 'none';
}

function updateProgressStatus(status, text) {
    const statusElement = document.getElementById('progressStatus');
    statusElement.className = `progress-status status-${status}`;
    statusElement.textContent = text;
}

function updateProgressLog(log, append = true) {
    const logElement = document.getElementById('progressLog');
    if (append) {
        // Append new log content instead of replacing
        logElement.textContent += log;
    } else {
        // Replace entire log content
        logElement.textContent = log;
    }
    logElement.scrollTop = logElement.scrollHeight;
}

function updateProgressBar(percentage) {
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
    }
    
    if (progressText) {
        progressText.textContent = Math.round(percentage) + '% Complete';
    }
}

function showDownloadSection() {
    document.getElementById('downloadSection').style.display = 'block';
}

function downloadExport() {
    if (currentExportDir) {
        // Create a link to download the export folder
        const downloadUrl = `<?php echo BASE_URL; ?>exports/${currentExportDir}/`;
        window.open(downloadUrl, '_blank');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDateInputs();
    validateDateRange();
});
</script>

<?php
$content = ob_get_clean();
require_once BASEPATH . 'app/views/layouts/main.php';
?>
