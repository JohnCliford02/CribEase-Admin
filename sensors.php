<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    header("Location: index.php"); 
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">
<?php include 'includes/maintenance_banner.php'; ?>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php" class="active">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="maintenance.php">Maintenance</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <div id="maintenanceBannerContainer"></div>
    <h1>Sensor Data Dashboard</h1>
    <div id="sensorDebug" style="margin:10px 0; padding:10px; border-radius:6px; background:#fff3cd; color:#856404; display:none;"></div>

    <!-- Summary Cards -->
    <div id="summaryCards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div class="summary-card">
            <div class="summary-label">Active Device(s)</div>
            <div class="summary-value" id="activeCount">0</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Device(s)</div>
            <div class="summary-value" id="totalCount">0</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Avg Temperature</div>
            <div class="summary-value" id="avgTemp">--°C</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Absent Count</div>
            <div class="summary-value" id="absentCount">0</div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" id="searchInput" placeholder="Search by Device ID, Temperature, or Timestamp..." 
               style="flex: 1; min-width: 250px; padding: 12px 15px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; transition: border-color 0.3s;">
        <select id="statusFilter" style="padding: 12px 15px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
            <option value="">All Status</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
        </select>
        <button class="btn" onclick="clearSearch()" style="padding: 12px 25px;">Clear Filters</button>
    </div>

    <!-- Records Per Page -->
    <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
        <label for="rowsPerPage" style="font-weight: 500;">Rows per page:</label>
        <select id="rowsPerPage" style="padding: 8px 12px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer;">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
    </div>

    <div class="table-wrapper">
        <table id="sensorTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Device ID</th>
                    <th>Presence Detection</th>
                    <th>Presence Status</th>
                    <th>Sleep Pattern</th>
                    <th>Sound</th>
                    <th>Temperature</th>
                    <th>Start Time</th>
                    <th>Last Active</th>
                </tr>
            </thead>
            <tbody id="sensorTableBody">
                <!-- Sensor data rows will be inserted here -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="paginationContainer" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;">
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getDatabase, ref, onValue } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

const style = document.createElement('style');
style.innerHTML = `
    .table-wrapper { 
        overflow-x: auto; 
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        background: white;
    }
    #sensorTable { 
        width: 100%; 
        border-collapse: collapse; 
        table-layout: auto;
    }
    #sensorTable th, #sensorTable td { 
        padding: 14px 12px; 
        text-align: left; 
        vertical-align: middle;
        border-bottom: 1px solid #e0e0e0;
    }
    #sensorTable th { 
        background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        color: white;
        font-weight: 600;
        position: sticky;
        top: 0;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }
    #sensorTable tbody tr {
        transition: background-color 0.2s ease;
    }
    #sensorTable tbody tr:hover {
        background-color: #f8f9fa;
    }
    #sensorTable tbody tr:nth-child(even) {
        background-color: #f5f5f5;
    }
    #sensorTable .device-id-cell { 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
        font-family: monospace;
        font-weight: 500;
        color: #2980b9;
    }
    #sensorTable .device-id-cell:hover { 
        white-space: normal; 
        overflow: visible; 
        word-break: break-word; 
        background-color: #fff3cd;
        z-index: 10;
        position: relative;
    }
    
    /* Status indicator */
    .status-indicator { 
        display: inline-block; 
        width: 14px; 
        height: 14px; 
        border-radius: 50%; 
        margin-right: 8px; 
        vertical-align: middle;
        box-shadow: 0 0 8px rgba(0,0,0,0.2);
        animation: pulse 2s infinite;
    }
    .status-indicator.active { 
        background: #27ae60;
        box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
    }
    .status-indicator.inactive { 
        background: #e74c3c;
        box-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    /* Summary Cards */
    .summary-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    .summary-label {
        font-size: 12px;
        text-transform: uppercase;
        opacity: 0.9;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }
    .summary-value {
        font-size: 32px;
        font-weight: bold;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-badge.present {
        background-color: #d4edda;
        color: #155724;
    }
    .status-badge.absent {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    /* Pagination */
    .pagination-btn {
        padding: 8px 12px;
        margin: 0 2px;
        border: 2px solid #ddd;
        background: black;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
        font-size: 13px;
    }
    .pagination-btn:hover {
        background: #f0f0f0;
        border-color: #34495e;
    }
    .pagination-btn.active {
        background: #34495e;
        color: white;
        border-color: #34495e;
    }
    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
`;
document.head.appendChild(style);

// Search and Filter functionality
let allRows = [];
let currentPage = 1;
let rowsPerPage = 25;

window.filterSensors = function() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    
    allRows = Array.from(document.querySelectorAll('#sensorTable tbody tr'));
    
    const filtered = allRows.filter(row => {
        const text = row.textContent.toLowerCase();
        const matchesSearch = text.includes(searchTerm);
        
        let matchesStatus = true;
        if (statusFilter === 'active') {
            matchesStatus = row.querySelector('.status-indicator.active') !== null;
        } else if (statusFilter === 'inactive') {
            matchesStatus = row.querySelector('.status-indicator.inactive') !== null;
        }
        
        return matchesSearch && matchesStatus;
    });
    
    currentPage = 1;
    displayPaginatedRows(filtered);
};

window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    currentPage = 1;
    displayAllRows();
};

function displayAllRows() {
    allRows = Array.from(document.querySelectorAll('#sensorTable tbody tr'));
    displayPaginatedRows(allRows);
}

function displayPaginatedRows(rowsToDisplay) {
    const totalPages = Math.ceil(rowsToDisplay.length / rowsPerPage);
    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx = startIdx + rowsPerPage;
    
    // Hide all rows
    allRows.forEach(row => row.style.display = 'none');
    
    // Show only current page rows
    rowsToDisplay.slice(startIdx, endIdx).forEach(row => row.style.display = '');
    
    // Generate pagination buttons
    generatePagination(totalPages, rowsToDisplay.length);
}

function generatePagination(totalPages, totalRows) {
    const container = document.getElementById('paginationContainer');
    container.innerHTML = '';
    
    if (totalPages <= 1) {
        container.innerHTML = `<span style="color: #666; padding: 10px;">Showing ${totalRows} records</span>`;
        return;
    }
    
    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.textContent = '← Previous';
    prevBtn.className = 'pagination-btn';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            displayPaginatedRows(Array.from(document.querySelectorAll('#sensorTable tbody tr')).filter(r => r.style.display !== 'none' || r.closest('table')));
        }
    };
    container.appendChild(prevBtn);
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className = 'pagination-btn' + (i === currentPage ? ' active' : '');
        btn.onclick = () => {
            currentPage = i;
            displayPaginatedRows(Array.from(document.querySelectorAll('#sensorTable tbody tr')).filter(r => r.style.display !== 'none' || r.closest('table')));
        };
        container.appendChild(btn);
    }
    
    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.textContent = 'Next →';
    nextBtn.className = 'pagination-btn';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => {
        if (currentPage < totalPages) {
            currentPage++;
            displayPaginatedRows(Array.from(document.querySelectorAll('#sensorTable tbody tr')).filter(r => r.style.display !== 'none' || r.closest('table')));
        }
    };
    container.appendChild(nextBtn);
    
    container.appendChild(document.createElement('span')).textContent = ` | Page ${currentPage} of ${totalPages}`;
}

// Rows per page change
document.getElementById('rowsPerPage').addEventListener('change', function() {
    rowsPerPage = parseInt(this.value);
    currentPage = 1;
    displayAllRows();
});

document.getElementById('searchInput').addEventListener('keyup', filterSensors);
document.getElementById('statusFilter').addEventListener('change', filterSensors);

// Firebase Config
const firebaseConfig = {
    apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
    authDomain: "esp32-connecttest.firebaseapp.com",
    databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "esp32-connecttest",
    storageBucket: "esp32-connecttest.firebasestorage.app",
    messagingSenderId: "950000610308",
    appId: "1:950000610308:web:a39583249e23784128d951"
};

// Init
const app = initializeApp(firebaseConfig);
const rtdb = getDatabase(app);

// Listen to realtime database devices path and update table
// Keep previous flat sensor snapshot per device and a local history to show appended readings
// Persist these to localStorage so they survive page refreshes
const STORAGE_KEY = 'cribease_sensor_cache_v1';
let _prevDeviceSensor = {}; // deviceId -> last flat sensor object
let _localHistory = {}; // deviceId -> array of previous flat sensor objects (most recent first)

function loadLocalCache() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
            _prevDeviceSensor = parsed.prev || {};
            _localHistory = parsed.hist || {};
        }
    } catch (e) {
        console.warn('Failed to load sensor cache from localStorage', e);
    }
}

function saveLocalCache() {
    try {
        const payload = JSON.stringify({ prev: _prevDeviceSensor, hist: _localHistory });
        localStorage.setItem(STORAGE_KEY, payload);
    } catch (e) {
        console.warn('Failed to save sensor cache to localStorage', e);
    }
}

function pruneLocalHistory() {
    const MAX_PER_DEVICE = 200;
    const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000; // 7 days
    const now = Date.now();
    Object.keys(_localHistory).forEach(deviceId => {
        const arr = _localHistory[deviceId];
        if (!Array.isArray(arr)) return;
        // remove entries older than MAX_AGE_MS
        _localHistory[deviceId] = arr.filter(entry => {
            if (!entry._capturedAt) return true;
            const parsed = Date.parse(entry._capturedAt);
            if (isNaN(parsed)) return true;
            return (now - parsed) <= MAX_AGE_MS;
        }).slice(0, MAX_PER_DEVICE);
    });
}

// Load any previously cached sensor history
loadLocalCache();

// helper debug element
const _debugEl = document.getElementById('sensorDebug');
function showDebug(msg, level = 'info'){
    if(!_debugEl) return;
    _debugEl.style.display = 'block';
    const p = document.createElement('div');
    p.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    if(level === 'error') p.style.color = '#721c24';
    _debugEl.appendChild(p);
}

// Listen with error handler so we can report failures to the UI
onValue(ref(rtdb, 'devices'), (snapshot) => {
    const tbody = document.getElementById('sensorTableBody');
    tbody.innerHTML = '';

    const data = snapshot.val();
    
    // Initialize summary counters
    let activeCount = 0;
    let totalCount = 0;
    let absentCount = 0;
    let tempSum = 0;
    let tempCount = 0;
    
    if (!data) {
        showDebug('No sensor data found (snapshot is null or empty)', 'error');
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="9" style="text-align:center; padding:30px; color:#999;">No sensor data found</td>';
        tbody.appendChild(row);
        updateSummary(activeCount, totalCount, absentCount, tempCount > 0 ? (tempSum / tempCount).toFixed(1) : '--');
        return;
    }

    // Iterate through each device and display up to MAX_DISPLAY sensor rows (historical data capped)
    const MAX_DISPLAY = 200; // maximum rows to render in the table
    let displayedCount = 0;   // rows appended so far
    let totalRowsCounted = 0; // total rows encountered (for notice if needed)
    let limitReached = false;

    const deviceIds = Object.keys(data);
    for (let d = 0; d < deviceIds.length; d++) {
        if (displayedCount >= MAX_DISPLAY) { limitReached = true; break; }
        const deviceId = deviceIds[d];
        const device = data[deviceId];
        let sensor = device.sensor || {};
        const info = device.info || {};
        
        totalCount++;
        
        // Debug logging - show what fields are in the sensor object
        console.log('Device:', deviceId, 'Sensor fields:', Object.keys(sensor).slice(0, 10));
        
        // Check for all possible field names
        const hasPresenceDetection = sensor.presenceDetection !== undefined;
        const hasPresenceStatus = sensor.presenceStatus !== undefined;
        const hasFallCount = sensor.fallCount !== undefined;
        const hasFallStatus = sensor.fallStatus !== undefined;
        console.log('Device:', deviceId, '- presenceDetection:', hasPresenceDetection, 'presenceStatus:', hasPresenceStatus, 'fallCount:', hasFallCount, 'fallStatus:', hasFallStatus);

        // Helper function to convert 24hr time to 12hr AM/PM format
        function formatTo12Hour(timeString) {
            if (!timeString) return '-';
            try {
                // Parse time string like "12/09/2025 - 13:50:15"
                const parts = timeString.split(' - ');
                if (parts.length < 2) return timeString;
                
                const datePart = parts[0];
                const timePart = parts[1];
                const [hours, minutes, seconds] = timePart.split(':');
                
                let hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                hour = hour % 12;
                hour = hour ? hour : 12; // 0 should be 12
                
                const formattedTime = `${String(hour).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
                return `${datePart} - ${formattedTime}`;
            } catch (e) {
                return timeString;
            }
        }

        // Format Start Time (deviceStartTime)
        let startTime = formatTo12Hour(info.deviceStartTime) || '-';
        
        // Format Last Active (deviceLastActive)
        let lastActive = formatTo12Hour(info.deviceLastActive) || '-';

        // Determine device active/inactive status (based on last-active timestamp)
        let isActive = false;
        // We'll try multiple candidate fields to determine when the device was last active
        const lastActiveRawCandidates = [
            info.deviceLastActive,
            info.lastActive,
            info.deviceLastSeen,
            info.last_seen,
            // possible timestamps embedded in sensor objects
            (sensor && sensor.updatedAt) || null,
            (sensor && sensor.timestamp) || null,
            // lastSensor fallback
            (device.lastSensor && (device.lastSensor._capturedAt || device.lastSensor.timestamp || device.lastSensor.updatedAt)) || null
        ];
        const lastActiveRaw = lastActiveRawCandidates.find(x => x !== undefined && x !== null && x !== '');

        // Helper: parse common non-ISO timestamp formats (e.g. "MM/DD/YYYY - HH:MM:SS")
        function parseDeviceDate(raw) {
            if (!raw) return null;
            // Try native parse first
            const d = new Date(raw);
            if (!isNaN(d.getTime())) return d;

            // Try pattern: MM/DD/YYYY - HH:MM:SS
            const m = raw.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*-\s*(\d{1,2}):(\d{2}):(\d{2})/);
            if (m) {
                const month = parseInt(m[1], 10);
                const day = parseInt(m[2], 10);
                const year = parseInt(m[3], 10);
                const hour = parseInt(m[4], 10);
                const minute = parseInt(m[5], 10);
                const second = parseInt(m[6], 10);
                return new Date(year, month - 1, day, hour, minute, second);
            }

            // Try pattern: M/D/YYYY, H:MM:SS AM/PM
            const m2 = raw.match(/(\d{1,2})\/(\d{1,2})\/(\d{4}).*?(\d{1,2}):(\d{2}):(\d{2})(?:\s*(AM|PM))?/i);
            if (m2) {
                let month = parseInt(m2[1], 10);
                let day = parseInt(m2[2], 10);
                let year = parseInt(m2[3], 10);
                let hour = parseInt(m2[4], 10);
                const minute = parseInt(m2[5], 10);
                const second = parseInt(m2[6], 10);
                const ampm = (m2[7] || '').toUpperCase();
                if (ampm === 'PM' && hour < 12) hour += 12;
                if (ampm === 'AM' && hour === 12) hour = 0;
                return new Date(year, month - 1, day, hour, minute, second);
            }

            return null;
        }

        if (lastActiveRaw) {
            const parsed = parseDeviceDate(lastActiveRaw);
            if (parsed && !isNaN(parsed.getTime())) {
                const age = Date.now() - parsed.getTime();
                    const ACTIVE_THRESHOLD = 10 * 1000; // 10 seconds
                // Require both a recent timestamp AND that the device currently has a live 'sensor' node
                // or an explicit online flag in info to be considered active.
                const hasLiveSensor = device.sensor && Object.keys(device.sensor).length > 0;
                const explicitOnline = info.online === true || info.isOnline === true;
                isActive = (age <= ACTIVE_THRESHOLD) && (hasLiveSensor || explicitOnline);
            }
        }

        const statusHtml = `<td><span class="status-indicator ${isActive ? 'active' : 'inactive'}" title="${isActive ? 'Active' : 'Inactive'}"></span></td>`;
        
        // Update active count
        if (isActive) activeCount++;

        // Check if sensor is flat (single record) or nested (multiple historical records)
        let sensorKeys = Object.keys(sensor);

        if (sensorKeys.length === 0) {
            // No current sensor node for this device. Try fallbacks:
            // 1) lastSensor field written by server/device (persistent)
            // 2) previously seen flat reading during this session (`_prevDeviceSensor`)
            const lastSensorFromDb = device.lastSensor || device.last_sensor || null;
            const prev = _prevDeviceSensor[deviceId] || null;
            const fallback = lastSensorFromDb || prev || {};

            if (fallback && Object.keys(fallback).length > 0) {
                // Use fallback as the current sensor for rendering
                sensor = fallback;
                sensorKeys = Object.keys(sensor);
            } else {
                // Nothing to show for this device except basic metadata - render an inactive row
                const row = document.createElement('tr');
                row.innerHTML = `
                    ${statusHtml}
                    <td class="device-id-cell">${device.deviceId || device.deviceID || deviceId}</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>${startTime}</td>
                    <td>${lastActive}</td>
                `;
                // increment total rows seen
                totalRowsCounted++;
                if (displayedCount < MAX_DISPLAY) {
                    tbody.appendChild(row);
                    displayedCount++;
                }
                continue;
            }
        }

        // Check if this is a flat structure (has temperature, presenceDetection, fallCount directly)
        if (sensor.temperature !== undefined || sensor.presenceDetection !== undefined || sensor.presenceStatus !== undefined || sensor.fallCount !== undefined || sensor.fallStatus !== undefined) {
            // Flat sensor structure - single latest reading in DB.
            console.log('Flat structure detected for device', deviceId, '- presenceDetection:', sensor.presenceDetection, 'presenceStatus:', sensor.presenceStatus);
            // We'll keep a local history per device so the UI can show appended readings
            // rather than replacing the row every time the device writes a new flat object.
            try {
                const prev = _prevDeviceSensor[deviceId];
                // If previous exists and differs from current, push previous into local history
                if (prev && JSON.stringify(prev) !== JSON.stringify(sensor)) {
                    _localHistory[deviceId] = _localHistory[deviceId] || [];
                    // store a copy of prev with a captured timestamp from info.deviceLastActive (fallback to now)
                    const histEntry = Object.assign({}, prev);
                    histEntry._capturedAt = info.deviceLastActive || new Date().toISOString();
                    _localHistory[deviceId].unshift(histEntry);
                    // limit history length to 50 entries
                    if (_localHistory[deviceId].length > 50) _localHistory[deviceId].pop();
                    // prune older entries and persist cache
                    pruneLocalHistory();
                    saveLocalCache();
                }
                // update prev pointer
                _prevDeviceSensor[deviceId] = JSON.parse(JSON.stringify(sensor));
                // persist prev pointer so it survives refresh
                saveLocalCache();
            } catch (e) { console.warn('History tracking failed for', deviceId, e); }

            // If we have no cached local history for this device, try to seed it from DB's lastSensor
            if ((!_localHistory[deviceId] || _localHistory[deviceId].length === 0) && (device.lastSensor || device.last_sensor)) {
                try {
                    const seed = device.lastSensor || device.last_sensor;
                    if (seed && typeof seed === 'object') {
                        _localHistory[deviceId] = _localHistory[deviceId] || [];
                        const histEntry = Object.assign({}, seed);
                        histEntry._capturedAt = histEntry._capturedAt || info.deviceLastActive || new Date().toISOString();
                        // add as most recent
                        _localHistory[deviceId].unshift(histEntry);
                        pruneLocalHistory();
                        saveLocalCache();
                        if (typeof showDebug === 'function') showDebug(`Seeded history for ${deviceId} from lastSensor`);
                    }
                } catch (e) { console.warn('Failed to seed local history for', deviceId, e); }
            }

            // Render any local history entries first (most recent first)
            const hist = _localHistory[deviceId] || [];
            for (let h = 0; h < hist.length; h++) {
                if (displayedCount >= MAX_DISPLAY) { limitReached = true; break; }
                const record = hist[h];
                if (!record || typeof record !== 'object') continue;
                const hRow = document.createElement('tr');
                // Resolve presence detection and status (prefer presenceDetection/presenceStatus)
                let hDisplayPresenceDetection = (record.presenceDetection !== undefined && record.presenceDetection !== null)
                    ? record.presenceDetection
                    : (record.fallCount !== undefined && record.fallCount !== null)
                        ? record.fallCount
                        : (record.distance !== undefined && record.distance !== null)
                            ? record.distance
                            : 'N/A';

                let hDisplayPresenceStatus = (record.presenceStatus !== undefined && record.presenceStatus !== null && record.presenceStatus !== '')
                    ? ('' + record.presenceStatus)
                    : (record.fallStatus !== undefined && record.fallStatus !== null && record.fallStatus !== '')
                        ? (record.fallStatus.toLowerCase() === 'present' ? 'Present' : 'Absent')
                        : 'N/A';
                const hPresenceStatusLower = (hDisplayPresenceStatus + '').toLowerCase();
                if (hPresenceStatusLower === 'absent') absentCount++;

                // temperature from history
                if (record.temperature) {
                    const temp = parseInt(record.temperature);
                    if (!isNaN(temp)) { tempSum += temp; tempCount++; }
                }

                hRow.innerHTML = `
                    ${statusHtml}
                    <td class="device-id-cell">${record.device_id || deviceId}</td>
                    <td>${hDisplayPresenceDetection}</td>
                    <td><span class="status-badge ${hPresenceStatusLower === 'present' ? 'present' : 'absent'}">${hDisplayPresenceStatus}</span></td>
                    <td>${record.sleepPattern || record.sleep_pattern || '-'}</td>
                    <td>${record.sound || '-'}</td>
                    <td>${record.temperature || '-'}°C</td>
                    <td>${record._capturedAt ? formatTo12Hour(record._capturedAt) : startTime}</td>
                    <td>${record._capturedAt ? formatTo12Hour(record._capturedAt) : lastActive}</td>
                `;
                // increment total rows seen
                totalRowsCounted++;
                if (displayedCount < MAX_DISPLAY) {
                    tbody.appendChild(hRow);
                    displayedCount++;
                }
            }
            if (limitReached) break;

            // Finally render the current/latest reading
            const row = document.createElement('tr');
            // Determine presence detection and status (prefer presenceDetection/presenceStatus fields)
            let displayPresenceDetection = (sensor.presenceDetection !== undefined && sensor.presenceDetection !== null)
                ? sensor.presenceDetection
                : (sensor.fallCount !== undefined && sensor.fallCount !== null)
                    ? sensor.fallCount
                    : (sensor.distance !== undefined && sensor.distance !== null)
                        ? sensor.distance
                        : 'N/A';

            let displayPresenceStatus = (sensor.presenceStatus !== undefined && sensor.presenceStatus !== null && sensor.presenceStatus !== '')
                ? ('' + sensor.presenceStatus)
                : (sensor.fallStatus !== undefined && sensor.fallStatus !== null && sensor.fallStatus !== '')
                    ? (sensor.fallStatus.toLowerCase() === 'present' ? 'Present' : 'Absent')
                    : 'N/A';

            const sensorPresenceStatusLower = (displayPresenceStatus + '').toLowerCase();
            if (sensorPresenceStatusLower === 'absent') {
                absentCount++;
            }
            // Calculate temperature
            if (sensor.temperature) {
                const temp = parseInt(sensor.temperature);
                if (!isNaN(temp)) {
                    tempSum += temp;
                    tempCount++;
                }
            }
            row.innerHTML = `
                ${statusHtml}
                <td class="device-id-cell">${sensor.device_id || deviceId}</td>
                <td>${displayPresenceDetection}</td>
                <td><span class="status-badge ${sensorPresenceStatusLower === 'present' ? 'present' : 'absent'}">${displayPresenceStatus}</span></td>
                <td>${sensor.sleepPattern || sensor.sleep_pattern || '-'}</td>
                <td>${sensor.sound || '-'}</td>
                <td>${sensor.temperature || '-'}°C</td>
                <td>${startTime}</td>
                <td>${lastActive}</td>
            `;
            totalRowsCounted++;
            if (displayedCount < MAX_DISPLAY) {
                tbody.appendChild(row);
                displayedCount++;
            }
        } else {
            // Nested structure - pick the latest record only (avoid creating a row per historical entry)
            const lastKey = sensorKeys[sensorKeys.length - 1];
            const latestRecord = sensor[lastKey];
            if (latestRecord && typeof latestRecord === 'object') {
                // Determine presence detection and status (prefer presenceDetection/presenceStatus fields)
                let displayPresenceDetection = (latestRecord.presenceDetection !== undefined && latestRecord.presenceDetection !== null)
                    ? latestRecord.presenceDetection
                    : (latestRecord.fallCount !== undefined && latestRecord.fallCount !== null)
                        ? latestRecord.fallCount
                        : (latestRecord.distance !== undefined && latestRecord.distance !== null)
                            ? latestRecord.distance
                            : 'N/A';

                let displayPresenceStatus = (latestRecord.presenceStatus !== undefined && latestRecord.presenceStatus !== null && latestRecord.presenceStatus !== '')
                    ? ('' + latestRecord.presenceStatus)
                    : (latestRecord.fallStatus !== undefined && latestRecord.fallStatus !== null && latestRecord.fallStatus !== '')
                        ? (latestRecord.fallStatus.toLowerCase() === 'present' ? 'Present' : 'Absent')
                        : 'N/A';
                const latestPresenceStatusLower = (displayPresenceStatus + '').toLowerCase();
                if (latestPresenceStatusLower === 'absent') absentCount++;

                if (latestRecord.temperature) {
                    const temp = parseInt(latestRecord.temperature);
                    if (!isNaN(temp)) {
                        tempSum += temp;
                        tempCount++;
                    }
                }

                const row = document.createElement('tr');
                const lastSeen = latestRecord._capturedAt ? formatTo12Hour(latestRecord._capturedAt) : lastActive;
                row.innerHTML = `
                    ${statusHtml}
                    <td class="device-id-cell">${latestRecord.device_id || deviceId}</td>
                    <td>${displayPresenceDetection}</td>
                    <td><span class="status-badge ${displayPresenceStatus.toLowerCase() === 'present' ? 'present' : 'absent'}">${displayPresenceStatus}</span></td>
                    <td>${latestRecord.sleepPattern || latestRecord.sleep_pattern || '-'}</td>
                    <td>${latestRecord.sound || '-'}</td>
                    <td>${latestRecord.temperature || '-'}°C</td>
                    <td>${startTime}</td>
                    <td>${lastSeen}</td>
                `;
                // increment total rows seen
                totalRowsCounted++;
                if (displayedCount < MAX_DISPLAY) {
                    tbody.appendChild(row);
                    displayedCount++;
                }
            }
        }
        if (limitReached) break;
    }

    // Update summary cards
    updateSummary(activeCount, totalCount, absentCount, tempCount > 0 ? (tempSum / tempCount).toFixed(1) : '--');

    // Show a small notice if we limited the number of displayed rows
    const paginationContainer = document.getElementById('paginationContainer');
    let noticeEl = document.getElementById('displayLimitNotice');
    if (!noticeEl) {
        noticeEl = document.createElement('div');
        noticeEl.id = 'displayLimitNotice';
        noticeEl.style = 'margin-bottom:8px; color:#666; font-size:13px;';
        paginationContainer.parentNode.insertBefore(noticeEl, paginationContainer);
    }
    if (displayedCount >= MAX_DISPLAY) {
        noticeEl.textContent = `Showing ${displayedCount} records (limited to ${MAX_DISPLAY})`;
    } else {
        noticeEl.textContent = '';
    }
    
    // Display paginated rows
    displayAllRows();

    console.log('Sensor table updated with', totalCount, 'devices -', activeCount, 'active');

    // Update summary cards
    updateSummary(activeCount, totalCount, absentCount, tempCount > 0 ? (tempSum / tempCount).toFixed(1) : '--');
    
    // Display paginated rows
    displayAllRows();

    console.log('Sensor table updated with', totalCount, 'devices -', activeCount, 'active');
}, (error) => {
    // onValue error handler
    console.error('Realtime DB onValue error:', error);
    showDebug('Realtime DB error: ' + (error && error.message ? error.message : String(error)), 'error');
    const tbody = document.getElementById('sensorTableBody');
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px; color:#900;">Realtime DB read error. Check console and Firebase rules.</td></tr>';
});

// Poll every 2 seconds to recalculate active/inactive status based on elapsed time
setInterval(() => {
    const rows = document.querySelectorAll('#sensorTable tbody tr');
    rows.forEach(row => {
        const statusIndicator = row.querySelector('.status-indicator');
        if (!statusIndicator) return;
        
        // Get the "Last Active" timestamp from the last column
        const cells = row.querySelectorAll('td');
        if (cells.length < 9) return;
        
        const lastActiveText = cells[cells.length - 1].textContent.trim();
        if (!lastActiveText || lastActiveText === '-') return;
        
        // Parse the timestamp to calculate age
        const lastActiveDate = new Date(lastActiveText);
        if (isNaN(lastActiveDate.getTime())) return;
        
        const age = Date.now() - lastActiveDate.getTime();
        const ACTIVE_THRESHOLD = 10 * 1000; // 10 seconds
        const isActive = age <= ACTIVE_THRESHOLD;
        
        // Update the status indicator color
        if (isActive) {
            statusIndicator.classList.remove('inactive');
            statusIndicator.classList.add('active');
            statusIndicator.setAttribute('title', 'Active');
        } else {
            statusIndicator.classList.remove('active');
            statusIndicator.classList.add('inactive');
            statusIndicator.setAttribute('title', 'Inactive');
        }
    });
}, 2000);

// Update summary cards with data
function updateSummary(active, total, absent, avgTemp) {
    document.getElementById('activeCount').textContent = active;
    // Force total devices to display 1 regardless of actual count
    document.getElementById('totalCount').textContent = 1;
    document.getElementById('absentCount').textContent = absent;
    document.getElementById('avgTemp').textContent = avgTemp === '--' ? avgTemp : avgTemp + '°C';
}
</script>
