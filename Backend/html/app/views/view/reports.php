<?php
$title = 'Reports - ' . APP_NAME;
$active_page = 'reports';
$page_title = 'Monitoring Usage Reports';
ob_start();
?>

<style>

    .content-area {
        max-width: none !important;
    }
.reports-container {
    padding: 20px;
}

.reports-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
}

.reports-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.reports-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.filter-panel {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.filter-row {
    display: flex;
    gap: 20px;
    align-items: end;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-generate {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 120px;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-generate:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-export {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-left: 10px;
}

.btn-export:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
}

.btn-export:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-clear {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-left: 10px;
}

.btn-clear:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.report-type-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
}

.report-type-tab {
    padding: 10px 20px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    color: #6c757d;
}

.report-type-tab.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
}

.report-type-tab:hover:not(.active) {
    border-color: #667eea;
    color: #667eea;
}

.results-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    display: none;
}

.results-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f8f9fa;
}

.results-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #495057;
    margin: 0;
}

.results-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #dee2e6;
}

.summary-card h4 {
    margin: 0 0 10px 0;
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-card .value {
    font-size: 2rem;
    font-weight: 700;
    color: #495057;
    margin: 0;
}

.reports-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.reports-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.reports-table td {
    padding: 12px;
    border-bottom: 1px solid #f8f9fa;
    vertical-align: middle;
}

.reports-table tr:hover {
    background-color: #f8f9fa;
}

.reports-table tr:last-child td {
    border-bottom: none;
}

.device-info {
    display: flex;
    flex-direction: column;
}

.device-ip {
    font-weight: 600;
    color: #495057;
    font-size: 0.95rem;
}

.device-user {
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 2px;
}

.hours-cell {
    text-align: right;
    font-weight: 600;
    color: #495057;
}

.percentage-bar {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 5px;
}

.percentage-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.percentage-fill.medium {
    background: linear-gradient(90deg, #ffc107, #fd7e14);
}

.percentage-fill.low {
    background: linear-gradient(90deg, #dc3545, #e83e8c);
}

.loading-spinner {
    display: none;
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: #495057;
}

.empty-state p {
    margin: 0;
    font-size: 1.1rem;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 15px 20px;
    border-radius: 10px;
    border: 1px solid #f5c6cb;
    margin: 20px 0;
    display: none;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .results-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .reports-table {
        font-size: 0.9rem;
    }
    
    .reports-table th,
    .reports-table td {
        padding: 10px 8px;
    }
}
</style>

<div class="reports-container">
    <!-- Header -->
    <div class="reports-header">
        <h1><i class="fas fa-chart-line"></i> Monitoring Usage Reports</h1>
        <p>Analyze device monitoring activity and usage patterns over time</p>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <h3 style="margin-top: 0; margin-bottom: 20px; color: #495057;">
            <i class="fas fa-filter"></i> Report Filters
        </h3>
        
        <!-- Report Type Tabs -->
        <div class="report-type-tabs">
            <div class="report-type-tab active" data-type="daily">
                <i class="fas fa-calendar-day"></i> Daily Reports
            </div>
            <div class="report-type-tab" data-type="weekly">
                <i class="fas fa-calendar-week"></i> Weekly Reports
            </div>
            <div class="report-type-tab" data-type="monthly">
                <i class="fas fa-calendar-alt"></i> Monthly Reports
            </div>
        </div>
        
        <!-- Filter Controls -->
        <div class="filter-row">
            <div class="filter-group">
                <label for="deviceFilter">Device</label>
                <select id="deviceFilter">
                    <option value="">All Devices</option>
                </select>
            </div>
            
            <!-- Daily Date Selector -->
            <div class="filter-group" id="dailyDateSelector">
                <label for="dailyDate">Select Date</label>
                <input type="date" id="dailyDate">
            </div>
            
            <!-- Weekly Date Selector -->
            <div class="filter-group" id="weeklyDateSelector" style="display: none;">
                <label for="weeklyDate">Select Week</label>
                <input type="week" id="weeklyDate">
            </div>
            
            <!-- Monthly Date Selector -->
            <div class="filter-group" id="monthlyDateSelector" style="display: none;">
                <label for="monthlyDate">Select Month</label>
                <input type="month" id="monthlyDate">
            </div>
            
            <div class="filter-group">
                <button class="btn-generate" onclick="generateReport()">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
                <button class="btn-export" onclick="exportReport()" id="exportBtn" disabled>
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn-clear" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Error Message -->
    <div class="error-message" id="errorMessage"></div>

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p>Generating report...</p>
    </div>

    <!-- Results Section -->
    <div class="results-section" id="resultsSection">
        <div class="results-header">
            <h3 class="results-title" id="resultsTitle">Monitoring Usage Report</h3>
        </div>
        
        <!-- Summary Cards -->
        <div class="results-summary" id="resultsSummary">
            <!-- Summary cards will be populated here -->
        </div>
        
        <!-- Report Table -->
        <div style="overflow-x: auto;">
            <table class="reports-table" id="reportsTable">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Period</th>
                        <th>Total Hours</th>
                        <th>Captured Hours</th>
                        <th>Not Captured (Online)</th>
                        <th>Offline Hours</th>
                        <th>Activity Summary</th>
                    </tr>
                </thead>
                <tbody id="reportsTableBody">
                    <!-- Report data will be populated here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentReportType = 'daily';
let allDevices = [];
let currentReportData = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    setupDateFilters();
    loadDevices();
    setupReportTypeTabs();
    initializeDateSelectors();
});

function setupDateFilters() {
    const today = new Date();
    const dailyDateInput = document.getElementById('dailyDate');
    const weeklyDateInput = document.getElementById('weeklyDate');
    const monthlyDateInput = document.getElementById('monthlyDate');
    
    // Set default values
    dailyDateInput.value = today.toISOString().split('T')[0];
    dailyDateInput.max = today.toISOString().split('T')[0];
    
    // Set default week (current week)
    const currentWeek = getWeekString(today);
    weeklyDateInput.value = currentWeek;
    
    // Set default month (current month)
    const currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
    monthlyDateInput.value = currentMonth;
}

function setupReportTypeTabs() {
    const tabs = document.querySelectorAll('.report-type-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Update current report type
            currentReportType = this.dataset.type;
            
            // Show/hide appropriate date selectors
            updateDateSelectorsForReportType();
            
            // Update date inputs based on report type
            updateDateInputsForReportType();
        });
    });
}

function updateDateSelectorsForReportType() {
    const dailySelector = document.getElementById('dailyDateSelector');
    const weeklySelector = document.getElementById('weeklyDateSelector');
    const monthlySelector = document.getElementById('monthlyDateSelector');
    
    // Hide all selectors first
    dailySelector.style.display = 'none';
    weeklySelector.style.display = 'none';
    monthlySelector.style.display = 'none';
    
    // Show the appropriate selector
    switch (currentReportType) {
        case 'daily':
            dailySelector.style.display = 'block';
            break;
        case 'weekly':
            weeklySelector.style.display = 'block';
            break;
        case 'monthly':
            monthlySelector.style.display = 'block';
            break;
    }
}

function updateDateInputsForReportType() {
    const today = new Date();
    const dailyDateInput = document.getElementById('dailyDate');
    const weeklyDateInput = document.getElementById('weeklyDate');
    const monthlyDateInput = document.getElementById('monthlyDate');
    
    switch (currentReportType) {
        case 'daily':
            dailyDateInput.value = today.toISOString().split('T')[0];
            break;
        case 'weekly':
            const currentWeek = getWeekString(today);
            weeklyDateInput.value = currentWeek;
            break;
        case 'monthly':
            const currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
            monthlyDateInput.value = currentMonth;
            break;
    }
}

function getWeekString(date) {
    // Get the start of the week (Monday)
    const startOfWeek = new Date(date);
    const day = startOfWeek.getDay();
    const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
    startOfWeek.setDate(diff);
    
    // Format as YYYY-W## (ISO week format)
    const year = startOfWeek.getFullYear();
    const weekNumber = getWeekNumber(startOfWeek);
    return `${year}-W${String(weekNumber).padStart(2, '0')}`;
}

function getWeekNumber(date) {
    const start = new Date(date.getFullYear(), 0, 1);
    const days = Math.floor((date - start) / (24 * 60 * 60 * 1000));
    return Math.ceil((days + start.getDay() + 1) / 7);
}

function getDateRangeFromSelector() {
    let startDate, endDate;
    
    switch (currentReportType) {
        case 'daily':
            startDate = document.getElementById('dailyDate').value;
            endDate = startDate;
            break;
        case 'weekly':
            const weekValue = document.getElementById('weeklyDate').value;
            const weekRange = getWeekDateRange(weekValue);
            startDate = weekRange.start;
            endDate = weekRange.end;
            break;
        case 'monthly':
            const monthValue = document.getElementById('monthlyDate').value;
            const monthRange = getMonthDateRange(monthValue);
            startDate = monthRange.start;
            endDate = monthRange.end;
            break;
        default:
            startDate = endDate = new Date().toISOString().split('T')[0];
    }
    
    return { startDate, endDate };
}

function getWeekDateRange(weekValue) {
    // weekValue format: YYYY-W##
    const [year, week] = weekValue.split('-W');
    const startDate = new Date(year, 0, 1);
    const daysToAdd = (parseInt(week) - 1) * 7;
    startDate.setDate(startDate.getDate() + daysToAdd);
    
    // Adjust to Monday of that week
    const dayOfWeek = startDate.getDay();
    const daysToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
    startDate.setDate(startDate.getDate() + daysToMonday);
    
    const endDate = new Date(startDate);
    endDate.setDate(startDate.getDate() + 6);
    
    return {
        start: startDate.toISOString().split('T')[0],
        end: endDate.toISOString().split('T')[0]
    };
}

function getMonthDateRange(monthValue) {
    // monthValue format: YYYY-MM
    const [year, month] = monthValue.split('-');
    const startDate = new Date(year, month - 1, 1);
    const endDate = new Date(year, month, 0); // Last day of the month
    
    return {
        start: startDate.toISOString().split('T')[0],
        end: endDate.toISOString().split('T')[0]
    };
}

function loadDevices() {
    fetch(`<?php echo BASE_URL; ?>reports/api_devices`)
        .then(response => response.json())
        .then(devices => {
            allDevices = devices;
            populateDeviceFilter(devices);
        })
        .catch(error => {
            console.error('Error loading devices:', error);
            showError('Error loading devices: ' + error.message);
        });
}

function populateDeviceFilter(devices) {
    const deviceFilter = document.getElementById('deviceFilter');
    deviceFilter.innerHTML = '<option value="">All Devices</option>';
    
    devices.forEach(device => {
        const option = document.createElement('option');
        option.value = device.id;
        option.textContent = `${device.username || 'Unknown'} (${device.ip_address})`;
        deviceFilter.appendChild(option);
    });
}

function generateReport() {
    const deviceId = document.getElementById('deviceFilter').value;
    
    // Get date range based on current report type
    const dateRange = getDateRangeFromSelector();
    const { startDate, endDate } = dateRange;
    
    if (!startDate || !endDate) {
        showError(`Please select a ${currentReportType} period.`);
        return;
    }
    
    showLoading(true);
    hideError();
    hideResults();
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        report_type: currentReportType
    });
    
    if (deviceId) {
        params.append('device_id', deviceId);
    }
    
    fetch(`<?php echo BASE_URL; ?>reports/api_usage_report?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayReport(data);
            } else {
                showError(data.message || 'Error generating report.');
            }
        })
        .catch(error => {
            console.error('Error generating report:', error);
            showError('Error generating report: ' + error.message);
        })
        .finally(() => {
            showLoading(false);
        });
}

function displayReport(data) {
    const resultsSection = document.getElementById('resultsSection');
    const resultsTitle = document.getElementById('resultsTitle');
    const resultsSummary = document.getElementById('resultsSummary');
    const reportsTableBody = document.getElementById('reportsTableBody');
    
    // Store current report data for export
    currentReportData = data;
    
    // Update title
    const reportTypeLabel = currentReportType.charAt(0).toUpperCase() + currentReportType.slice(1);
    resultsTitle.textContent = `${reportTypeLabel} Monitoring Usage Report (${data.start_date} to ${data.end_date})`;
    
    // Display summary cards
    displaySummaryCards(data.data);
    
    // Display report table - pass report info for date range
    const reportInfo = {
        start_date: data.start_date,
        end_date: data.end_date
    };
    displayReportTable(data.data, reportInfo);
    
    // Enable export button
    document.getElementById('exportBtn').disabled = false;
    
    // Show results section
    resultsSection.style.display = 'block';
}

function displaySummaryCards(reportData) {
    const resultsSummary = document.getElementById('resultsSummary');
    
    if (reportData.length === 0) {
        resultsSummary.innerHTML = `
            <div class="summary-card">
                <h4>Total Devices</h4>
                <p class="value">0</p>
            </div>
            <div class="summary-card">
                <h4>Total Monitoring Hours</h4>
                <p class="value">0</p>
            </div>
            <div class="summary-card">
                <h4>Average Usage</h4>
                <p class="value">0%</p>
            </div>
        `;
        return;
    }
    
    const totalDevices = reportData.length;
    const totalMonitoringHours = reportData.reduce((sum, device) => sum + device.captured_hours, 0);
    const totalPeriodHours = reportData.reduce((sum, device) => sum + device.period_hours, 0);
    const averageUsage = totalPeriodHours > 0 ? ((totalMonitoringHours / totalPeriodHours) * 100).toFixed(1) : 0;
    
    resultsSummary.innerHTML = `
        <div class="summary-card">
            <h4>Total Devices</h4>
            <p class="value">${totalDevices}</p>
        </div>
        <div class="summary-card">
            <h4>Total Monitoring Hours</h4>
            <p class="value">${totalMonitoringHours.toFixed(1)}</p>
        </div>
        <div class="summary-card">
            <h4>Average Usage</h4>
            <p class="value">${averageUsage}%</p>
        </div>
        <div class="summary-card">
            <h4>Report Period</h4>
            <p class="value">${currentReportType}</p>
        </div>
    `;
}

function displayReportTable(reportData, reportInfo) {
    const reportsTableBody = document.getElementById('reportsTableBody');
    
    if (reportData.length === 0) {
        reportsTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Data Found</h3>
                    <p>No monitoring data found for the selected period and filters.</p>
                </td>
            </tr>
        `;
        return;
    }
    
    reportsTableBody.innerHTML = '';
    
    reportData.forEach(device => {
        const row = document.createElement('tr');
        
        const usagePercentage = device.period_hours > 0 ? 
            ((device.captured_hours / device.period_hours) * 100).toFixed(1) : 0;
        
        const activitySummary = `
            <div>
                <small>
                    Screenshots: ${device.screenshot_count}<br>
                    Key Activity: ${device.key_activity_count}<br>
                    Browser Activity: ${device.browser_activity_count}
                </small>
                <div class="percentage-bar">
                    <div class="percentage-fill ${getUsageClass(usagePercentage)}" 
                         style="width: ${Math.min(usagePercentage, 100)}%"></div>
                </div>
            </div>
        `;
        
        row.innerHTML = `
            <td>
                <div class="device-info">
                    <div class="device-ip">${device.ip_address}</div>
                    <div class="device-user">${device.username || 'Unknown User'}</div>
                </div>
            </td>
            <td>${formatDateRange(reportInfo.start_date, reportInfo.end_date)}</td>
            <td class="hours-cell">${device.period_hours}h</td>
            <td class="hours-cell">${device.captured_hours}h</td>
            <td class="hours-cell">${device.not_captured_while_online}h</td>
            <td class="hours-cell">${device.offline_hours}h</td>
            <td>${activitySummary}</td>
        `;
        
        reportsTableBody.appendChild(row);
    });
}

function getUsageClass(percentage) {
    if (percentage >= 70) return '';
    if (percentage >= 40) return 'medium';
    return 'low';
}

function formatDateRange(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (startDate === endDate) {
        return start.toLocaleDateString();
    } else {
        return `${start.toLocaleDateString()} - ${end.toLocaleDateString()}`;
    }
}

function exportReport() {
    if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
        showError('No report data available to export. Please generate a report first.');
        return;
    }
    
    // Generate CSV content
    const csvContent = generateCSVContent(currentReportData);
    
    // Create and download file
    downloadCSV(csvContent, generateFileName());
}

function generateCSVContent(data) {
    const headers = [
        'Device IP',
        'Username', 
        'Device Name',
        'Device Type',
        'Period',
        'Total Hours',
        'Captured Hours',
        'Not Captured While Online',
        'Offline Hours',
        'Screenshot Count',
        'Key Activity Count',
        'Browser Activity Count',
        'Usage Percentage (%)'
    ];
    
    const csvRows = [headers.join(',')];
    
    data.data.forEach(device => {
        const usagePercentage = device.period_hours > 0 ? 
            ((device.captured_hours / device.period_hours) * 100).toFixed(1) : 0;
        
        const row = [
            escapeCSVField(device.ip_address),
            escapeCSVField(device.username || 'Unknown'),
            escapeCSVField(device.name || ''),
            escapeCSVField(device.type || ''),
            escapeCSVField(`${data.start_date} to ${data.end_date}`),
            device.period_hours,
            device.captured_hours.toFixed(2),
            device.not_captured_while_online.toFixed(2),
            device.offline_hours.toFixed(2),
            device.screenshot_count,
            device.key_activity_count,
            device.browser_activity_count,
            usagePercentage
        ];
        
        csvRows.push(row.join(','));
    });
    
    // Add summary row
    const totalDevices = data.data.length;
    const totalMonitoringHours = data.data.reduce((sum, device) => sum + device.captured_hours, 0);
    const totalPeriodHours = data.data.reduce((sum, device) => sum + device.period_hours, 0);
    const averageUsage = totalPeriodHours > 0 ? ((totalMonitoringHours / totalPeriodHours) * 100).toFixed(1) : 0;
    
    csvRows.push(''); // Empty row
    csvRows.push('SUMMARY');
    csvRows.push('Total Devices,' + totalDevices);
    csvRows.push('Total Monitoring Hours,' + totalMonitoringHours.toFixed(2));
    csvRows.push('Average Usage %,' + averageUsage);
    csvRows.push('Report Type,' + data.report_type);
    csvRows.push('Generated On,' + new Date().toISOString());
    
    return csvRows.join('\n');
}

function escapeCSVField(field) {
    if (field === null || field === undefined) {
        return '';
    }
    
    const stringField = String(field);
    
    // If field contains comma, quote, or newline, wrap in quotes and escape quotes
    if (stringField.includes(',') || stringField.includes('"') || stringField.includes('\n')) {
        return '"' + stringField.replace(/"/g, '""') + '"';
    }
    
    return stringField;
}

function generateFileName() {
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toTimeString().split(' ')[0].replace(/:/g, '-');
    const reportType = currentReportType.charAt(0).toUpperCase() + currentReportType.slice(1);
    
    return `monitoring-usage-report-${reportType}-${dateStr}-${timeStr}.csv`;
}

function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        // Feature detection. Browsers that support HTML5 download attribute
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } else {
        // Fallback for older browsers
        showError('CSV export is not supported in this browser. Please use a modern browser.');
    }
}

function clearFilters() {
    document.getElementById('deviceFilter').value = '';
    updateDateInputsForReportType();
    hideResults();
    hideError();
    
    // Disable export button and clear report data
    document.getElementById('exportBtn').disabled = true;
    currentReportData = null;
}

// Initialize date selectors on page load
function initializeDateSelectors() {
    updateDateSelectorsForReportType();
    updateDateInputsForReportType();
}

function showLoading(show) {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const generateBtn = document.querySelector('.btn-generate');
    
    if (show) {
        loadingSpinner.style.display = 'block';
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    } else {
        loadingSpinner.style.display = 'none';
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-chart-bar"></i> Generate Report';
    }
}

function showError(message) {
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = message;
    errorMessage.style.display = 'block';
}

function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}

function hideResults() {
    document.getElementById('resultsSection').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
require_once BASEPATH . 'app/views/layouts/main.php';
?>
