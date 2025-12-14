<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">
<?php include 'includes/maintenance_banner.php'; ?>

<!-- Firebase (compat builds for namespaced API like firebase.firestore()) -->
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-database-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js"></script>

<style>
/* DASHBOARD UI FIXES */
.dashboard-container {
    width: calc(100% - 260px);
    max-width: 100%;
    padding: 20px;
}

/* Prevent horizontal page scroll caused by fixed sidebar + full-width content */
html, body {
    overflow-x: hidden;
}

.cards {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.card {
    padding: 20px;
    border-radius: 12px;
    width: 300px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}

.card h2 {
    font-size: 20px;
    margin-bottom: 10px;
}

.card .value {
    font-size: 40px;
    font-weight: bold;
    color: #2c3e50;
}

.latest-box {
    margin-top: 20px;
    width: 100%;
}

.latest-box .card {
    width: 100%;
}

.table-wrapper {
    overflow-x: auto;
    margin-top: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    table-layout: fixed;
}

th, td {
    padding: 10px;
    text-align: center;
    vertical-align: middle;
    word-break: break-word;
    white-space: normal;
}

th {
    background: #2c3e50;
    color: white;
}

td {
    background: white;
}

/* Search box styling */
.search-box {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box input {
    flex: 1;
    max-width: 400px;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.search-box input:focus {
    outline: none;
    border-color: #2980b9;
    box-shadow: 0 0 5px rgba(41, 128, 185, 0.3);
}

.search-box button {
    padding: 10px 15px;
}
</style>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="maintenance.php">Maintenance</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <div class="dashboard-container">
        <div id="maintenanceBannerContainer"></div>
        <h1>Dashboard</h1>

        <!-- CARDS -->
        <div class="cards">
            <div class="card">
                <h2>Total Users</h2>
                <div class="value" id="totalUsers">0</div>
            </div>

            <div class="card">
                <h2>Total Sensor Records</h2>
                <div class="value" id="totalSensors">0</div>
            </div>
        </div>

        <!-- SENSOR METRICS CARDS -->
        <div style="margin-top: 40px;">
            <h2>Sensor Records by Metric</h2>
            <div class="cards">
                <div class="card">
                    <h2>Presence Detection Records</h2>
                    <div class="value" id="presenceDetectionRecords">0</div>
                </div>

                <div class="card">
                    <h2>Presence Status Records</h2>
                    <div class="value" id="presenceStatusRecords">0</div>
                </div>

                <div class="card">
                    <h2>Sleep Pattern Records</h2>
                    <div class="value" id="sleepPatternRecords">0</div>
                </div>

                <div class="card">
                    <h2>Sound Records</h2>
                    <div class="value" id="soundRecords">0</div>
                </div>

                <div class="card">
                    <h2>Temperature Records</h2>
                    <div class="value" id="temperatureRecords">0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// FIREBASE CONFIG
const firebaseConfig = {
    apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
    authDomain: "esp32-connecttest.firebaseapp.com",
    databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "esp32-connecttest",
    storageBucket: "esp32-connecttest.firebasestorage.app",
    messagingSenderId: "950000610308",
    appId: "1:950000610308:web:a39583249e23784128d951"
};

firebase.initializeApp(firebaseConfig);
console.log('Firebase init:', firebase && firebase.apps ? firebase.apps.length : 'n/a');
/* Use Firestore (client-side) */
const db = firebase.firestore();

/* TOTAL USERS */
db.collection("users").onSnapshot(snapshot => {
    const count = snapshot.size || 0;
    document.getElementById("totalUsers").innerText = count;
}, err => console.error('users snapshot error', err));

/* TOTAL SENSOR RECORDS - Count all sensor records from Realtime Database devices */
const rtdb = firebase.database();
rtdb.ref('devices').on('value', (snapshot) => {
    let totalSensorRecords = 0;
    let presenceDetectionCount = 0;
    let presenceStatusCount = 0;
    let sleepPatternCount = 0;
    let soundCount = 0;
    let temperatureCount = 0;
    
    const data = snapshot.val();
    
    if (data) {
        // Iterate through each device
        Object.keys(data).forEach(deviceId => {
            const device = data[deviceId];
            const sensor = device.sensor || {};
            
            // Check if sensor is flat (single record) or nested (multiple records)
            const sensorKeys = Object.keys(sensor);
            
            if (sensorKeys.length > 0) {
                // Check if this is a flat structure (has temperature, presenceDetection/fallCount directly)
                if (sensor.temperature !== undefined || sensor.presenceDetection !== undefined || sensor.presenceStatus !== undefined || sensor.fallCount !== undefined || sensor.fallStatus !== undefined) {
                    // Flat structure = 1 record per device
                    totalSensorRecords += 1;
                    
                    // Count each metric (check both new and old field names)
                    if ((sensor.presenceDetection !== undefined || sensor.fallCount !== undefined) && (sensor.presenceDetection !== null || sensor.fallCount !== null) && (sensor.presenceDetection !== '' || sensor.fallCount !== '')) {
                        presenceDetectionCount += 1;
                    }
                    if ((sensor.presenceStatus !== undefined || sensor.fallStatus !== undefined) && (sensor.presenceStatus !== null || sensor.fallStatus !== null) && (sensor.presenceStatus !== '' || sensor.fallStatus !== '')) {
                        presenceStatusCount += 1;
                    }
                    if ((sensor.sleepPattern || sensor.sleep_pattern) !== undefined && (sensor.sleepPattern || sensor.sleep_pattern) !== null && (sensor.sleepPattern || sensor.sleep_pattern) !== '') {
                        sleepPatternCount += 1;
                    }
                    if (sensor.sound !== undefined && sensor.sound !== null && sensor.sound !== '') {
                        soundCount += 1;
                    }
                    if (sensor.temperature !== undefined && sensor.temperature !== null && sensor.temperature !== '') {
                        temperatureCount += 1;
                    }
                } else {
                    // Nested structure = count each record object
                    sensorKeys.forEach(key => {
                        const record = sensor[key];
                        if (typeof record === 'object' && record !== null) {
                            totalSensorRecords += 1;
                            
                            // Count each metric (check both new and old field names)
                            if ((record.presenceDetection !== undefined || record.fallCount !== undefined) && (record.presenceDetection !== null || record.fallCount !== null) && (record.presenceDetection !== '' || record.fallCount !== '')) {
                                presenceDetectionCount += 1;
                            }
                            if ((record.presenceStatus !== undefined || record.fallStatus !== undefined) && (record.presenceStatus !== null || record.fallStatus !== null) && (record.presenceStatus !== '' || record.fallStatus !== '')) {
                                presenceStatusCount += 1;
                            }
                            if ((record.sleepPattern || record.sleep_pattern) !== undefined && (record.sleepPattern || record.sleep_pattern) !== null && (record.sleepPattern || record.sleep_pattern) !== '') {
                                sleepPatternCount += 1;
                            }
                            if (record.sound !== undefined && record.sound !== null && record.sound !== '') {
                                soundCount += 1;
                            }
                            if (record.temperature !== undefined && record.temperature !== null && record.temperature !== '') {
                                temperatureCount += 1;
                            }
                        }
                    });
                }
            }
        });
    }
    
    // Update total count
    document.getElementById("totalSensors").innerText = totalSensorRecords;
    
    // Update metric counts
    document.getElementById("presenceDetectionRecords").innerText = presenceDetectionCount;
    document.getElementById("presenceStatusRecords").innerText = presenceStatusCount;
    document.getElementById("sleepPatternRecords").innerText = sleepPatternCount;
    document.getElementById("soundRecords").innerText = soundCount;
    document.getElementById("temperatureRecords").innerText = temperatureCount;
}, err => console.error('sensor count error', err));
</script>
