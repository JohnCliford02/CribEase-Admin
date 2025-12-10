<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    header("Location: index.php"); 
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php" class="active">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <h1>Sensor Data</h1>

    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <input type="text" id="searchInput" placeholder="Search by user ID, temperature, or timestamp..." 
               style="flex: 1; max-width: 400px; padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
        <button class="btn" onclick="clearSearch()">Clear</button>
    </div>

    <div class="table-wrapper">
        <table id="sensorTable">
            <thead>
                <tr>
                    <th>Device ID</th>
                    <!-- <th>Distance</th> -->
                    <th>Fall Count</th>
                    <th>Fall Status</th>
                    <!-- <th>Humidity</th> -->
                    <th>Sleep Pattern</th>
                    <th>Sound</th>
                    <th>Temperature</th>
                    <th>Start Time</th>
                    <th>Last Active</th>

                </tr>
            </thead>
            <tbody>
                <!-- Sensor data rows will be inserted here -->
            </tbody>
        </table>
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getDatabase, ref, onValue } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

const style = document.createElement('style');
style.innerHTML = `
    .table-wrapper { overflow-x: auto; }
    #sensorTable { width: 100%; border-collapse: collapse; table-layout: fixed; }
    #sensorTable th, #sensorTable td { padding: 10px; text-align: left; vertical-align: top; word-break: break-word; white-space: normal; }
    #sensorTable th { background: #34495e; color: white; }
    #sensorTable .device-id-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: default; }
    #sensorTable .device-id-cell:hover { white-space: normal; overflow: visible; word-break: break-word; background-color: #f9f9f9; z-index: 10; position: relative; max-width: 300px; }
`;
document.head.appendChild(style);

// Search functionality
window.filterSensors = function() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#sensorTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
};

window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    filterSensors();
};

document.getElementById('searchInput').addEventListener('keyup', filterSensors);

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
onValue(ref(rtdb, 'devices'), (snapshot) => {
    const tbody = document.getElementById('sensorTable').getElementsByTagName('tbody')[0];
    tbody.innerHTML = '';

    const data = snapshot.val();
    if (!data) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="9" style="text-align:center; padding:20px;">No sensor data found</td>';
        tbody.appendChild(row);
        return;
    }

    // Iterate through each device and display ALL sensor records (historical data)
    Object.keys(data).forEach(deviceId => {
        const device = data[deviceId];
        const sensor = device.sensor || {};
        const info = device.info || {};

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

        // Check if sensor is flat (single record) or nested (multiple historical records)
        const sensorKeys = Object.keys(sensor);
        
        if (sensorKeys.length === 0) {
            // No sensor data for this device
            return;
        }

        // Check if this is a flat structure (has temperature, fallCount directly)
        if (sensor.temperature !== undefined || sensor.fallCount !== undefined || sensor.fallStatus !== undefined) {
            // Flat sensor structure - single row
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="device-id-cell">${sensor.device_id || deviceId}</td>
                <td>${sensor.fallCount || '-'}</td>
                <td>${sensor.fallStatus || '-'}</td>
                <td>${sensor.sleepPattern || sensor.sleep_pattern || '-'}</td>
                <td>${sensor.sound || '-'}</td>
                <td>${sensor.temperature || '-'}</td>
                <td>${startTime}</td>
                <td>${lastActive}</td>
            `;
            tbody.appendChild(row);
        } else {
            // Nested structure - create row for EACH historical record
            sensorKeys.forEach(recordKey => {
                const record = sensor[recordKey];
                if (typeof record !== 'object' || record === null) return;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="device-id-cell">${record.device_id || deviceId}</td>
                    <td>${record.fallCount || '-'}</td>
                    <td>${record.fallStatus || '-'}</td>
                    <td>${record.sleepPattern || record.sleep_pattern || '-'}</td>
                    <td>${record.sound || '-'}</td>
                    <td>${record.temperature || '-'}</td>
                    <td>${startTime}</td>
                    <td>${lastActive}</td>
                `;
                tbody.appendChild(row);
            });
        }
    });

    console.log('Sensor table updated with', Object.keys(data).length, 'devices');
});
</script>
